<?php

namespace Tests\Feature\Customer;

use App\Enums\CustomerPlanStatus;
use App\Enums\KycStatus;
use App\Enums\PaymentStatus;
use App\Models\CustomerPlan;
use App\Models\GoldRate;
use App\Models\GoldTransaction;
use App\Models\KycProfile;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\FakesPaymentGateway;
use Tests\TestCase;

class CustomerPaymentTest extends TestCase
{
    use FakesPaymentGateway;
    use RefreshDatabase;

    private User $customer;

    private CustomerPlan $scheme;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-07 11:00:00'); // IST
        $this->fakeGateway();

        $this->customer = $this->approvedCustomer(['name' => 'Priya Sundar']);
        $this->scheme = $this->schemeFor($this->customer); // ₹5,000 × 11
        $this->rateToday(12500);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function start(?CustomerPlan $scheme = null, ?string $token = null): TestResponse
    {
        $scheme ??= $this->scheme;

        return $this->actingAs($this->customer, 'customer')
            ->post(route('customer.schemes.payment.store', $scheme->scheme_number), ['payment_token' => $token ?? (string) Str::uuid()]);
    }

    private function startedPayment(): Payment
    {
        $this->start()->assertRedirect();

        return Payment::query()->latest('id')->firstOrFail();
    }

    private function postCallback(Payment $payment, array $response): TestResponse
    {
        return $this->actingAs($this->customer, 'customer')->post(route('customer.payments.callback', $payment->reference), $response);
    }

    private function payInFull(): Payment
    {
        $payment = $this->startedPayment();
        $this->postCallback($payment, $this->gatewayPays($payment))->assertRedirect(route('customer.payments.show', $payment->reference));

        return $payment->fresh();
    }

    // Access ----------------------------------------------------------------------------

    public function test_guest_cannot_access_payments(): void
    {
        $payment = Payment::factory()->for($this->scheme, 'customerPlan')->create();

        foreach ([
            route('customer.payments.index'),
            route('customer.payments.show', $payment->reference),
            route('customer.payments.receipt', $payment->reference),
            route('customer.payments.checkout', $payment->reference),
            route('customer.schemes.payment', $this->scheme->scheme_number),
        ] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
        $this->post(route('customer.schemes.payment.store', $this->scheme->scheme_number), ['payment_token' => (string) Str::uuid()])->assertRedirect(route('login'));
        $this->post(route('customer.payments.callback', $payment->reference), [])->assertRedirect(route('login'));

        $this->assertSame(1, Payment::query()->count());
    }

    public function test_customer_can_view_payment_page_for_own_scheme(): void
    {
        $this->actingAs($this->customer, 'customer')->get(route('customer.schemes.payment', $this->scheme->scheme_number))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee($this->scheme->scheme_number)->assertSee('Gold Premium')
            ->assertSee('₹5,000')->assertSee('Installment 1 of 11')
            ->assertSee('Pay ₹5,000 Securely')
            ->assertSee('₹12,500/g', false)->assertSee('0.400000 g')
            ->assertSee('name="payment_token"', false)
            ->assertDontSee('name="amount"', false)
            ->assertDontSee(self::GATEWAY_SECRET);
    }

    public function test_customer_cannot_pay_another_customers_scheme(): void
    {
        $other = $this->approvedCustomer();
        $theirs = $this->schemeFor($other);

        $this->actingAs($this->customer, 'customer');
        $this->get(route('customer.schemes.payment', $theirs->scheme_number))->assertNotFound();
        $this->post(route('customer.schemes.payment.store', $theirs->scheme_number), ['payment_token' => (string) Str::uuid()])->assertNotFound();
        $this->get(route('customer.schemes.payment', 'SSJ-0000-NOPE00'))->assertNotFound();

        $this->assertSame(0, Payment::query()->count());
    }

    // KYC --------------------------------------------------------------------------------

    public function test_payment_is_blocked_unless_kyc_is_approved(): void
    {
        $cases = ['none' => null, 'not_submitted' => KycStatus::Pending, 'pending_review' => KycStatus::Submitted, 'rejected' => KycStatus::Rejected];

        foreach ($cases as $label => $status) {
            $customer = User::factory()->create();
            if ($status) {
                KycProfile::factory()->for($customer)->create(['kyc_status' => $status]);
            }
            $scheme = $this->schemeFor($customer);

            $this->actingAs($customer, 'customer')->get(route('customer.schemes.payment', $scheme->scheme_number))->assertOk()
                ->assertSee('data-payment-blocked="kyc"', false)
                ->assertSee(PaymentService::KYC_MESSAGE)
                ->assertSee(route('customer.kyc'), false)
                ->assertDontSee('data-payment-form', false);

            // Enforced on the server, not just hidden in the page.
            $this->post(route('customer.schemes.payment.store', $scheme->scheme_number), ['payment_token' => (string) Str::uuid()])
                ->assertRedirect(route('customer.schemes.payment', $scheme->scheme_number))
                ->assertSessionHasErrors(['payment' => PaymentService::KYC_MESSAGE]);

            $this->assertSame(0, $scheme->payments()->count(), $label);
        }
        Http::assertNothingSent();
    }

    public function test_approved_kyc_allows_payment_and_order_is_created(): void
    {
        $this->start()->assertRedirect();

        $payment = Payment::query()->sole();
        $this->assertSame(PaymentStatus::Created, $payment->status);
        $this->assertStringStartsWith('order_', $payment->gateway_order_id);
        $this->assertSame('razorpay', $payment->gateway);
        $this->assertSame(1, $payment->installment_number);
        $this->assertSame($this->customer->id, $payment->user_id);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v1/orders') && $request['amount'] === 500000 && $request['currency'] === 'INR');

        $this->get(route('customer.payments.checkout', $payment->reference))->assertOk()
            ->assertSee(self::GATEWAY_KEY)                 // public key id only
            ->assertSee($payment->gateway_order_id)
            ->assertDontSee(self::GATEWAY_SECRET);
    }

    // Amount snapshot ---------------------------------------------------------------------

    public function test_payment_amount_comes_from_customer_plan_not_current_plan(): void
    {
        // Enrolled at ₹5,000 × 11 = ₹55,000; the admin then reprices the plan to ₹6,000 × 11 = ₹66,000.
        $this->assertSame('55000.00', $this->scheme->total_commitment);
        $this->scheme->plan->update(['monthly_amount' => 6000]);
        $this->assertSame('66000.00', $this->scheme->plan->fresh()->total_amount);

        $this->actingAs($this->customer, 'customer')->get(route('customer.schemes.payment', $this->scheme->scheme_number))
            ->assertSee('Pay ₹5,000 Securely')->assertDontSee('₹6,000');
        $this->get(route('customer.schemes.show', $this->scheme->scheme_number))
            ->assertSee('₹5,000')->assertSee('11 months')->assertSee('₹55,000')->assertDontSee('₹66,000');

        // A forged amount in the request is ignored.
        $this->post(route('customer.schemes.payment.store', $this->scheme->scheme_number), [
            'payment_token' => (string) Str::uuid(), 'amount' => '1', 'amount_paise' => '100', 'customer_plan_id' => '999', 'user_id' => '999',
        ]);
        $payment = Payment::query()->sole();
        $this->assertSame(500000, $payment->amount_paise);
        $this->assertSame($this->scheme->id, $payment->customer_plan_id);

        $this->postCallback($payment, $this->gatewayPays($payment));
        $this->assertSame(500000, $payment->goldTransaction()->sole()->amount_paise);
    }

    // Verification ------------------------------------------------------------------------

    public function test_successful_payment_is_recorded_with_gold_transaction(): void
    {
        $payment = $this->startedPayment();

        $this->postCallback($payment, $this->gatewayPays($payment))
            ->assertRedirect(route('customer.payments.show', $payment->reference))
            ->assertSessionHas('status', fn ($s) => str_contains($s, '0.400000 g of 22K gold'));

        $payment->refresh();
        $this->assertSame(PaymentStatus::Success, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('upi', $payment->payment_method);
        $this->assertStringStartsWith('pay_', $payment->gateway_payment_id);

        $gold = $payment->goldTransaction()->sole();
        $this->assertSame('0.400000', $gold->gold_grams);
        $this->assertSame(1250000, $gold->rate_per_gram_paise);
        $this->assertSame('22K', $gold->purity->value);
        $this->assertSame('2026-10-07', $gold->rate_date->toDateString());
        $this->assertSame('09:30:00', $gold->rate_time);
        $this->assertSame(CustomerPlanStatus::Active, $this->scheme->fresh()->status); // first payment activates the scheme
    }

    public function test_gateway_response_is_not_trusted_blindly(): void
    {
        $payment = $this->startedPayment();

        // A browser claiming success, with its own amount, gold and rate, but no valid gateway proof.
        $this->postCallback($payment, [
            'status' => 'success', 'amount' => '500000', 'gold_grams' => '100', 'gold_rate' => '1',
            'razorpay_order_id' => $payment->gateway_order_id, 'razorpay_payment_id' => 'pay_fake', 'razorpay_signature' => str_repeat('a', 64),
        ]);

        $payment->refresh();
        $this->assertNotSame(PaymentStatus::Success, $payment->status);
        $this->assertSame(0, GoldTransaction::query()->count());
    }

    public function test_invalid_signature_fails(): void
    {
        $payment = $this->startedPayment();
        $response = $this->gatewayPays($payment);
        $response['razorpay_signature'] = hash_hmac('sha256', $response['razorpay_order_id'].'|'.$response['razorpay_payment_id'], 'wrong-secret');

        $this->postCallback($payment, $response)->assertSessionHas('status', fn ($s) => str_contains($s, 'could not be completed'));

        $payment->refresh();
        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertStringContainsString('signature is invalid', $payment->failure_reason);
        $this->assertSame(0, GoldTransaction::query()->count());
    }

    public function test_incorrect_amount_fails(): void
    {
        $payment = $this->startedPayment();

        // Signed correctly, but the gateway's own record shows ₹1 was paid.
        $this->postCallback($payment, $this->gatewayPays($payment, 'captured', 100));

        $payment->refresh();
        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertStringContainsString('amount does not match', $payment->failure_reason);
        $this->assertSame(0, GoldTransaction::query()->count());
    }

    public function test_failed_payment_creates_no_gold_and_customer_can_retry(): void
    {
        $payment = $this->startedPayment();
        $this->postCallback($payment, $this->gatewayPays($payment, 'failed'));

        $payment->refresh();
        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertSame('Payment declined by the bank.', $payment->failure_reason);
        $this->assertSame(0, GoldTransaction::query()->count());
        $this->assertSame(CustomerPlanStatus::Pending, $this->scheme->fresh()->status);

        $this->get(route('customer.payments.show', $payment->reference))->assertSee('Payment Failed')->assertSee('No gold was credited')->assertSee('data-retry', false);

        $retry = $this->payInFull();
        $this->assertNotSame($payment->id, $retry->id);
        $this->assertSame(PaymentStatus::Success, $retry->status);
        $this->assertSame(1, GoldTransaction::query()->count());
    }

    public function test_cancelled_payment_creates_no_gold(): void
    {
        $payment = $this->startedPayment();

        $this->post(route('customer.payments.cancel', $payment->reference))->assertSessionHas('status', fn ($s) => str_contains($s, 'cancelled'));

        $this->assertSame(PaymentStatus::Cancelled, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->cancelled_at);
        $this->assertSame(0, GoldTransaction::query()->count());

        // A new attempt is allowed.
        $this->start()->assertRedirect();
        $this->assertSame(2, Payment::query()->count());
    }

    public function test_pending_gateway_state_creates_no_gold(): void
    {
        $payment = $this->startedPayment();

        // Authorised but not captured: money is not held yet.
        $this->postCallback($payment, $this->gatewayPays($payment, 'authorized'))->assertSessionHas('status', fn ($s) => str_contains($s, 'confirming'));

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertSame(0, GoldTransaction::query()->count());
        $this->get(route('customer.payments.show', $payment->reference))->assertSee('Payment Being Confirmed')->assertDontSee('data-receipt-link', false);
        $this->get(route('customer.payments.receipt', $payment->reference))->assertRedirect(route('customer.payments.show', $payment->reference));
    }

    // Idempotency ------------------------------------------------------------------------

    public function test_duplicate_callback_does_not_create_duplicate_gold(): void
    {
        $payment = $this->startedPayment();
        $response = $this->gatewayPays($payment);

        $this->postCallback($payment, $response);
        $this->postCallback($payment, $response);
        $this->postCallback($payment, $response);

        $this->assertSame(1, Payment::query()->successful()->count());
        $this->assertSame(1, GoldTransaction::query()->count());
        $this->assertSame('0.400000', GoldTransaction::query()->sole()->gold_grams);
        $this->get(route('customer.dashboard'))->assertSee('data-scheme-gold>0.400000 g', false);
    }

    public function test_webhook_and_callback_together_credit_gold_once(): void
    {
        $payment = $this->startedPayment();
        $response = $this->gatewayPays($payment);
        $body = $this->webhookBody($payment, $response['razorpay_payment_id']);

        $this->call('POST', route('payments.webhook', 'razorpay'), [], [], [], ['HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, self::WEBHOOK_SECRET), 'CONTENT_TYPE' => 'application/json'], $body)
            ->assertOk()->assertJson(['status' => 'processed']);
        $this->postCallback($payment, $response);
        $this->call('POST', route('payments.webhook', 'razorpay'), [], [], [], ['HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, self::WEBHOOK_SECRET), 'CONTENT_TYPE' => 'application/json'], $body)
            ->assertOk();

        $this->assertSame(PaymentStatus::Success, $payment->fresh()->status);
        $this->assertSame(1, GoldTransaction::query()->count());
        $this->assertSame('webhook', $payment->fresh()->metadata['verified_via']);
    }

    public function test_forged_webhook_is_rejected(): void
    {
        $payment = $this->startedPayment();
        $response = $this->gatewayPays($payment);
        $body = $this->webhookBody($payment, $response['razorpay_payment_id']);

        $this->call('POST', route('payments.webhook', 'razorpay'), [], [], [], ['HTTP_X_RAZORPAY_SIGNATURE' => 'forged', 'CONTENT_TYPE' => 'application/json'], $body)
            ->assertStatus(400);

        $this->assertSame(PaymentStatus::Created, $payment->fresh()->status);
        $this->assertSame(0, GoldTransaction::query()->count());
    }

    public function test_repeated_submission_reuses_the_same_payment(): void
    {
        $token = (string) Str::uuid();
        $this->start(null, $token);
        $this->start(null, $token);
        $this->start(); // new token, but an unfinished attempt for this installment exists

        $this->assertSame(1, Payment::query()->count());
        Http::assertSentCount(1); // one gateway order
    }

    // Historical rate (mandatory) ------------------------------------------------------------

    public function test_historical_gold_rate_is_never_recalculated(): void
    {
        // Day 1: 22K = ₹12,500/g; customer pays ₹5,000 → 0.400000 g.
        Carbon::setTestNow('2026-10-06 11:00:00');
        GoldRate::query()->whereDate('rate_date', '2026-10-07')->update(['status' => false]); // start from a clean rate history
        $this->scheme->update(['start_date' => '2026-10-06']);
        $this->rateToday(12500);
        $day1 = $this->payInFull();

        $gold = $day1->goldTransaction;
        $this->assertSame('0.400000', $gold->gold_grams);
        $this->assertSame(1250000, $gold->rate_per_gram_paise);

        // Day 2: 22K = ₹13,000/g.
        Carbon::setTestNow('2026-10-07 11:00:00');
        $this->rateToday(13000, '09:00:00');
        $this->assertSame('13000.00', GoldRate::latestFor('22K')->rate_per_gram);

        // The Day 1 transaction still shows ₹12,500/g and 0.400000 g — everywhere.
        $gold = GoldTransaction::query()->sole();
        $this->assertSame(1250000, $gold->rate_per_gram_paise);
        $this->assertSame('0.400000', $gold->gold_grams);
        $this->assertSame('2026-10-06', $gold->rate_date->toDateString());

        foreach ([
            route('customer.payments.show', $day1->reference),
            route('customer.payments.receipt', $day1->reference),
            route('customer.payments.index'),
        ] as $url) {
            $this->get($url)->assertOk()->assertSee('₹12,500')->assertSee('0.400000 g')->assertDontSee('₹13,000');
        }

        // Only the valuation uses today's rate: 0.4 g × ₹13,000 = ₹5,200. The gold held is unchanged.
        $this->assertSame('5200', $this->statValue('current-value'));
        $this->assertSame('0.400000 g', $this->stat('gold-accumulated'));
    }

    public function test_multiple_legitimate_installments_are_allowed(): void
    {
        $first = $this->payInFull();

        // Same month: the installment is already paid.
        $this->actingAs($this->customer, 'customer')->get(route('customer.schemes.payment', $this->scheme->scheme_number))
            ->assertSee('data-payment-blocked="not_due"', false)->assertSee('due from 01 Nov 2026');
        $this->start()->assertSessionHasErrors('payment');

        // Next month, at next month's rate.
        Carbon::setTestNow('2026-11-05 10:00:00');
        $this->rateToday(10000);
        $second = $this->payInFull();

        $this->assertSame(2, $second->installment_number);
        $this->assertSame('0.500000', $second->goldTransaction->gold_grams);
        $this->assertSame('0.400000', $first->goldTransaction->fresh()->gold_grams);
        $this->assertSame(2, Payment::query()->successful()->count());

        $this->assertSame('₹10,000', $this->stat('total-contributed'));
        $this->assertSame('0.900000 g', $this->stat('gold-accumulated'));
    }

    public function test_payment_requires_todays_gold_rate(): void
    {
        GoldRate::query()->update(['status' => false]);
        GoldRate::factory()->rate(12000)->on('2026-10-06')->create(); // yesterday only

        $this->actingAs($this->customer, 'customer')->get(route('customer.schemes.payment', $this->scheme->scheme_number))
            ->assertSee('data-payment-blocked="rate"', false);
        $this->start()->assertSessionHasErrors('payment');
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_closed_scheme_cannot_accept_payment(): void
    {
        $this->scheme->update(['status' => CustomerPlanStatus::Cancelled]);

        $this->start()->assertSessionHasErrors('payment');
        $this->assertSame(0, Payment::query()->count());
    }

    // Screens -----------------------------------------------------------------------------

    public function test_customer_sees_payment_history_detail_and_receipt(): void
    {
        $payment = $this->payInFull();

        $this->get(route('customer.payments.index'))->assertOk()
            ->assertSee($payment->reference)->assertSee($this->scheme->scheme_number)
            ->assertSee('₹5,000')->assertSee('Successful')->assertSee('₹12,500/g', false)->assertSee('0.400000 g');

        $this->get(route('customer.payments.show', $payment->reference))->assertOk()
            ->assertSee('Payment Successful')->assertSee('Gold Premium')->assertSee('0.400000 g')
            ->assertSee('22K')->assertSee('₹12,500/g', false)->assertSee($payment->gateway_payment_id)
            ->assertSee('UPI')->assertSee(route('customer.payments.receipt', $payment->reference), false);

        $this->get(route('customer.payments.receipt', $payment->reference))->assertOk()
            ->assertSee('Sri Sindhu Jewellery')->assertSee('Gold Saving Scheme')->assertSee('Priya Sundar')
            ->assertSee($this->scheme->scheme_number)->assertSee('₹5,000')->assertSee('₹12,500')
            ->assertSee('0.400000 g')->assertSee('22K')->assertSee($payment->gateway_payment_id)
            ->assertSee('Successful')->assertDontSee('GST')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->get(route('customer.schemes.show', $this->scheme->scheme_number))->assertOk()
            ->assertSee('data-scheme-payments', false)->assertSee('₹5,000')->assertSee('0.400000 g');
    }

    public function test_empty_states_are_honest(): void
    {
        $this->actingAs($this->customer, 'customer')->get(route('customer.payments.index'))->assertOk()->assertSee('data-empty-payments', false);

        $this->assertSame('₹0', $this->stat('total-contributed'));
        $this->assertSame('0.000000 g', $this->stat('gold-accumulated'));
        $this->assertSame('Awaiting first payment', $this->stat('current-value'));
    }

    public function test_customer_cannot_see_another_customers_payment(): void
    {
        $other = $this->approvedCustomer();
        $theirs = Payment::factory()->for($this->schemeFor($other), 'customerPlan')->create();

        $this->actingAs($this->customer, 'customer');
        foreach (['show', 'receipt', 'checkout'] as $action) {
            $this->get(route('customer.payments.'.$action, $theirs->reference))->assertNotFound();
        }
        $this->post(route('customer.payments.callback', $theirs->reference), [])->assertNotFound();
        $this->post(route('customer.payments.cancel', $theirs->reference))->assertNotFound();
        $this->get('/customer/payments/'.$theirs->id)->assertNotFound(); // ids are not accepted
        $this->get(route('customer.payments.index'))->assertDontSee($theirs->reference);

        $this->assertSame(PaymentStatus::Created, $theirs->fresh()->status);
    }

    public function test_no_gateway_secret_appears_in_responses_or_logs(): void
    {
        Log::spy();
        $payment = $this->payInFull();

        $pages = collect([
            route('customer.payments.index'), route('customer.payments.show', $payment->reference),
            route('customer.payments.receipt', $payment->reference), route('customer.dashboard'),
            route('customer.schemes.show', $this->scheme->scheme_number),
        ])->map(fn ($url) => $this->get($url)->getContent())->implode('');

        foreach ([self::GATEWAY_SECRET, self::WEBHOOK_SECRET, $payment->gateway_signature] as $secret) {
            $this->assertStringNotContainsString($secret, $pages);
            $this->assertStringNotContainsString($secret, $payment->fresh()->toJson());
        }
        foreach (['info', 'warning', 'error', 'debug', 'notice', 'critical'] as $level) {
            Log::shouldNotHaveReceived($level);
        }
    }

    public function test_simulator_is_verified_server_side_and_refused_in_production(): void
    {
        config(['payments.gateway' => 'simulator']);
        $payment = $this->startedPayment();
        $this->assertSame('simulator', $payment->gateway);

        $this->get(route('customer.payments.checkout', $payment->reference))->assertSee('Development payment simulator');

        // A forged simulator callback (no gateway-side record / bad signature) is refused.
        $this->post(route('customer.payments.callback', $payment->reference), ['gateway_order_id' => $payment->gateway_order_id, 'gateway_payment_id' => 'sim_pay_x', 'gateway_signature' => 'x']);
        $this->assertNotSame(PaymentStatus::Success, $payment->fresh()->status);

        $second = $this->startedPayment();
        $this->post(route('customer.payments.simulate', $second->reference), ['outcome' => 'success'])->assertRedirect(route('customer.payments.show', $second->reference));
        $this->assertSame(PaymentStatus::Success, $second->fresh()->status);
        $this->assertSame('0.400000', $second->goldTransaction->gold_grams);

        $this->app->detectEnvironment(fn () => 'production');
        $this->assertFalse(app(\App\Payments\SimulatorGateway::class)->isConfigured());
        $this->expectException(\LogicException::class);
        app(\App\Payments\PaymentGateway::class);
    }

    public function test_payment_routes_are_csrf_protected_and_rate_limited(): void
    {
        foreach (['customer.schemes.payment.store', 'customer.payments.callback', 'customer.payments.cancel', 'customer.payments.simulate'] as $name) {
            $middleware = app('router')->getRoutes()->getByName($name)->gatherMiddleware();
            $this->assertContains('web', $middleware, $name);
            $this->assertContains('customer', $middleware, $name);
            $this->assertNotEmpty(array_filter($middleware, fn ($m) => str_starts_with($m, 'throttle:')), $name);
        }
        $this->assertContains(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, app(\Illuminate\Contracts\Http\Kernel::class)->getMiddlewareGroups()['web']);
    }

    // Helpers -------------------------------------------------------------------------------

    private function stat(string $key): string
    {
        preg_match('/data-stat="'.$key.'".*?data-stat-value>(.*?)<\/p>/s', $this->actingAs($this->customer, 'customer')->get(route('customer.dashboard'))->getContent(), $m);

        return trim($m[1] ?? '');
    }

    private function statValue(string $key): string
    {
        return preg_replace('/[^0-9.]/', '', $this->stat($key));
    }
}
