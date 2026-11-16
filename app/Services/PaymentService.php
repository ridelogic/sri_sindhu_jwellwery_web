<?php

namespace App\Services;

use App\Enums\CustomerPlanStatus;
use App\Enums\GoldTransactionType;
use App\Enums\KycStatus;
use App\Enums\PaymentEventType;
use App\Enums\PaymentStatus;
use App\Models\CustomerPlan;
use App\Models\GoldRate;
use App\Models\GoldTransaction;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\NotificationMessages;
use App\Payments\GatewayVerification;
use App\Payments\GoldRateUnavailableException;
use App\Payments\PaymentGateway;
use App\Payments\PaymentGatewayException;
use App\Payments\PaymentNotAllowedException;
use App\Payments\PaymentVerificationException;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Scheme installment payments.
 *
 *  1. initiate()   — eligibility (ownership, approved KYC, open scheme,
 *                    installment due, gateway, today's rate) → payment row
 *                    (amount from customer_plans) → gateway order.
 *  2. handleCallback() / handleWebhook() — the gateway response is verified
 *                    server-side by the adapter; nothing from the browser is trusted.
 *  3. complete()   — ONE database transaction: lock the payment, re-check it,
 *                    match amount/currency/order, pick the rate in effect at
 *                    the payment time, calculate grams exactly, mark the
 *                    payment successful and write the gold ledger row.
 *
 * Idempotency: a successful payment is final and returned as-is; the gold
 * ledger has a UNIQUE payment_id and payments a UNIQUE gateway_payment_id, so
 * repeated callbacks or webhooks can never credit gold twice.
 */
class PaymentService
{
    public const KYC_MESSAGE = 'Please complete and get your KYC approved before making a payment.';

    public function __construct(private GoldCalculationService $gold) {}

    public function gateway(): PaymentGateway
    {
        return app(PaymentGateway::class);
    }

    // Installments --------------------------------------------------------------------

    /**
     * Installment position for a scheme. One installment becomes due per
     * calendar month from the start month; missed months can be caught up,
     * but installments cannot be paid ahead of their month.
     *
     * The number of installments is the scheme's own snapshot
     * (installments_total; for every pre-existing scheme = duration_months).
     * Open-ended schemes (unlimited tenure, total = null) never run out.
     *
     * @return array{paid: int, due: int, total: ?int, next_number: ?int, next_due_date: ?Carbon}
     */
    public function installments(CustomerPlan $scheme, ?Carbon $at = null): array
    {
        $at ??= now();
        $frequency = $scheme->contributionFrequency();
        $total = $scheme->installments_total;
        $paid = $scheme->payments()->successful()->count();

        // One installment per period (calendar month for every existing scheme). With no
        // schedule (Unlimited frequency) a contribution can always be made while the scheme is open.
        $elapsed = $frequency->isScheduled() ? $frequency->periodsElapsed($scheme->start_date, $at) : $paid + 1;

        $next = $total === null || $paid < $total ? $paid + 1 : null;

        return [
            'paid' => $paid,
            'due' => max(0, $total === null ? $elapsed : min($total, $elapsed)),
            'total' => $total,
            'next_number' => $next,
            'next_due_date' => $next === null || ! $frequency->isScheduled() ? null : $frequency->periodStart($scheme->start_date, $paid),
        ];
    }

    /**
     * The amount to charge for the next installment, in paise, or null when it is
     * outside the scheme's own minimum / maximum (snapshotted at enrolment).
     * Fixed-amount schemes (every pre-existing scheme) always charge their amount.
     */
    public function amountFor(CustomerPlan $scheme, ?int $requestedPaise): ?int
    {
        $min = Money::toPaise($scheme->minimumAmount());

        if ($scheme->isFixedAmount() || $requestedPaise === null) {
            return $min;
        }

        $max = $scheme->hasUnlimitedMaximum() ? null : Money::toPaise($scheme->maximum_amount);

        return $requestedPaise >= $min && ($max === null || $requestedPaise <= $max) ? $requestedPaise : null;
    }

    /**
     * Can this customer pay the next installment of this scheme now?
     *
     * @return array{allowed: bool, reason: ?string, message: ?string, installments: array, amount_paise: int, rate: ?GoldRate}
     */
    public function eligibility(User $customer, CustomerPlan $scheme): array
    {
        $installments = $this->installments($scheme);
        $rate = $this->todaysRate();
        $result = fn (?string $reason = null, ?string $message = null) => [
            'allowed' => $reason === null,
            'reason' => $reason,
            'message' => $message,
            'installments' => $installments,
            'amount_paise' => Money::toPaise($scheme->minimumAmount()), // the customer's agreed amount, never the plan's current price
            'amount_max_paise' => $scheme->hasUnlimitedMaximum() ? null : Money::toPaise($scheme->maximum_amount),
            'fixed_amount' => $scheme->isFixedAmount(),
            'credits_gold' => $scheme->creditsGold(),
            'rate' => $rate,
        ];

        if ($customer->isAdmin() || $scheme->user_id !== $customer->id) {
            return $result('ownership', 'This scheme is not available.');
        }

        if ($customer->kycProfile()->value('kyc_status') !== KycStatus::Verified) {
            return $result('kyc', self::KYC_MESSAGE);
        }

        if ($installments['next_number'] === null) {
            return $result('complete', 'All installments for this scheme have been paid. Thank you!');
        }

        if (! in_array($scheme->status, CustomerPlanStatus::open(), true)) {
            return $result('scheme', 'This scheme is '.strtolower($scheme->status->label()).' and cannot accept payments.');
        }

        if ($installments['paid'] >= $installments['due']) {
            $period = $scheme->contributionFrequency()->period() ?? 'period';

            return $result('not_due', 'This '.$period.'\'s installment has been paid. Installment '.$installments['next_number'].' is due from '.$installments['next_due_date']->format('d M Y').'.');
        }

        // Without a schedule, contributions are accepted up to the maturity date (if any).
        if (! $scheme->contributionFrequency()->isScheduled() && $scheme->maturity_date && today()->greaterThan($scheme->maturity_date)) {
            return $result('matured', 'This scheme reached its maturity date on '.$scheme->maturity_date->format('d M Y').' and no longer accepts contributions.');
        }

        if (! $this->gateway()->isConfigured()) {
            return $result('gateway', 'Online payments are not available at the moment. Please contact our team to pay your installment.');
        }

        // Only gold-crediting schemes need today's rate; Money / Anyone schemes record money only.
        if ($rate === null && $scheme->creditsGold()) {
            return $result('rate', 'Today\'s '.$this->gold->purity()->value.' gold rate has not been published yet. Please try again later.');
        }

        return $result();
    }

    // Initiation -------------------------------------------------------------------------

    /**
     * Start (or resume) a payment for the scheme's next installment.
     *
     * @throws PaymentNotAllowedException
     * @throws PaymentGatewayException
     */
    public function initiate(User $customer, CustomerPlan $scheme, string $idempotencyKey, ?int $amountPaise = null): Payment
    {
        // Same browser submission again (double click / refresh): same payment.
        $existing = Payment::query()->where('user_id', $customer->id)->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        [$payment, $reused] = DB::transaction(function () use ($customer, $scheme, $idempotencyKey, $amountPaise) {
            /** @var CustomerPlan $locked */
            $locked = CustomerPlan::query()->whereKey($scheme->id)->lockForUpdate()->firstOrFail();

            $eligibility = $this->eligibility($customer, $locked);
            if (! $eligibility['allowed']) {
                throw new PaymentNotAllowedException($eligibility['message'], $eligibility['reason']);
            }

            // The amount is checked against the scheme's own limits — never trusted from the browser.
            $amount = $this->amountFor($locked, $amountPaise);
            if ($amount === null) {
                throw new PaymentNotAllowedException($locked->hasUnlimitedMaximum()
                    ? 'Please enter an amount of at least '.Money::inr($locked->minimumAmount()).'.'
                    : 'Please enter an amount between '.Money::inr($locked->minimumAmount()).' and '.Money::inr($locked->maximum_amount).'.', 'amount');
            }

            $number = $eligibility['installments']['next_number'];

            // An unfinished attempt for this installment (and amount) is resumed rather than duplicated.
            $open = $locked->payments()
                ->whereIn('status', PaymentStatus::openValues())
                ->where('installment_number', $number)
                ->where('amount_paise', $amount)
                ->whereNotNull('gateway_order_id')
                ->where('initiated_at', '>=', now()->subMinutes((int) config('payments.reuse_attempt_minutes', 30)))
                ->latest('id')
                ->first();
            if ($open) {
                return [$open, true];
            }

            $payment = Payment::query()->create([
                'user_id' => $customer->id,
                'customer_plan_id' => $locked->id,
                'installment_number' => $number,
                'amount_paise' => $amount,
                'currency' => config('payments.currency', 'INR'),
                'status' => PaymentStatus::Created,
                'gateway' => $this->gateway()->name(),
                'idempotency_key' => $idempotencyKey,
            ]);
            $this->event($payment, PaymentEventType::Initiated, $customer);

            return [$payment, false];
        });

        if ($reused) {
            return $payment;
        }

        // Network call outside the database transaction.
        try {
            $order = $this->gateway()->createOrder($payment);
        } catch (PaymentGatewayException $e) {
            $this->markFailed($payment, $e->getMessage(), $customer);

            throw $e;
        }

        if ($order->amountPaise !== $payment->amount_paise || $order->currency !== $payment->currency) {
            $this->markFailed($payment, 'The payment gateway returned an unexpected order.', $customer);

            throw new PaymentGatewayException('The payment could not be started. Please try again.');
        }

        $payment->forceFill(['gateway_order_id' => $order->orderId])->save();

        return $payment;
    }

    // Gateway responses ------------------------------------------------------------------

    /**
     * Browser callback from checkout. Verified server-side by the gateway adapter.
     *
     * @param  array<string, mixed>  $response
     */
    public function handleCallback(Payment $payment, array $response, ?User $actor = null): Payment
    {
        if ($payment->isSuccessful()) {
            return $payment; // already credited — repeat callbacks change nothing
        }

        try {
            $verification = $this->gateway()->verifyPayment($payment, $response);
        } catch (PaymentVerificationException $e) {
            return $this->verificationFailed($payment, $e->getMessage(), $actor);
        } catch (PaymentGatewayException) {
            return $this->markPending($payment, 'We could not confirm this payment with the gateway yet.', $actor);
        }

        return $this->apply($payment, $verification, $actor);
    }

    /**
     * Server-to-server webhook (already authenticated by the adapter).
     */
    public function handleWebhook(GatewayVerification $verification): ?Payment
    {
        $payment = Payment::query()->where('gateway_order_id', $verification->orderId)->first();

        return $payment ? $this->apply($payment, $verification, null) : null;
    }

    public function apply(Payment $payment, GatewayVerification $verification, ?User $actor): Payment
    {
        return match ($verification->status) {
            PaymentStatus::Success => $this->complete($payment, $verification, $actor),
            PaymentStatus::Failed => $this->markFailed($payment, $verification->failureReason ?? 'The payment was declined.', $actor),
            default => $this->markPending($payment, null, $actor),
        };
    }

    /**
     * The financial step — all or nothing.
     */
    public function complete(Payment $payment, GatewayVerification $verification, ?User $actor = null): Payment
    {
        try {
            return DB::transaction(function () use ($payment, $verification, $actor) {
                /** @var Payment $locked */
                $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

                if ($locked->isSuccessful()) {
                    return $locked; // idempotent
                }

                $this->assertMatches($locked, $verification);

                $paidAt = $this->paidAt($locked, $verification);

                // Gold-output schemes (every pre-existing scheme) buy gold at the rate in effect
                // at payment time. Money / Anyone schemes record the money only — no gold.
                $creditsGold = CustomerPlan::query()->findOrFail($locked->customer_plan_id)->creditsGold();
                if ($creditsGold) {
                    $rate = $this->gold->rateAt($paidAt) ?? throw new GoldRateUnavailableException;
                    $ratePaise = $this->gold->ratePaise($rate);
                    $grams = $this->gold->gramsFor($locked->amount_paise, $ratePaise);
                }

                $locked->forceFill([
                    'status' => PaymentStatus::Success,
                    'paid_at' => $paidAt,
                    'gateway_payment_id' => $verification->paymentId,
                    'gateway_signature' => $verification->signature ?? $locked->gateway_signature,
                    'payment_method' => $verification->method,
                    'failure_reason' => null,
                    'failed_at' => null,
                    'cancelled_at' => null,
                    'metadata' => ['verified_via' => $verification->source],
                ])->save();

                $gold = ! $creditsGold ? null : GoldTransaction::query()->create([
                    'user_id' => $locked->user_id,
                    'customer_plan_id' => $locked->customer_plan_id,
                    'payment_id' => $locked->id,
                    'gold_rate_id' => $rate->id,
                    'transaction_type' => GoldTransactionType::Credit,
                    'amount_paise' => $locked->amount_paise,
                    'rate_per_gram_paise' => $ratePaise,           // snapshot — never re-read from gold_rates
                    'gold_grams' => $grams,
                    'purity' => $rate->purity,
                    'rate_date' => $rate->rate_date->toDateString(),
                    'rate_time' => $rate->rate_time,
                    'transaction_date' => $paidAt,
                    'description' => 'Installment '.$locked->installment_number.' — '.$locked->reference,
                ]);

                $this->advanceScheme($locked->customer_plan_id);
                $this->event($locked, PaymentEventType::Succeeded, $actor);

                // Gold-credited notification from the stored snapshot (after commit only).
                if ($gold) {
                    $this->notifications()->afterCommit(fn () => $this->notifications()->notify(
                        $locked->user()->firstOrFail(),
                        NotificationMessages::goldCredited($gold->fresh(['payment', 'customerPlan'])),
                    ));
                }

                return $locked;
            });
        } catch (PaymentVerificationException $e) {
            return $this->verificationFailed($payment, $e->getMessage(), $actor);
        } catch (UniqueConstraintViolationException) {
            // A concurrent completion won the race, or the gateway payment id is already used.
            $fresh = $payment->fresh();

            return $fresh->isSuccessful() ? $fresh : $this->verificationFailed($fresh, 'This gateway payment is already recorded.', $actor);
        } catch (GoldRateUnavailableException) {
            // Money was received but no rate applies: never guess a rate — hold for review.
            return $this->markPending($payment, 'Payment received. Gold will be credited once the applicable gold rate is confirmed.', $actor);
        }
    }

    /** The customer left checkout. Only unfinished attempts with no gateway payment can be cancelled. */
    public function cancel(Payment $payment, User $actor, bool $gatewayReportedFailure = false): Payment
    {
        return DB::transaction(function () use ($payment, $actor, $gatewayReportedFailure) {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PaymentStatus::Created || $locked->gateway_payment_id !== null) {
                return $locked;
            }

            if ($gatewayReportedFailure) {
                $locked->forceFill(['status' => PaymentStatus::Failed, 'failed_at' => now(), 'failure_reason' => 'The payment was not completed.'])->save();
                $this->event($locked, PaymentEventType::Failed, $actor, 'Reported by checkout');
            } else {
                $locked->forceFill(['status' => PaymentStatus::Cancelled, 'cancelled_at' => now()])->save();
                $this->event($locked, PaymentEventType::Cancelled, $actor);
            }

            return $locked;
        });
    }

    // Internals ---------------------------------------------------------------------------

    /** The active rate in effect now, only if it was published today. */
    public function todaysRate(): ?GoldRate
    {
        $rate = $this->gold->rateAt(now());

        return $rate && $rate->rate_date->isSameDay(today()) ? $rate : null;
    }

    private function assertMatches(Payment $payment, GatewayVerification $verification): void
    {
        if ($verification->paymentId === null || $verification->paymentId === '') {
            throw new PaymentVerificationException('The gateway did not return a payment reference.');
        }
        if (! hash_equals((string) $payment->gateway_order_id, $verification->orderId)) {
            throw new PaymentVerificationException('The payment does not belong to this order.');
        }
        if ($verification->amountPaise !== $payment->amount_paise) {
            throw new PaymentVerificationException('The paid amount does not match the installment amount.');
        }
        if ($verification->currency !== $payment->currency) {
            throw new PaymentVerificationException('The payment currency does not match.');
        }
        if (Payment::query()->where('gateway_payment_id', $verification->paymentId)->whereKeyNot($payment->id)->exists()) {
            throw new PaymentVerificationException('This gateway payment is already recorded.');
        }
    }

    /** Gateway time of payment, kept within [initiated_at, now] to absorb clock skew. */
    private function paidAt(Payment $payment, GatewayVerification $verification): Carbon
    {
        $now = now();
        $paidAt = $verification->paidAt?->copy()->setTimezone(config('app.timezone')) ?? $now;
        $floor = $payment->initiated_at ?? $payment->created_at;

        if ($paidAt->greaterThan($now)) {
            return $now;
        }

        return $floor && $paidAt->lessThan($floor) ? $floor->copy() : $paidAt;
    }

    /** First payment activates a pending scheme; the final installment completes it. */
    private function advanceScheme(int $schemeId): void
    {
        $scheme = CustomerPlan::query()->whereKey($schemeId)->lockForUpdate()->firstOrFail();
        $paid = $scheme->payments()->successful()->count();

        $status = match (true) {
            $scheme->installments_total !== null && $paid >= $scheme->installments_total => CustomerPlanStatus::Completed,
            $scheme->status === CustomerPlanStatus::Pending => CustomerPlanStatus::Active,
            default => $scheme->status,
        };

        if ($status !== $scheme->status) {
            $scheme->forceFill(['status' => $status])->save();
        }
    }

    private function markFailed(Payment $payment, string $reason, ?User $actor): Payment
    {
        return $this->transition($payment, PaymentStatus::Failed, PaymentEventType::Failed, $reason, $actor);
    }

    private function markPending(Payment $payment, ?string $reason, ?User $actor): Payment
    {
        return $this->transition($payment, PaymentStatus::Pending, PaymentEventType::Pending, $reason, $actor);
    }

    private function verificationFailed(Payment $payment, string $reason, ?User $actor): Payment
    {
        return $this->transition($payment, PaymentStatus::Failed, PaymentEventType::VerificationFailed, 'Payment could not be verified: '.$reason, $actor);
    }

    /** Status change for a payment that is not (and will not become) successful here. */
    private function transition(Payment $payment, PaymentStatus $status, PaymentEventType $event, ?string $reason, ?User $actor): Payment
    {
        return DB::transaction(function () use ($payment, $status, $event, $reason, $actor) {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->isSuccessful()) {
                return $locked; // a success is final
            }

            $locked->forceFill(array_filter([
                'status' => $status,
                'failed_at' => $status === PaymentStatus::Failed ? now() : null,
                'failure_reason' => $reason === null ? null : mb_substr($reason, 0, 255),
            ], fn ($value) => $value !== null))->save();

            $this->event($locked, $event, $actor, $reason === null ? null : mb_substr($reason, 0, 255));

            return $locked;
        });
    }

    /**
     * Audit the event and notify the customer after commit. Keyed by the event
     * id: a repeated callback/webhook creates no event, so no notification.
     */
    private function event(Payment $payment, PaymentEventType $type, ?User $actor, ?string $note = null): void
    {
        $event = $payment->events()->create(['actor_id' => $actor?->id, 'event' => $type, 'note' => $note]);

        $this->notifications()->afterCommit(function () use ($payment, $event) {
            $fresh = $payment->fresh(['customerPlan', 'goldTransaction', 'user']);
            $message = NotificationMessages::payment($fresh, $event);

            if ($message) {
                $this->notifications()->notify($fresh->user, $message);
            }
        });
    }

    private function notifications(): NotificationService
    {
        return app(NotificationService::class);
    }
}
