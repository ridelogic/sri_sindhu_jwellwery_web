<?php

namespace Tests\Feature\Admin;

use App\Enums\NotificationType;
use App\Models\User;
use App\Notifications\NotificationMessage;
use App\Push\PushNotificationProvider;
use App\Services\DeviceTokenService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\FakePushProvider;
use Tests\TestCase;

class AdminNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-07 11:00:00');
        $this->admin = User::factory()->admin()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seedNotifications(): User
    {
        $customer = User::factory()->create(['name' => 'Priya Sundar']);
        $service = app(NotificationService::class);
        $service->notify($customer, new NotificationMessage(NotificationType::PaymentSuccess, 'Payment Successful', 'Private message body for Priya', 'k1', ['Scheme' => 'SSJ-PRIVATE-1']));
        $service->notify($customer, new NotificationMessage(NotificationType::GoldRateUpdated, 'Today\'s Gold Rate Updated', '22K Gold ₹12,500/g', 'k2'));
        $customer->notifications()->where('type', 'gold_rate_updated')->first()->markAsRead();

        return $customer;
    }

    public function test_guests_and_customers_cannot_view_admin_notifications(): void
    {
        $customer = User::factory()->create();

        $this->get(route('admin.notifications.index'))->assertRedirect(route('admin.login'));
        $this->actingAs($customer, 'customer')->get(route('admin.notifications.index'))->assertRedirect(route('admin.login'));
        $this->actingAs($customer, 'web')->get(route('admin.notifications.index'))->assertForbidden();
        $this->assertContains('admin', app('router')->getRoutes()->getByName('admin.notifications.index')->gatherMiddleware());
    }

    public function test_admin_sees_health_counts_without_private_content(): void
    {
        $customer = $this->seedNotifications();
        app(DeviceTokenService::class)->register($customer, 'secret-device-token-'.str_repeat('x', 30), \App\Enums\DevicePlatform::Android, 'Priya phone');

        $response = $this->actingAs($this->admin, 'web')->get(route('admin.notifications.index'))->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Payment successful')->assertSee('Gold rate updated')
            ->assertSee('data-push-provider', false);

        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/data-stat="all".*?data-stat-value>2</s', $html);
        $this->assertMatchesRegularExpression('/data-stat="unread".*?data-stat-value>1</s', $html);
        $this->assertMatchesRegularExpression('/data-type-row="payment_success".*?data-type-total>1</s', $html);

        foreach (['Private message body for Priya', 'SSJ-PRIVATE-1', 'secret-device-token-', 'Priya phone'] as $private) {
            $this->assertStringNotContainsString($private, $html);
        }
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_delivery_status_is_never_sent_without_credentials(): void
    {
        config(['push.provider' => 'fcm', 'push.fcm.project_id' => null, 'push.fcm.client_email' => null, 'push.fcm.private_key' => null]);
        $customer = User::factory()->create();
        app(DeviceTokenService::class)->register($customer, 'token-'.str_repeat('y', 40), \App\Enums\DevicePlatform::Ios);

        app(NotificationService::class)->notify($customer, new NotificationMessage(NotificationType::KycApproved, 'KYC Approved', 'Approved.', 'kyc:1'));

        $html = $this->actingAs($this->admin, 'web')->get(route('admin.notifications.index'))->assertOk()
            ->assertSee('FCM · Not configured')->getContent();
        $this->assertMatchesRegularExpression('/data-delivery="not_configured">1</', $html);
        $this->assertMatchesRegularExpression('/data-delivery="sent">0</', $html);
    }

    public function test_delivery_counts_reflect_real_outcomes(): void
    {
        $this->app->instance(PushNotificationProvider::class, new FakePushProvider);
        $customer = User::factory()->create();
        app(DeviceTokenService::class)->register($customer, 'token-'.str_repeat('z', 40), \App\Enums\DevicePlatform::Android);

        app(NotificationService::class)->notify($customer, new NotificationMessage(NotificationType::KycApproved, 'KYC Approved', 'Approved.', 'kyc:2'));

        $html = $this->actingAs($this->admin, 'web')->get(route('admin.notifications.index'))->getContent();
        $this->assertMatchesRegularExpression('/data-delivery="sent">1</', $html);
    }

    public function test_admin_navigation_links_to_notifications(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.dashboard'))->assertOk()
            ->assertSee(route('admin.notifications.index'), false);
    }
}
