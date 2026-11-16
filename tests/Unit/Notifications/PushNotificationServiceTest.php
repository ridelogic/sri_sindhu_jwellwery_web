<?php

namespace Tests\Unit\Notifications;

use App\Enums\DeliveryStatus;
use App\Enums\DevicePlatform;
use App\Enums\KycStatus;
use App\Enums\NotificationType;
use App\Enums\PaymentStatus;
use App\Models\DeviceToken;
use App\Models\KycProfile;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Notifications\NotificationMessage;
use App\Push\FirebaseCloudMessagingProvider;
use App\Push\NullPushProvider;
use App\Push\PushMessage;
use App\Push\PushNotificationProvider;
use App\Services\DeviceTokenService;
use App\Services\GoldRateUpdateService;
use App\Services\KycService;
use App\Services\NotificationService;
use App\Services\PaymentService;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\Concerns\FakesPaymentGateway;
use Tests\Support\FakePushProvider;
use Tests\TestCase;

class PushNotificationServiceTest extends TestCase
{
    use FakesPaymentGateway;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-07 11:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function device(User $user, string $platform = 'android'): DeviceToken
    {
        return app(DeviceTokenService::class)->register($user, 'token-'.Str::random(50), DevicePlatform::from($platform), 'Phone');
    }

    private function useProvider(PushNotificationProvider $provider): void
    {
        $this->app->instance(PushNotificationProvider::class, $provider);
    }

    /** A throwaway RSA key generated for the test — never a real credential. */
    private function fcmConfig(): array
    {
        // Some PHP builds (e.g. XAMPP on Windows) need an explicit openssl.cnf to GENERATE keys;
        // signing at runtime does not.
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        foreach ([getenv('OPENSSL_CONF') ?: null, dirname(PHP_BINARY).'/extras/openssl/openssl.cnf'] as $cnf) {
            if ($cnf && is_file($cnf)) {
                $options['config'] = $cnf;
                break;
            }
        }
        $key = openssl_pkey_new($options);
        openssl_pkey_export($key, $pem, null, $options);

        return [
            'project_id' => 'test-project',
            'client_email' => 'push@test-project.iam.gserviceaccount.com',
            'private_key' => str_replace("\n", '\n', $pem), // single-line .env form
            'token_uri' => 'https://oauth2.googleapis.com/token',
            'send_uri' => 'https://fcm.googleapis.com/v1/projects/%s/messages:send',
            'timeout' => 5,
        ];
    }

    public function test_send_to_user_reaches_every_active_device(): void
    {
        $fake = new FakePushProvider;
        $this->useProvider($fake);
        $user = User::factory()->create();
        $this->device($user, 'android');
        $this->device($user, 'ios');
        $this->device($user, 'web')->forceFill(['is_active' => false])->save();

        $deliveries = app(PushNotificationService::class)->sendToUser($user, new PushMessage('Hi', 'Body'), (string) Str::uuid());

        $this->assertCount(2, $deliveries);
        $this->assertCount(2, $fake->sent);
        $this->assertSame(2, NotificationDelivery::query()->where('status', DeliveryStatus::Sent->value)->count());
    }

    public function test_send_to_users_and_send_to_token(): void
    {
        $fake = new FakePushProvider;
        $this->useProvider($fake);
        $users = User::factory()->count(3)->create()->each(fn ($u) => $this->device($u));

        $this->assertSame(3, app(PushNotificationService::class)->sendToUsers($users, new PushMessage('Hi', 'Body')));
        $this->assertSame(DeliveryStatus::Sent, app(PushNotificationService::class)->sendToToken(DeviceToken::query()->first(), new PushMessage('Hi', 'Body'))->status);
    }

    public function test_missing_fcm_credentials_record_not_configured_and_never_sent(): void
    {
        Http::preventStrayRequests();
        config(['push.provider' => 'fcm', 'push.fcm.project_id' => null, 'push.fcm.client_email' => null, 'push.fcm.private_key' => null]);
        $user = User::factory()->create();
        $this->device($user);

        $this->assertFalse(app(PushNotificationProvider::class)->isConfigured());

        $notification = app(NotificationService::class)->notify($user, new NotificationMessage(NotificationType::KycApproved, 'KYC Approved', 'Approved.', 'k'));

        $this->assertNotNull($notification); // in-app still works
        $delivery = NotificationDelivery::query()->sole();
        $this->assertSame(DeliveryStatus::NotConfigured, $delivery->status);
        $this->assertNull($delivery->attempted_at);
        Http::assertNothingSent();

        $this->assertSame(DeliveryStatus::NotConfigured, (new NullPushProvider)->send('t', new PushMessage('a', 'b'))->status);
    }

    public function test_invalid_tokens_are_deactivated(): void
    {
        $this->useProvider(new FakePushProvider('invalid'));
        $user = User::factory()->create();
        $device = $this->device($user);

        app(PushNotificationService::class)->sendToUser($user, new PushMessage('Hi', 'Body'), (string) Str::uuid());

        $this->assertFalse($device->fresh()->is_active);
        $this->assertSame(DeliveryStatus::Failed, NotificationDelivery::query()->sole()->status);
    }

    public function test_provider_exceptions_are_contained_and_safe(): void
    {
        $this->useProvider(new FakePushProvider('throw'));
        $user = User::factory()->create();
        $this->device($user);

        app(PushNotificationService::class)->sendToUser($user, new PushMessage('Hi', 'Body'), (string) Str::uuid());

        $delivery = NotificationDelivery::query()->sole();
        $this->assertSame(DeliveryStatus::Failed, $delivery->status);
        $this->assertSame('Delivery failed.', $delivery->error); // no exception text
    }

    public function test_retried_job_never_double_sends(): void
    {
        $fake = new FakePushProvider;
        $this->useProvider($fake);
        $user = User::factory()->create();
        $this->device($user);
        $deliveries = app(PushNotificationService::class)->sendToUser($user, new PushMessage('Hi', 'Body'), (string) Str::uuid());

        (new \App\Jobs\SendPushNotification($deliveries->pluck('id')->all(), new PushMessage('Hi', 'Body')))->handle(app(PushNotificationService::class));

        $this->assertCount(1, $fake->sent);
    }

    public function test_fcm_provider_sends_v1_request_without_leaking_credentials(): void
    {
        $config = $this->fcmConfig();
        config(['push.provider' => 'fcm', 'push.fcm' => $config]);
        Http::preventStrayRequests();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.test-access', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/test-project/messages/1']),
        ]);
        Log::spy();

        $user = User::factory()->create();
        $device = $this->device($user);
        $this->assertTrue(app(PushNotificationProvider::class)->isConfigured());

        app(NotificationService::class)->notify($user, new NotificationMessage(NotificationType::GoldCredited, 'Gold Credited', '0.400000 g of 22K gold has been added to your savings.', 'gold:1', url: '/customer/payments/X'));

        $this->assertSame(DeliveryStatus::Sent, NotificationDelivery::query()->sole()->status);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'oauth2.googleapis.com/token')
            && $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer' && substr_count($request['assertion'], '.') === 2);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://fcm.googleapis.com/v1/projects/test-project/messages:send'
            && $request->hasHeader('Authorization', 'Bearer ya29.test-access')
            && $request['message']['token'] === $device->token
            && $request['message']['notification']['title'] === 'Gold Credited'
            && $request['message']['data']['type'] === 'gold_credited');

        foreach (['info', 'warning', 'error', 'debug'] as $level) {
            Log::shouldNotHaveReceived($level);
        }
        $this->assertStringNotContainsString('PRIVATE KEY', NotificationDelivery::query()->get()->toJson());
    }

    public function test_fcm_unregistered_token_is_marked_invalid(): void
    {
        config(['push.provider' => 'fcm', 'push.fcm' => $this->fcmConfig()]);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.x']),
            'fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404),
        ]);
        $device = $this->device(User::factory()->create());

        $result = app(PushNotificationService::class)->sendToToken($device, new PushMessage('Hi', 'Body'));

        $this->assertSame(DeliveryStatus::Failed, $result->status);
        $this->assertTrue($result->invalidToken);
        $this->assertFalse($device->fresh()->is_active);
        $this->assertStringNotContainsString($device->token, (string) $result->error);
    }

    public function test_invalid_private_key_is_not_configured(): void
    {
        $provider = new FirebaseCloudMessagingProvider(['project_id' => 'p', 'client_email' => 'e@x', 'private_key' => 'not-a-key'] + $this->fcmConfig());

        $this->assertFalse($provider->isConfigured());
        $this->assertSame(DeliveryStatus::NotConfigured, $provider->send('token', new PushMessage('a', 'b'))->status);
    }

    public function test_push_credentials_are_never_exposed_in_responses(): void
    {
        $config = $this->fcmConfig();
        config(['push.provider' => 'fcm', 'push.fcm' => $config]);
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();

        $pages = $this->actingAs($admin, 'web')->get(route('admin.notifications.index'))->getContent()
            .$this->actingAs($customer, 'customer')->get(route('customer.notifications.settings'))->getContent()
            .$this->get(route('customer.notifications.index'))->getContent();

        foreach ([$config['client_email'], 'PRIVATE KEY', substr($config['private_key'], 40, 30)] as $secret) {
            $this->assertStringNotContainsString($secret, $pages);
        }
    }

    // Push failure never rolls back business operations ------------------------------------------

    public function test_push_failure_does_not_roll_back_payment_kyc_or_rate_update(): void
    {
        $this->useProvider(new FakePushProvider('throw'));
        $this->fakeGateway();
        $customer = $this->approvedCustomer();
        $this->device($customer);

        // Payment.
        $this->rateToday(12500);
        $payments = app(PaymentService::class);
        $payment = $payments->initiate($customer, $this->schemeFor($customer), (string) Str::uuid());
        $this->assertSame(PaymentStatus::Success, $payments->handleCallback($payment, $this->gatewayPays($payment), $customer)->status);

        // KYC approval.
        $other = User::factory()->create();
        $this->device($other);
        $kyc = KycProfile::factory()->for($other)->create(['kyc_status' => KycStatus::Submitted]);
        app(KycService::class)->approve($kyc, User::factory()->admin()->create());
        $this->assertSame(KycStatus::Verified, $kyc->fresh()->kyc_status);

        // Gold-rate update.
        $records = app(GoldRateUpdateService::class)->record(today(), '10:45', ['22K' => '12600', '24K' => '13600']);
        $this->assertCount(2, $records);

        // In-app notifications exist; push attempts are recorded as failed, not sent.
        $this->assertGreaterThan(0, $customer->notifications()->count());
        $this->assertSame(0, NotificationDelivery::query()->where('status', 'sent')->count());
        $this->assertGreaterThan(0, NotificationDelivery::query()->where('status', 'failed')->count());
    }
}
