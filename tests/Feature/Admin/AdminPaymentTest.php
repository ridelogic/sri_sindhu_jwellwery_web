<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Concerns\FakesPaymentGateway;
use Tests\TestCase;

class AdminPaymentTest extends TestCase
{
    use FakesPaymentGateway;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-07 11:00:00');
        $this->fakeGateway();
        $this->admin = User::factory()->admin()->create();
        $this->rateToday(12500);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** A verified successful payment made through the real service + fake gateway. */
    private function successfulPayment(string $name = 'Priya Sundar'): Payment
    {
        $customer = $this->approvedCustomer(['name' => $name]);
        $payments = app(PaymentService::class);
        $payment = $payments->initiate($customer, $this->schemeFor($customer), (string) Str::uuid());

        return $payments->handleCallback($payment, $this->gatewayPays($payment), $customer)->fresh();
    }

    private function attempt(PaymentStatus $status, string $name): Payment
    {
        $customer = $this->approvedCustomer(['name' => $name]);

        return Payment::factory()->for($this->schemeFor($customer), 'customerPlan')->status($status)->create();
    }

    public function test_guests_and_customers_cannot_view_admin_payments(): void
    {
        $payment = $this->successfulPayment();

        $this->get(route('admin.payments.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.payments.show', $payment))->assertRedirect(route('admin.login'));

        $customer = $payment->user;
        $this->actingAs($customer, 'customer')->get(route('admin.payments.index'))->assertRedirect(route('admin.login'));
        $this->actingAs($customer, 'web')->get(route('admin.payments.show', $payment))->assertForbidden();

        foreach (['admin.payments.index', 'admin.payments.show'] as $name) {
            $this->assertContains('admin', app('router')->getRoutes()->getByName($name)->gatherMiddleware());
        }
    }

    public function test_admin_can_view_payment_list_newest_first(): void
    {
        $this->attempt(PaymentStatus::Failed, 'Older Failed');
        Carbon::setTestNow('2026-10-07 12:00:00');
        $success = $this->successfulPayment('Newer Success');

        $response = $this->actingAs($this->admin, 'web')->get(route('admin.payments.index'))->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Newer Success')->assertSee($success->customerPlan->scheme_number)
            ->assertSee('₹5,000')->assertSee('Successful')->assertSee('Razorpay', false)
            ->assertSee($success->reference)->assertSee('0.400000 g')
            ->assertSeeInOrder(['Newer Success', 'Older Failed']);

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertDontSee(self::GATEWAY_SECRET)->assertDontSee($success->gateway_signature);
    }

    public function test_admin_filters_work(): void
    {
        $this->successfulPayment('Success Person');
        $this->attempt(PaymentStatus::Failed, 'Failed Person');
        $this->attempt(PaymentStatus::Cancelled, 'Cancelled Person');
        $this->attempt(PaymentStatus::Created, 'Created Person');
        $this->attempt(PaymentStatus::Pending, 'Pending Person');

        $everyone = ['Success Person', 'Failed Person', 'Cancelled Person', 'Created Person', 'Pending Person'];
        $cases = [
            'success' => ['Success Person'],
            'failed' => ['Failed Person'],
            'cancelled' => ['Cancelled Person'],
            'pending' => ['Created Person', 'Pending Person'],
        ];

        $this->actingAs($this->admin, 'web');
        foreach ($cases as $status => $visible) {
            $response = $this->get(route('admin.payments.index', ['status' => $status]))->assertOk();
            foreach ($everyone as $name) {
                in_array($name, $visible, true) ? $response->assertSee($name) : $response->assertDontSee($name);
            }
        }

        $all = $this->get(route('admin.payments.index'))->assertOk();
        foreach ($everyone as $name) {
            $all->assertSee($name);
        }
        $this->get(route('admin.payments.index', ['status' => "x' OR 1=1"]))->assertOk()->assertSee('Failed Person');
    }

    public function test_admin_can_view_payment_detail(): void
    {
        $payment = $this->successfulPayment();

        $this->actingAs($this->admin, 'web')->get(route('admin.payments.show', $payment))->assertOk()
            ->assertSee('Priya Sundar')->assertSee($payment->customerPlan->scheme_number)
            ->assertSee('₹5,000')->assertSee('Successful')->assertSee('Razorpay')
            ->assertSee($payment->gateway_order_id)->assertSee($payment->gateway_payment_id)
            ->assertSee('0.400000 g')->assertSee('₹12,500')->assertSee('22K')
            ->assertSee('Verified (hidden)')
            ->assertSee('Payment initiated')->assertSee('Payment successful')
            ->assertDontSee($payment->gateway_signature)
            ->assertDontSee(self::GATEWAY_SECRET);

        $failed = $this->attempt(PaymentStatus::Failed, 'Failed Person');
        $this->get(route('admin.payments.show', $failed))->assertOk()
            ->assertSee('Payment was declined by the bank.')->assertSee('No gold credited');

        $this->get(route('admin.payments.show', 999999))->assertNotFound();
    }

    public function test_admin_dashboard_shows_real_payment_totals(): void
    {
        $this->successfulPayment('A');
        $this->successfulPayment('B');
        $this->attempt(PaymentStatus::Failed, 'C');
        Carbon::setTestNow('2026-10-08 10:00:00');

        $html = $this->actingAs($this->admin, 'web')->get(route('admin.dashboard'))->assertOk()->getContent();
        $stat = function ($key) use (&$html) {
            return preg_match('/data-stat="'.$key.'".*?data-stat-value>(.*?)<\/p>/s', $html, $m) ? trim($m[1]) : null;
        };

        $this->assertSame('0', $stat('today-payments'));   // nothing paid on the 8th
        $this->assertSame('₹0', $stat('today-amount'));
        $this->assertSame('2', $stat('total-payments'));

        Carbon::setTestNow('2026-10-07 18:00:00');
        $html = $this->get(route('admin.dashboard'))->getContent();
        $this->assertSame('2', $stat('today-payments'));
        $this->assertSame('₹10,000', $stat('today-amount'));
    }
}
