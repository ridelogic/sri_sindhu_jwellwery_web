<?php

namespace Tests\Feature\Customer;

use App\Enums\KycStatus;
use App\Enums\NotificationType;
use App\Enums\PaymentStatus;
use App\Models\DeviceToken;
use App\Models\GoldRate;
use App\Models\KycProfile;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\NotificationMessage;
use App\Push\PushNotificationProvider;
use App\Services\KycService;
use App\Services\NotificationService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Concerns\FakesPaymentGateway;
use Tests\Support\FakePushProvider;
use Tests\TestCase;

class CustomerNotificationTest extends TestCase
{
    use FakesPaymentGateway;
    use RefreshDatabase;

    private User $customer;

    private FakePushProvider $push;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-07 11:00:00');
        $this->fakeGateway();
        $this->push = new FakePushProvider;
        $this->app->instance(PushNotificationProvider::class, $this->push);
        $this->customer = $this->approvedCustomer(['name' => 'Priya Sundar']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function notifyCustomer(?User $user = null, string $key = 'test:1', NotificationType $type = NotificationType::PaymentSuccess): DatabaseNotification
    {
        return app(NotificationService::class)->notify($user ?? $this->customer, new NotificationMessage(
            $type, 'Payment Successful', '₹5,000 payment received successfully.', $key, ['Scheme' => 'SSJ-TEST'],
        ));
    }

    private function updateRates(string $r22 = '12500', string $r24 = '13500', string $time = '10:30'): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'web')->post(route('admin.gold-rates.store'), [
            'rate_date' => today()->toDateString(), 'rate_time' => $time, 'rate_22k' => $r22, 'rate_24k' => $r24,
        ])->assertRedirect(route('admin.gold-rates.index'));
    }

    private function pay(?User $customer = null): Payment
    {
        $customer ??= $this->customer;
        $payments = app(PaymentService::class);
        $payment = $payments->initiate($customer, $this->schemeFor($customer), (string) Str::uuid());

        return $payments->handleCallback($payment, $this->gatewayPays($payment), $customer)->fresh();
    }

    private function ofType(NotificationType $type, ?User $user = null)
    {
        return ($user ?? $this->customer)->notifications()->where('type', $type->value)->get();
    }

    // Access & centre ----------------------------------------------------------------------

    public function test_guest_cannot_access_notifications(): void
    {
        $notification = $this->notifyCustomer();

        $this->get(route('customer.notifications.index'))->assertRedirect(route('login'));
        $this->get(route('customer.notifications.show', $notification->id))->assertRedirect(route('login'));
        $this->get(route('customer.notifications.settings'))->assertRedirect(route('login'));
        $this->post(route('customer.notifications.read', $notification->id))->assertRedirect(route('login'));
        $this->post(route('customer.notifications.read-all'))->assertRedirect(route('login'));
        $this->post(route('customer.devices.store'), ['token' => str_repeat('a', 40), 'platform' => 'android'])->assertRedirect(route('login'));

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_customer_can_view_own_notifications(): void
    {
        $this->notifyCustomer();

        $this->actingAs($this->customer, 'customer')->get(route('customer.notifications.index'))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Payment Successful')->assertSee('₹5,000 payment received successfully.')
            ->assertSee('data-notification-state="unread"', false)
            ->assertSee('Mark all as read');
    }

    public function test_customer_cannot_view_another_customers_notification(): void
    {
        $other = $this->approvedCustomer();
        $theirs = $this->notifyCustomer($other, 'theirs');

        $this->actingAs($this->customer, 'customer');
        $this->get(route('customer.notifications.show', $theirs->id))->assertNotFound();
        $this->post(route('customer.notifications.read', $theirs->id))->assertNotFound();
        $this->get(route('customer.notifications.index'))->assertDontSee($theirs->id);
        $this->post(route('customer.notifications.read-all'));

        $this->assertNull($theirs->fresh()->read_at);
    }

    public function test_viewing_a_notification_marks_it_read(): void
    {
        $notification = $this->notifyCustomer();

        $this->actingAs($this->customer, 'customer')->get(route('customer.notifications.show', $notification->id))
            ->assertOk()->assertSee('Payment Successful')->assertSee('SSJ-TEST');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_one_and_mark_all_as_read(): void
    {
        $first = $this->notifyCustomer(null, 'a');
        $this->notifyCustomer(null, 'b');
        $this->notifyCustomer(null, 'c');
        $this->actingAs($this->customer, 'customer');

        $this->postJson(route('customer.notifications.read', $first->id))->assertOk()->assertJson(['unread' => 2]);
        $this->assertNotNull($first->fresh()->read_at);

        $this->post(route('customer.notifications.read-all'))->assertSessionHas('status');
        $this->assertSame(0, $this->customer->unreadNotifications()->count());

        // State changes are never GET.
        $this->get(route('customer.notifications.index').'/read-all')->assertMethodNotAllowed();
    }

    public function test_unread_count_badge_is_correct_and_hidden_at_zero(): void
    {
        $this->actingAs($this->customer, 'customer');
        $html = $this->get(route('customer.dashboard'))->getContent();
        $this->assertStringContainsString('data-notification-bell', $html);
        $this->assertStringNotContainsString('data-unread-count', $html); // no "0" badge

        $this->notifyCustomer(null, 'a');
        $this->notifyCustomer(null, 'b');
        $this->notifyCustomer(null, 'c');

        $this->get(route('customer.dashboard'))->assertSee('data-unread-count>3', false)->assertSee('Notifications, 3 unread');
    }

    public function test_empty_state(): void
    {
        $this->actingAs($this->customer, 'customer')->get(route('customer.notifications.index'))
            ->assertOk()->assertSee('data-empty-notifications', false)->assertSee('No notifications yet.')->assertDontSee('Mark all as read');
        $this->get(route('customer.dashboard'))->assertSee('Latest')->assertSee('No notifications yet.');
    }

    public function test_dashboard_shows_latest_three(): void
    {
        foreach (range(1, 5) as $i) {
            Carbon::setTestNow(now()->addMinute());
            app(NotificationService::class)->notify($this->customer, new NotificationMessage(NotificationType::PaymentSuccess, "Title $i", "Message $i", "k$i"));
        }

        $this->actingAs($this->customer, 'customer')->get(route('customer.dashboard'))
            ->assertSee('Title 5')->assertSee('Title 4')->assertSee('Title 3')->assertDontSee('Title 2')
            ->assertSee('View All Notifications');
    }

    // Gold rate (mandatory) ------------------------------------------------------------------

    public function test_gold_rate_update_creates_one_notification_with_both_rates(): void
    {
        $this->updateRates('12500', '13500', '10:30');

        $notifications = $this->ofType(NotificationType::GoldRateUpdated);
        $this->assertCount(1, $notifications); // ONE for 22K + 24K
        $data = $notifications->sole()->data;

        $this->assertSame('Today\'s Gold Rate Updated', $data['title']);
        $this->assertSame('22K Gold ₹12,500/g · 24K Gold ₹13,500/g', $data['message']);
        $this->assertSame(['22K Gold' => '₹12,500/g', '24K Gold' => '₹13,500/g', 'Updated' => '07 Oct 2026, 10:30 AM'], $data['details']);
        $this->assertSame(['rate_date' => '2026-10-07', 'rate_time' => '10:30:00', 'rate_22k' => '12500.00', 'rate_24k' => '13500.00'], $data['data']);

        $this->actingAs($this->customer, 'customer')->get(route('customer.notifications.index'))
            ->assertSee('Today&#039;s Gold Rate Updated', false)->assertSee('₹12,500/g')->assertSee('₹13,500/g');
    }

    public function test_every_customer_is_notified_once_and_admins_are_not(): void
    {
        $others = User::factory()->count(3)->create();
        $this->updateRates();

        foreach ($others->push($this->customer) as $customer) {
            $this->assertCount(1, $this->ofType(NotificationType::GoldRateUpdated, $customer));
        }
        $this->assertSame(0, DatabaseNotification::query()->whereIn('notifiable_id', User::query()->admins()->pluck('id'))->count());
    }

    public function test_back_dated_rate_entry_is_not_announced(): void
    {
        $this->updateRates('12500', '13500', '10:30');
        $this->updateRates('12000', '13000', '08:00'); // earlier than the current rate → history only

        $this->assertCount(1, $this->ofType(NotificationType::GoldRateUpdated));
    }

    public function test_duplicate_rate_event_creates_no_duplicate_notification(): void
    {
        $this->updateRates();
        $rateIds = GoldRate::query()->latest('id')->limit(2)->pluck('id')->all();

        // Re-running the same job (retry / double dispatch) notifies nobody twice…
        (new \App\Jobs\SendGoldRateNotifications($rateIds))->handle(app(NotificationService::class));
        (new \App\Jobs\SendGoldRateNotifications(array_reverse($rateIds)))->handle(app(NotificationService::class));
        $this->assertCount(1, $this->ofType(NotificationType::GoldRateUpdated));

        // …but a genuinely new update still notifies.
        $this->updateRates('12600', '13600', '10:45');
        $this->assertCount(2, $this->ofType(NotificationType::GoldRateUpdated));
    }

    public function test_disabled_gold_rate_preference_prevents_only_rate_notifications(): void
    {
        $this->actingAs($this->customer, 'customer')->put(route('customer.notifications.settings.update'), [
            'gold_rate_updates' => '0', 'payment_updates' => '1', 'kyc_updates' => '1', 'promotional_notifications' => '0',
        ])->assertRedirect(route('customer.notifications.settings'));

        $this->updateRates();
        $this->assertCount(0, $this->ofType(NotificationType::GoldRateUpdated));

        $this->pay(); // payment notifications continue
        $this->assertCount(1, $this->ofType(NotificationType::PaymentSuccess));
    }

    // KYC ------------------------------------------------------------------------------------

    public function test_kyc_submission_approval_and_rejection_notify_with_safe_reason(): void
    {
        $customer = User::factory()->create();
        $kyc = KycProfile::factory()->for($customer)->create(['kyc_status' => KycStatus::Submitted, 'pan_number' => 'BKLPS7319M', 'aadhaar_last4' => '4821']);
        $admin = User::factory()->admin()->create();
        $service = app(KycService::class);

        $service->reject($kyc, $admin, 'PAN document is unclear.');
        $rejected = $this->ofType(NotificationType::KycRejected, $customer)->sole();
        $this->assertSame('KYC Requires Update', $rejected->data['title']);
        $this->assertSame('Your KYC needs an update. Please review the reason and resubmit.', $rejected->data['message']);
        $this->assertSame(['Reason' => 'PAN document is unclear.'], $rejected->data['details']);

        $kyc->refresh()->forceFill(['kyc_status' => KycStatus::Submitted])->save();
        $service->approve($kyc, $admin);
        $approved = $this->ofType(NotificationType::KycApproved, $customer)->sole();
        $this->assertSame('KYC Approved', $approved->data['title']);
        $this->assertSame('Your KYC has been approved successfully.', $approved->data['message']);

        $json = $customer->notifications()->get()->toJson();
        foreach (['BKLPS7319M', '4821', $admin->name, $admin->email] as $private) {
            $this->assertStringNotContainsString($private, $json);
        }
    }

    public function test_kyc_submitted_notification_from_customer_flow(): void
    {
        \Illuminate\Support\Facades\Storage::fake(KycService::DISK);
        $customer = User::factory()->create();

        $this->actingAs($customer, 'customer')->put(route('customer.kyc.update'), CustomerKycTest::details() + [
            'intent' => 'submit', 'documents' => ['aadhaar' => CustomerKycTest::pdf(), 'pan' => CustomerKycTest::pdf()],
        ])->assertSessionHasNoErrors();

        $this->assertSame('Your KYC has been submitted for review.', $this->ofType(NotificationType::KycSubmitted, $customer)->sole()->data['message']);
    }

    public function test_duplicate_kyc_decision_creates_no_duplicate_notification(): void
    {
        $customer = User::factory()->create();
        $kyc = KycProfile::factory()->for($customer)->create(['kyc_status' => KycStatus::Submitted]);
        $admin = User::factory()->admin()->create();

        app(KycService::class)->approve($kyc, $admin);
        try {
            app(KycService::class)->approve($kyc, $admin); // refused: already approved
        } catch (\Illuminate\Validation\ValidationException) {
        }
        $this->actingAs($admin, 'web')->post(route('admin.kyc.approve', $kyc))->assertSessionHasErrors('kyc');

        $this->assertCount(1, $this->ofType(NotificationType::KycApproved, $customer));
    }

    public function test_kyc_preference_is_respected(): void
    {
        $customer = User::factory()->create();
        app(NotificationService::class)->updatePreferences($customer, ['gold_rate_updates' => true, 'payment_updates' => true, 'kyc_updates' => false]);
        $kyc = KycProfile::factory()->for($customer)->create(['kyc_status' => KycStatus::Submitted]);

        app(KycService::class)->approve($kyc, User::factory()->admin()->create());

        $this->assertSame(KycStatus::Verified, $kyc->fresh()->kyc_status); // the decision itself is unaffected
        $this->assertCount(0, $customer->notifications);
    }

    // Payments ------------------------------------------------------------------------------

    public function test_payment_success_and_gold_credited_notifications_use_the_snapshot(): void
    {
        $this->rateToday(12500);
        $payment = $this->pay();

        $started = $this->ofType(NotificationType::PaymentInitiated)->sole();
        $this->assertSame('Payment Started', $started->data['title']);

        $success = $this->ofType(NotificationType::PaymentSuccess)->sole();
        $this->assertSame('₹5,000 payment received successfully.', $success->data['message']);
        $this->assertSame($payment->customerPlan->scheme_number, $success->data['details']['Scheme']);
        $this->assertSame('0.400000 g', $success->data['details']['Gold credited']);
        $this->assertSame('₹12,500/g', $success->data['details']['Rate']);

        $gold = $this->ofType(NotificationType::GoldCredited)->sole();
        $this->assertSame('0.400000 g of 22K gold has been added to your savings.', $gold->data['message']);
        $this->assertSame(['gold_grams' => '0.400000', 'rate_per_gram' => '12500.00', 'purity' => '22K', 'amount' => '5000.00', 'payment_reference' => $payment->reference], $gold->data['data']);
    }

    public function test_historical_payment_notification_never_uses_todays_rate(): void
    {
        // Day 1: ₹12,500/g → ₹5,000 buys 0.400000 g.
        Carbon::setTestNow('2026-10-06 11:00:00');
        $this->rateToday(12500);
        $this->pay();

        // Day 2: ₹13,000/g.
        Carbon::setTestNow('2026-10-07 11:00:00');
        $this->updateRates('13000', '14000', '09:00');

        foreach ([NotificationType::PaymentSuccess, NotificationType::GoldCredited] as $type) {
            $notification = $this->ofType($type)->sole();
            $html = $this->actingAs($this->customer, 'customer')->get(route('customer.notifications.show', $notification->id))->assertOk()->getContent();
            preg_match('/<article[^>]*data-notification-detail.*?<\/article>/s', $html, $m); // the notification itself, not the site's live rate strip
            $this->assertStringContainsString('₹12,500/g', $m[0]);
            $this->assertStringContainsString('0.400000 g', $m[0]);
            $this->assertStringNotContainsString('₹13,000', $m[0]);
        }
    }

    public function test_payment_failure_and_cancellation_notify_safely(): void
    {
        $this->rateToday(12500);
        $payments = app(PaymentService::class);
        $scheme = $this->schemeFor($this->customer);

        $failed = $payments->initiate($this->customer, $scheme, (string) Str::uuid());
        $payments->handleCallback($failed, $this->gatewayPays($failed, 'failed'), $this->customer);

        $notification = $this->ofType(NotificationType::PaymentFailed)->sole();
        $this->assertSame('Your payment could not be completed. You can try again.', $notification->data['message']);
        $this->assertStringNotContainsString('declined by the bank', json_encode($notification->data)); // no gateway error text
        $this->assertStringNotContainsString(self::GATEWAY_SECRET, json_encode($notification->data));

        $cancelled = $payments->initiate($this->customer, $scheme, (string) Str::uuid());
        $payments->cancel($cancelled, $this->customer);
        $this->assertSame('Your payment was cancelled. You can try again.', $this->ofType(NotificationType::PaymentCancelled)->sole()->data['message']);
    }

    public function test_duplicate_payment_callback_creates_no_duplicate_notification(): void
    {
        $this->rateToday(12500);
        $payments = app(PaymentService::class);
        $payment = $payments->initiate($this->customer, $this->schemeFor($this->customer), (string) Str::uuid());
        $response = $this->gatewayPays($payment);

        $payments->handleCallback($payment, $response, $this->customer);
        $payments->handleCallback($payment, $response, $this->customer);
        $payments->handleCallback($payment->fresh(), $response, $this->customer);

        $this->assertCount(1, $this->ofType(NotificationType::PaymentSuccess));
        $this->assertCount(1, $this->ofType(NotificationType::GoldCredited));

        // A legitimate next installment still notifies.
        Carbon::setTestNow('2026-11-03 11:00:00');
        $this->rateToday(12000);
        $next = $payments->initiate($this->customer, $payment->customerPlan->fresh(), (string) Str::uuid());
        $payments->handleCallback($next, $this->gatewayPays($next), $this->customer);
        $this->assertCount(2, $this->ofType(NotificationType::PaymentSuccess));
    }

    public function test_disabled_payment_preference_prevents_payment_notifications(): void
    {
        app(NotificationService::class)->updatePreferences($this->customer, ['gold_rate_updates' => true, 'payment_updates' => false, 'kyc_updates' => true]);
        $this->rateToday(12500);

        $payment = $this->pay();

        $this->assertSame(PaymentStatus::Success, $payment->status);
        $this->assertSame(0, $this->customer->notifications()->count());
    }

    public function test_preferences_page_defaults_and_saving(): void
    {
        $html = $this->actingAs($this->customer, 'customer')->get(route('customer.notifications.settings'))->assertOk()->getContent();
        $checked = fn ($key) => (bool) preg_match('/<input type="checkbox"[^>]*\schecked\s[^>]*data-preference="'.$key.'"/', $html);
        $this->assertTrue($checked('gold_rate_updates'));
        $this->assertTrue($checked('payment_updates'));
        $this->assertTrue($checked('kyc_updates'));
        $this->assertFalse($checked('promotional_notifications'));

        $this->put(route('customer.notifications.settings.update'), ['gold_rate_updates' => '0', 'payment_updates' => '1', 'kyc_updates' => '1', 'promotional_notifications' => '1', 'user_id' => '999']);

        $this->assertSame(
            ['gold_rate_updates' => false, 'payment_updates' => true, 'kyc_updates' => true, 'promotional_notifications' => true],
            app(NotificationService::class)->preferencesFor($this->customer),
        );
    }

    // Devices ---------------------------------------------------------------------------------

    public function test_device_token_registration_belongs_to_the_signed_in_customer(): void
    {
        $token = 'fcm-token-'.Str::random(60);

        $response = $this->actingAs($this->customer, 'customer')->postJson(route('customer.devices.store'), [
            'token' => $token, 'platform' => 'android', 'device_name' => 'Pixel 8', 'user_id' => 999,
        ])->assertCreated()->assertJson(['platform' => 'android', 'device_name' => 'Pixel 8', 'active' => true]);

        $this->assertStringNotContainsString($token, $response->getContent());
        $device = DeviceToken::query()->sole();
        $this->assertSame($this->customer->id, $device->user_id);
        $this->assertSame($token, $device->token);
        $this->assertNotSame($token, \Illuminate\Support\Facades\DB::table('device_tokens')->value('token')); // encrypted at rest
        $this->assertStringNotContainsString($token, $device->toJson());

        $this->get(route('customer.notifications.settings'))->assertSee('Pixel 8')->assertDontSee($token);

        $this->postJson(route('customer.devices.store'), ['token' => 'short', 'platform' => 'blackberry'])->assertUnprocessable();
    }

    public function test_duplicate_token_is_handled_safely_and_multiple_devices_are_supported(): void
    {
        $this->actingAs($this->customer, 'customer');
        $phone = 'token-phone-'.Str::random(40);
        $this->postJson(route('customer.devices.store'), ['token' => $phone, 'platform' => 'android'])->assertCreated();
        $this->postJson(route('customer.devices.store'), ['token' => $phone, 'platform' => 'android'])->assertCreated();
        $this->postJson(route('customer.devices.store'), ['token' => 'token-tablet-'.Str::random(40), 'platform' => 'ios'])->assertCreated();

        $this->assertSame(2, $this->customer->deviceTokens()->count());

        // The same physical device signed in to another account moves to that account.
        $other = $this->approvedCustomer();
        $this->actingAs($other, 'customer')->postJson(route('customer.devices.store'), ['token' => $phone, 'platform' => 'android'])->assertCreated();
        $this->assertSame(1, $this->customer->deviceTokens()->count());
        $this->assertSame(1, $other->deviceTokens()->count());
        $this->assertSame(2, DeviceToken::query()->count());

        // Both of a customer's devices receive a notification.
        $this->actingAs($this->customer, 'customer')->postJson(route('customer.devices.store'), ['token' => 'token-laptop-'.Str::random(40), 'platform' => 'web'])->assertCreated();
        $this->notifyCustomer(null, 'multi');
        $this->assertCount(2, $this->push->sent);
    }

    public function test_token_can_be_deactivated_only_by_its_owner(): void
    {
        $this->actingAs($this->customer, 'customer')->postJson(route('customer.devices.store'), ['token' => 'token-'.Str::random(40), 'platform' => 'ios'])->assertCreated();
        $device = DeviceToken::query()->sole();

        $other = $this->approvedCustomer();
        $this->actingAs($other, 'customer')->post(route('customer.devices.deactivate', $device->id))->assertNotFound();
        $this->assertTrue($device->fresh()->is_active);

        $this->actingAs($this->customer, 'customer')->post(route('customer.devices.deactivate', $device->id))->assertRedirect(route('customer.notifications.settings'));
        $this->assertFalse($device->fresh()->is_active);

        $this->notifyCustomer(null, 'after-removal');
        $this->assertCount(0, $this->push->sent); // inactive devices receive nothing
    }
}
