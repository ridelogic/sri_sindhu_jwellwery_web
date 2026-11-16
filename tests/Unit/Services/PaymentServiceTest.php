<?php

namespace Tests\Unit\Services;

use App\Enums\CustomerPlanStatus;
use App\Enums\PaymentEventType;
use App\Enums\PaymentStatus;
use App\Models\CustomerPlan;
use App\Models\GoldRate;
use App\Models\GoldTransaction;
use App\Models\Payment;
use App\Models\User;
use App\Payments\GatewayVerification;
use App\Services\PaymentService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Tests\Concerns\FakesPaymentGateway;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use FakesPaymentGateway;
    use RefreshDatabase;

    private PaymentService $payments;

    private User $customer;

    private CustomerPlan $scheme;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-07 11:00:00');
        $this->fakeGateway();
        $this->payments = app(PaymentService::class);
        $this->customer = $this->approvedCustomer();
        $this->scheme = $this->schemeFor($this->customer);
        $this->rateToday(12500);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function initiate(): Payment
    {
        return $this->payments->initiate($this->customer, $this->scheme, (string) Str::uuid());
    }

    private function verified(Payment $payment, array $overrides = []): GatewayVerification
    {
        return new GatewayVerification(...$overrides + [
            'status' => PaymentStatus::Success,
            'orderId' => $payment->gateway_order_id,
            'paymentId' => 'pay_'.Str::random(14),
            'amountPaise' => $payment->amount_paise,
            'currency' => 'INR',
            'method' => 'card',
            'paidAt' => now(),
            'signature' => 'sig',
            'source' => 'webhook',
        ]);
    }

    public function test_initiate_creates_an_order_for_the_customer_plan_amount(): void
    {
        $payment = $this->initiate();

        $this->assertSame(PaymentStatus::Created, $payment->status);
        $this->assertSame(500000, $payment->amount_paise);
        $this->assertNotNull($payment->gateway_order_id);
        $this->assertSame(PaymentEventType::Initiated, $payment->events()->sole()->event);
    }

    public function test_complete_records_payment_and_gold_atomically(): void
    {
        $payment = $this->payments->complete($this->initiate(), $this->verified($this->initiate()->fresh()));

        $this->assertSame(PaymentStatus::Success, $payment->status);
        $gold = GoldTransaction::query()->sole();
        $this->assertSame($payment->id, $gold->payment_id);
        $this->assertSame($payment->user_id, $gold->user_id);
        $this->assertSame($payment->customer_plan_id, $gold->customer_plan_id);
        $this->assertSame('0.400000', $gold->gold_grams);
        $this->assertSame(1250000, $gold->rate_per_gram_paise);
        $this->assertSame(CustomerPlanStatus::Active, $this->scheme->fresh()->status);
        $this->assertSame(PaymentEventType::Succeeded, $payment->events()->first()->event);
    }

    public function test_completion_is_idempotent(): void
    {
        $payment = $this->initiate();
        $verification = $this->verified($payment);

        $this->payments->complete($payment, $verification);
        $this->payments->complete($payment->fresh(), $verification);
        $this->payments->complete($payment, $verification); // stale model instance too

        $this->assertSame(1, Payment::query()->successful()->count());
        $this->assertSame(1, GoldTransaction::query()->count());
    }

    public function test_duplicate_gateway_payment_id_is_rejected(): void
    {
        $first = $this->initiate();
        $this->payments->complete($first, $this->verified($first, ['paymentId' => 'pay_SAMEID']));

        // A second payment claiming the same gateway payment id.
        Carbon::setTestNow('2026-11-02 11:00:00');
        $this->rateToday(12500);
        $second = $this->initiate();
        $result = $this->payments->complete($second, $this->verified($second, ['paymentId' => 'pay_SAMEID']));

        $this->assertSame(PaymentStatus::Failed, $result->status);
        $this->assertStringContainsString('already recorded', $result->failure_reason);
        $this->assertSame(1, GoldTransaction::query()->count());
    }

    public function test_database_constraints_back_up_the_service(): void
    {
        $payment = $this->initiate();
        $this->payments->complete($payment, $this->verified($payment, ['paymentId' => 'pay_UNIQUE1']));

        // A second ledger row for the same payment is refused by the database itself.
        try {
            DB::table('gold_transactions')->insert([
                'user_id' => $payment->user_id, 'customer_plan_id' => $payment->customer_plan_id, 'payment_id' => $payment->id,
                'transaction_type' => 'credit', 'amount_paise' => 500000, 'rate_per_gram_paise' => 1250000, 'gold_grams' => '0.400000',
                'purity' => '22K', 'rate_date' => '2026-10-07', 'transaction_date' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->fail('Duplicate gold transaction was accepted.');
        } catch (UniqueConstraintViolationException) {
            $this->addToAssertionCount(1);
        }

        // And the same gateway payment id cannot be stored twice.
        $other = Payment::factory()->for($this->scheme, 'customerPlan')->create();
        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('payments')->where('id', $other->id)->update(['gateway_payment_id' => 'pay_UNIQUE1']);
    }

    public function test_amount_or_currency_mismatch_never_credits_gold(): void
    {
        foreach ([['amountPaise' => 499999], ['currency' => 'USD'], ['orderId' => 'order_other']] as $overrides) {
            $payment = Payment::factory()->for($this->scheme, 'customerPlan')->create();
            $result = $this->payments->complete($payment, $this->verified($payment, $overrides));

            $this->assertSame(PaymentStatus::Failed, $result->status);
            $this->assertSame(PaymentEventType::VerificationFailed, $result->events()->first()->event);
        }
        $this->assertSame(0, GoldTransaction::query()->count());
    }

    public function test_rollback_occurs_if_gold_transaction_creation_fails(): void
    {
        $payment = $this->initiate();
        GoldTransaction::creating(fn () => throw new RuntimeException('disk full'));

        try {
            $this->payments->complete($payment, $this->verified($payment));
            $this->fail('Expected the failure to propagate.');
        } catch (RuntimeException $e) {
            $this->assertSame('disk full', $e->getMessage());
        }

        $payment->refresh();
        $this->assertSame(PaymentStatus::Created, $payment->status);   // not left half-successful
        $this->assertNull($payment->paid_at);
        $this->assertNull($payment->gateway_payment_id);
        $this->assertSame(0, GoldTransaction::query()->count());
        $this->assertSame(CustomerPlanStatus::Pending, $this->scheme->fresh()->status);
        $this->assertFalse($payment->events()->where('event', PaymentEventType::Succeeded->value)->exists());
    }

    public function test_rate_is_taken_at_payment_time_not_now(): void
    {
        $payment = $this->initiate();
        $paidAt = now();

        // A later rate update (same day) must not affect a payment made before it.
        Carbon::setTestNow('2026-10-07 15:00:00');
        GoldRate::factory()->rate(13000)->on('2026-10-07', '14:00:00')->create();

        $this->payments->complete($payment, $this->verified($payment, ['paidAt' => $paidAt]));

        $this->assertSame(1250000, GoldTransaction::query()->sole()->rate_per_gram_paise);
        $this->assertSame('0.400000', GoldTransaction::query()->sole()->gold_grams);
    }

    public function test_without_an_applicable_rate_payment_is_held_not_guessed(): void
    {
        $payment = $this->initiate();
        GoldRate::query()->update(['status' => false]);

        $result = $this->payments->complete($payment, $this->verified($payment));

        $this->assertSame(PaymentStatus::Pending, $result->status);
        $this->assertStringContainsString('applicable gold rate', $result->failure_reason);
        $this->assertSame(0, GoldTransaction::query()->count());
    }

    public function test_failed_and_pending_verifications_create_no_gold(): void
    {
        $payment = $this->initiate();

        $this->payments->apply($payment, $this->verified($payment, ['status' => PaymentStatus::Pending]), null);
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);

        $this->payments->apply($payment, $this->verified($payment, ['status' => PaymentStatus::Failed, 'failureReason' => 'Declined']), null);
        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);

        $this->assertSame(0, GoldTransaction::query()->count());
    }

    public function test_a_success_is_final(): void
    {
        $payment = $this->initiate();
        $this->payments->complete($payment, $this->verified($payment));

        // Later "failed" or "cancel" reports cannot undo a verified success.
        $this->payments->apply($payment, $this->verified($payment, ['status' => PaymentStatus::Failed]), null);
        $this->payments->cancel($payment, $this->customer);

        $this->assertSame(PaymentStatus::Success, $payment->fresh()->status);
        $this->assertSame(1, GoldTransaction::query()->count());
    }

    public function test_financial_records_are_immutable(): void
    {
        $payment = $this->initiate();
        $this->payments->complete($payment, $this->verified($payment));
        $payment->refresh();
        $gold = GoldTransaction::query()->sole();

        $attempts = [
            'payment amount' => fn () => $payment->update(['amount_paise' => 1]),
            'payment status' => fn () => $payment->update(['status' => PaymentStatus::Failed]),
            'payment delete' => fn () => $payment->delete(),
            'gold grams' => fn () => $gold->update(['gold_grams' => '9.000000']),
            'gold rate snapshot' => fn () => $gold->update(['rate_per_gram_paise' => 1300000]),
            'gold delete' => fn () => $gold->delete(),
            'rate history' => fn () => GoldRate::query()->firstOrFail()->update(['rate_per_gram' => 13000]),
        ];

        foreach ($attempts as $label => $attempt) {
            try {
                $attempt();
                $this->fail("$label was allowed");
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(500000, $payment->fresh()->amount_paise);
        $this->assertSame('0.400000', $gold->fresh()->gold_grams);
        $this->assertSame(1250000, $gold->fresh()->rate_per_gram_paise);
    }

    public function test_sensitive_payment_fields_are_hidden_from_serialisation(): void
    {
        $payment = $this->initiate();
        $this->payments->complete($payment, $this->verified($payment, ['signature' => 'secret-signature-value']));

        $json = $payment->fresh()->toJson();
        $this->assertStringNotContainsString('secret-signature-value', $json);
        $this->assertStringNotContainsString('idempotency_key', $json);
    }

    public function test_final_installment_completes_the_scheme(): void
    {
        $scheme = $this->schemeFor($this->customer, '1000', 2);

        $first = $this->payments->initiate($this->customer, $scheme, (string) Str::uuid());
        $this->payments->complete($first, $this->verified($first));
        $this->assertSame(CustomerPlanStatus::Active, $scheme->fresh()->status);

        Carbon::setTestNow('2026-11-03 11:00:00');
        $this->rateToday(12500);
        $second = $this->payments->initiate($this->customer, $scheme->fresh(), (string) Str::uuid());
        $this->payments->complete($second, $this->verified($second));

        $this->assertSame(CustomerPlanStatus::Completed, $scheme->fresh()->status);
        $this->assertSame('complete', $this->payments->eligibility($this->customer, $scheme->fresh())['reason']);
    }
}
