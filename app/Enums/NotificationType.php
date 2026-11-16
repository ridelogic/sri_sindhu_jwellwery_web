<?php

namespace App\Enums;

/**
 * Customer notification types. Each type belongs to one preference category,
 * which the customer can switch off.
 */
enum NotificationType: string
{
    case GoldRateUpdated = 'gold_rate_updated';
    case KycSubmitted = 'kyc_submitted';
    case KycApproved = 'kyc_approved';
    case KycRejected = 'kyc_rejected';
    case PaymentInitiated = 'payment_initiated';
    case PaymentSuccess = 'payment_success';
    case PaymentFailed = 'payment_failed';
    case PaymentCancelled = 'payment_cancelled';
    case GoldCredited = 'gold_credited';

    public function label(): string
    {
        return match ($this) {
            self::GoldRateUpdated => 'Gold rate updated',
            self::KycSubmitted => 'KYC submitted',
            self::KycApproved => 'KYC approved',
            self::KycRejected => 'KYC requires update',
            self::PaymentInitiated => 'Payment started',
            self::PaymentSuccess => 'Payment successful',
            self::PaymentFailed => 'Payment failed',
            self::PaymentCancelled => 'Payment cancelled',
            self::GoldCredited => 'Gold credited',
        };
    }

    public function preference(): NotificationPreferenceKey
    {
        return match ($this) {
            self::GoldRateUpdated => NotificationPreferenceKey::GoldRateUpdates,
            self::KycSubmitted, self::KycApproved, self::KycRejected => NotificationPreferenceKey::KycUpdates,
            default => NotificationPreferenceKey::PaymentUpdates,
        };
    }

    /** Icon name from the site icon set. */
    public function icon(): string
    {
        return match ($this) {
            self::GoldRateUpdated => 'trend',
            self::KycSubmitted, self::KycApproved, self::KycRejected => 'shield',
            self::GoldCredited => 'gem',
            default => 'receipt',
        };
    }

    /** Visual tone: positive, attention or neutral. */
    public function tone(): string
    {
        return match ($this) {
            self::KycApproved, self::PaymentSuccess, self::GoldCredited => 'positive',
            self::KycRejected, self::PaymentFailed => 'attention',
            default => 'neutral',
        };
    }
}
