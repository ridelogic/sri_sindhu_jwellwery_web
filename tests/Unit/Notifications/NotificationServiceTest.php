<?php

namespace Tests\Unit\Notifications;

use App\Enums\GoldPurity;
use App\Enums\KycStatus;
use App\Enums\NotificationType;
use App\Enums\PaymentStatus;
use App\Models\GoldRate;
use App\Models\KycProfile;
use App\Models\User;
use App\Notifications\NotificationMessage;
use App\Notifications\NotificationMessages;
use App\Services\GoldRateUpdateService;
use App\Services\KycService;
use App\Services\NotificationService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\Concerns\FakesPaymentGateway;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use FakesPaymentGateway;
    use RefreshDatabase;

    private NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-07 11:00:00');
        $this->service = app(NotificationService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function message(string $key = 'event:1', NotificationType $type = NotificationType::PaymentSuccess): NotificationMessage
    {
        return new NotificationMessage($type, 'Title', 'Message', $key);
    }

    public function test_same_event_is_stored_once_per_user_but_new_events_are_stored(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull($this->service->notify($user, $this->message('event:1')));
        $this->assertNull($this->service->notify($user, $this->message('event:1')));
        $this->assertNotNull($this->service->notify($user, $this->message('event:2')));

        $this->assertSame(2, $user->notifications()->count());
    }

    public function test_database_constraint_backs_up_dedupe(): void
    {
        $user = User::factory()->create();
        $this->service->notify($user, $this->message('event:x'));

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(), 'type' => 'payment_success', 'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id, 'data' => '{}', 'dedupe_key' => 'event:x', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_preferences_default_and_filter_by_category(): void
    {
        $user = User::factory()->create();
        $this->assertSame(['gold_rate_updates' => true, 'payment_updates' => true, 'kyc_updates' => true, 'promotional_notifications' => false], $this->service->preferencesFor($user));

        $this->service->updatePreferences($user, ['gold_rate_updates' => false, 'payment_updates' => true, 'kyc_updates' => true]);

        $this->assertNull($this->service->notify($user, $this->message('rate', NotificationType::GoldRateUpdated)));
        $this->assertNotNull($this->service->notify($user, $this->message('pay', NotificationType::PaymentFailed)));
        $this->assertNotNull($this->service->notify($user, $this->message('kyc', NotificationType::KycApproved)));
    }

    public function test_admins_never_receive_customer_notifications(): void
    {
        $this->assertNull($this->service->notify(User::factory()->admin()->create(), $this->message()));
    }

    public function test_bulk_fan_out_is_chunked_respects_preferences_and_is_idempotent(): void
    {
        $customers = User::factory()->count(7)->create();
        User::factory()->admin()->create();
        $this->service->updatePreferences($customers[0], ['gold_rate_updates' => false, 'payment_updates' => true, 'kyc_updates' => true]);
        $message = $this->message('gold_rate:1-2', NotificationType::GoldRateUpdated);

        $created = $this->service->notifyMany(User::query()->customers(), $message, chunk: 2);
        $again = $this->service->notifyMany(User::query()->customers(), $message, chunk: 3);

        $this->assertSame(6, $created);   // 7 customers − 1 opted out; the admin excluded
        $this->assertSame(0, $again);     // re-run: nobody notified twice
        $this->assertSame(6, DatabaseNotification::query()->count());
    }

    public function test_gold_rate_message_uses_stored_values(): void
    {
        $rates = collect([
            GoldRate::factory()->purity(GoldPurity::K22)->rate(12500)->on('2026-10-07', '10:30:00')->create(),
            GoldRate::factory()->purity(GoldPurity::K24)->rate(13500)->on('2026-10-07', '10:30:00')->create(),
        ]);

        $message = NotificationMessages::goldRateUpdated($rates);

        $this->assertSame('Today\'s Gold Rate Updated', $message->title);
        $this->assertSame('22K Gold ₹12,500/g · 24K Gold ₹13,500/g', $message->message);
        $this->assertSame('07 Oct 2026, 10:30 AM', $message->details['Updated']);
        $this->assertSame('12500.00', $message->data['rate_22k']);
        $this->assertSame('13500.00', $message->data['rate_24k']);
        $this->assertSame('gold_rate:'.$rates->pluck('id')->sort()->implode('-'), $message->key);
    }

    public function test_notifications_are_created_only_after_commit(): void
    {
        $user = User::factory()->create();

        DB::transaction(function () use ($user) {
            $this->service->afterCommit(fn () => $this->service->notify($user, $this->message('commit')));
            $this->assertSame(0, $user->notifications()->count()); // not yet
        });
        $this->assertSame(1, $user->notifications()->count());

        try {
            DB::transaction(function () use ($user) {
                $this->service->afterCommit(fn () => $this->service->notify($user, $this->message('rolled-back')));
                throw new RuntimeException('business failure');
            });
        } catch (RuntimeException) {
        }
        $this->assertSame(1, $user->notifications()->count()); // rolled back → never notified
    }

    public function test_notification_failure_never_breaks_the_caller(): void
    {
        $this->service->afterCommit(fn () => throw new RuntimeException('notification store down'));
        $this->addToAssertionCount(1); // reached: the exception was contained
    }

    // Failure isolation for the real operations ----------------------------------------------

    private function breakNotifications(): void
    {
        $broken = Mockery::mock(NotificationService::class)->makePartial();
        $broken->shouldReceive('notify')->andThrow(new RuntimeException('notifications down'));
        $broken->shouldReceive('notifyMany')->andThrow(new RuntimeException('notifications down'));
        $this->app->instance(NotificationService::class, $broken);
    }

    public function test_notification_failure_does_not_roll_back_payment(): void
    {
        $this->fakeGateway();
        $customer = $this->approvedCustomer();
        $this->rateToday(12500);
        $payments = app(PaymentService::class);
        $payment = $payments->initiate($customer, $this->schemeFor($customer), (string) Str::uuid());

        $this->breakNotifications();
        $result = $payments->handleCallback($payment, $this->gatewayPays($payment), $customer);

        $this->assertSame(PaymentStatus::Success, $result->status);
        $this->assertSame('0.400000', $result->goldTransaction->gold_grams);
    }

    public function test_notification_failure_does_not_roll_back_kyc_approval(): void
    {
        $kyc = KycProfile::factory()->create(['kyc_status' => KycStatus::Submitted]);

        $this->breakNotifications();
        app(KycService::class)->approve($kyc, User::factory()->admin()->create());

        $this->assertSame(KycStatus::Verified, $kyc->fresh()->kyc_status);
    }

    public function test_notification_failure_does_not_roll_back_gold_rate_update(): void
    {
        User::factory()->create();
        $this->breakNotifications();

        $records = app(GoldRateUpdateService::class)->record(today(), '10:30', ['22K' => '12500', '24K' => '13500']);

        $this->assertCount(2, $records);
        $this->assertSame('12500.00', GoldRate::latestFor('22K')->rate_per_gram);
        $this->assertSame(0, DatabaseNotification::query()->count());
    }
}
