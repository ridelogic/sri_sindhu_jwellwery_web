<?php

namespace Tests\Feature\Admin;

use App\Enums\CustomerPlanStatus;
use App\Enums\NotificationType;
use App\Models\CustomerPlan;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\NotificationMessage;
use App\Services\NotificationService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Concerns\FakesPaymentGateway;
use Tests\TestCase;

/**
 * Admin UI redesign additions: the read-only Customer Schemes List and the
 * notification event list.
 */
class AdminSchemeListTest extends TestCase
{
    use FakesPaymentGateway;
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

    public function test_scheme_list_is_admin_only(): void
    {
        $customer = User::factory()->create();

        $this->get(route('admin.schemes.index'))->assertRedirect(route('admin.login'));
        $this->actingAs($customer, 'customer')->get(route('admin.schemes.index'))->assertRedirect(route('admin.login'));
        $this->actingAs($customer, 'web')->get(route('admin.schemes.index'))->assertForbidden();
        $this->assertContains('admin', app('router')->getRoutes()->getByName('admin.schemes.index')->gatherMiddleware());
    }

    public function test_scheme_list_shows_real_paid_figures(): void
    {
        $this->fakeGateway();
        $customer = $this->approvedCustomer(['name' => 'Priya Sundar', 'mobile_number' => '9876543210']);
        $scheme = $this->schemeFor($customer); // Gold Premium ₹5,000 × 11
        $this->rateToday(12500);
        $payments = app(PaymentService::class);
        $payment = $payments->initiate($customer, $scheme, (string) Str::uuid());
        $payments->handleCallback($payment, $this->gatewayPays($payment), $customer);

        $html = $this->actingAs($this->admin, 'web')->get(route('admin.schemes.index'))->assertOk()
            ->assertSee('Customer Schemes List')
            ->assertSee('Priya Sundar')->assertSee('+91 98765 43210')
            ->assertSee('Gold Premium')->assertSee($scheme->scheme_number)
            ->assertSee('11 months')->assertSee('Active')
            ->assertSee(route('admin.customers.schemes.show', [$customer->id, $scheme->id]), false)
            ->getContent();

        $this->assertMatchesRegularExpression('/data-scheme-list-row="'.$scheme->scheme_number.'".*?data-scheme-paid>₹5,000<.*?data-scheme-installments>1 \/ 11</s', $html);
    }

    public function test_scheme_list_filters(): void
    {
        $priya = User::factory()->create(['name' => 'Priya Sundar', 'mobile_number' => '9876543210']);
        $arjun = User::factory()->create(['name' => 'Arjun Ramesh', 'mobile_number' => '9123456780']);
        $basic = Plan::factory()->create(['name' => 'Gold Basic']);
        $plus = Plan::factory()->create(['name' => 'Gold Plus']);
        $a = CustomerPlan::factory()->for($priya)->for($basic)->status(CustomerPlanStatus::Active)->create();
        $b = CustomerPlan::factory()->for($arjun)->for($plus)->pending()->create();
        $c = CustomerPlan::factory()->for($arjun)->for($basic)->status(CustomerPlanStatus::Completed)->create();

        $this->actingAs($this->admin, 'web');
        $rows = fn ($query) => collect([$a, $b, $c])->filter(fn ($s) => str_contains($this->get(route('admin.schemes.index', $query))->getContent(), 'data-scheme-list-row="'.$s->scheme_number.'"'))->map->id->values()->all();

        $this->assertSame([$a->id, $b->id, $c->id], $rows([]));
        $this->assertSame([$a->id], $rows(['user' => $priya->id]));
        $this->assertSame([$b->id, $c->id], $rows(['mobile' => '+91 91234 56780']));
        $this->assertSame([$a->id, $c->id], $rows(['plan' => $basic->id]));
        $this->assertSame([$c->id], $rows(['status' => 'completed']));
        $this->assertSame([$c->id], $rows(['user' => $arjun->id, 'plan' => $basic->id]));

        $this->get(route('admin.schemes.index', ['status' => 'deleted']))->assertSessionHasErrors('status');
    }

    public function test_notification_list_groups_events_and_hides_personal_content(): void
    {
        $customers = User::factory()->count(3)->create();
        $service = app(NotificationService::class);
        foreach ($customers as $customer) {
            $service->notify($customer, new NotificationMessage(NotificationType::GoldRateUpdated, "Today's Gold Rate Updated", '22K Gold ₹12,500/g · 24K Gold ₹13,500/g', 'gold_rate:1-2'));
        }
        $service->notify($customers[0], new NotificationMessage(NotificationType::PaymentSuccess, 'Payment Successful', 'Private amount for Priya', 'payment_event:9'));
        $customers[1]->notifications()->first()->markAsRead();

        $html = $this->actingAs($this->admin, 'web')->get(route('admin.notifications.index'))->assertOk()
            ->assertSee('22K Gold ₹12,500/g · 24K Gold ₹13,500/g') // broadcast: shown
            ->assertSee('Personal — content hidden')
            ->assertDontSee('Private amount for Priya')
            ->assertSee('1 of 3 read')
            ->getContent();

        $this->assertSame(2, substr_count($html, 'data-notification-event='));

        $this->get(route('admin.notifications.index', ['type' => 'payment_success']))->assertSee('Payment Successful')->assertDontSee('24K Gold ₹13,500/g');
        $this->get(route('admin.notifications.index', ['status' => 'read']))->assertSee('No notifications match these filters.');
        $this->get(route('admin.notifications.index', ['date' => '2026-10-06']))->assertSee('No notifications match these filters.');
        $this->get(route('admin.notifications.index', ['type' => 'bogus']))->assertSessionHasErrors('type');
    }
}
