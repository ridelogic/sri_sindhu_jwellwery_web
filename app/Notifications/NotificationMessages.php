<?php

namespace App\Notifications;

use App\Enums\GoldPurity;
use App\Enums\KycAuditAction;
use App\Enums\NotificationType;
use App\Enums\PaymentEventType;
use App\Models\GoldRate;
use App\Models\GoldTransaction;
use App\Models\KycAuditLog;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds customer notifications from STORED records only. Payment and gold
 * figures come from the payment and its permanent gold-transaction snapshot —
 * never from the current gold rate.
 */
final class NotificationMessages
{
    /** @param  Collection<int, GoldRate>  $rates  the rows created by one rate update */
    public static function goldRateUpdated(Collection $rates): NotificationMessage
    {
        $byPurity = $rates->keyBy(fn (GoldRate $rate) => $rate->purity->value);
        $first = $rates->first();
        $moment = $first->effectiveAtMoment();
        $updated = $moment->format('d M Y, g:i A');

        $lines = collect(GoldPurity::cases())
            ->filter(fn ($purity) => $byPurity->has($purity->value))
            ->mapWithKeys(fn ($purity) => [$purity->value.' Gold' => Money::inr($byPurity[$purity->value]->rate_per_gram).'/g']);

        return new NotificationMessage(
            type: NotificationType::GoldRateUpdated,
            title: $first->rate_date->isToday() ? 'Today\'s Gold Rate Updated' : 'Gold Rate Updated',
            message: $lines->map(fn ($value, $label) => "$label $value")->implode(' · '),
            key: 'gold_rate:'.$rates->pluck('id')->sort()->implode('-'),
            details: $lines->all() + ['Updated' => $updated],
            data: [
                'rate_date' => $first->rate_date->toDateString(),
                'rate_time' => $first->rate_time,
                'rate_22k' => $byPurity->get(GoldPurity::K22->value)?->rate_per_gram,
                'rate_24k' => $byPurity->get(GoldPurity::K24->value)?->rate_per_gram,
            ],
            url: route('gold.rate', absolute: false),
        );
    }

    public static function kyc(KycAuditLog $log): NotificationMessage
    {
        $url = route('customer.kyc', absolute: false);
        $key = 'kyc_audit:'.$log->id;

        return match ($log->action) {
            KycAuditAction::Submitted => new NotificationMessage(
                NotificationType::KycSubmitted, 'KYC Submitted', 'Your KYC has been submitted for review.', $key, url: $url,
            ),
            KycAuditAction::Approved => new NotificationMessage(
                NotificationType::KycApproved, 'KYC Approved', 'Your KYC has been approved successfully.', $key, url: $url,
            ),
            // Only the customer-facing rejection reason — the same text shown on the KYC page.
            KycAuditAction::Rejected => new NotificationMessage(
                NotificationType::KycRejected, 'KYC Requires Update', 'Your KYC needs an update. Please review the reason and resubmit.', $key,
                details: array_filter(['Reason' => (string) $log->note]), url: $url,
            ),
            KycAuditAction::EnteredByAdmin => throw new \LogicException('Admin KYC drafts do not notify the customer.'),
        };
    }

    /** Payment lifecycle notifications, keyed by the payment event that caused them. */
    public static function payment(Payment $payment, PaymentEvent $event): ?NotificationMessage
    {
        $amount = Money::inr($payment->amount());
        $scheme = (string) $payment->customerPlan?->scheme_number;
        $key = 'payment_event:'.$event->id;
        $url = route('customer.payments.show', $payment->reference, absolute: false);
        $data = ['payment_reference' => $payment->reference, 'scheme_number' => $scheme, 'amount' => $payment->amount()];
        $details = ['Scheme' => $scheme, 'Amount' => $amount, 'Reference' => $payment->reference];

        return match ($event->event) {
            PaymentEventType::Initiated => new NotificationMessage(
                NotificationType::PaymentInitiated, 'Payment Started', "Your $amount installment payment has started.", $key, $details, $data, $url,
            ),
            PaymentEventType::Succeeded => self::paymentSuccess($payment, $key, $url, $data),
            // Never the gateway's error text — a fixed, safe message.
            PaymentEventType::Failed, PaymentEventType::VerificationFailed => new NotificationMessage(
                NotificationType::PaymentFailed, 'Payment Failed', 'Your payment could not be completed. You can try again.', $key, $details, $data, $url,
            ),
            PaymentEventType::Cancelled => new NotificationMessage(
                NotificationType::PaymentCancelled, 'Payment Cancelled', 'Your payment was cancelled. You can try again.', $key, $details, $data, $url,
            ),
            default => null, // "pending" is not a customer-facing outcome
        };
    }

    public static function goldCredited(GoldTransaction $gold): NotificationMessage
    {
        $payment = $gold->payment;
        $rate = Money::inr($gold->ratePerGram()).'/g';

        return new NotificationMessage(
            type: NotificationType::GoldCredited,
            title: 'Gold Credited',
            message: "{$gold->gold_grams} g of {$gold->purity->value} gold has been added to your savings.",
            key: 'gold_transaction:'.$gold->id,
            details: [
                'Gold' => $gold->gold_grams.' g',
                'Rate used' => $rate,
                'Payment' => Money::inr(Money::fromPaise($gold->amount_paise)),
                'Scheme' => (string) $gold->customerPlan?->scheme_number,
            ],
            data: [
                'gold_grams' => $gold->gold_grams,
                'rate_per_gram' => $gold->ratePerGram(),
                'purity' => $gold->purity->value,
                'amount' => Money::fromPaise($gold->amount_paise),
                'payment_reference' => $payment?->reference,
            ],
            url: $payment ? route('customer.payments.show', $payment->reference, absolute: false) : null,
        );
    }

    private static function paymentSuccess(Payment $payment, string $key, string $url, array $data): NotificationMessage
    {
        $gold = $payment->goldTransaction; // the permanent snapshot
        $amount = Money::inr($payment->amount());
        $details = ['Scheme' => (string) $payment->customerPlan?->scheme_number];

        if ($gold) {
            $details += ['Gold credited' => $gold->gold_grams.' g', 'Rate' => Money::inr($gold->ratePerGram()).'/g'];
            $data += ['gold_grams' => $gold->gold_grams, 'rate_per_gram' => $gold->ratePerGram(), 'purity' => $gold->purity->value];
        }

        return new NotificationMessage(
            NotificationType::PaymentSuccess, 'Payment Successful', "$amount payment received successfully.",
            $key, $details + ['Paid on' => $payment->paid_at?->format('d M Y, g:i A') ?? Carbon::now()->format('d M Y, g:i A')], $data, $url,
        );
    }
}
