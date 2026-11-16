<?php

namespace App\Enums;

/**
 * Preference switches (columns of notification_preferences).
 */
enum NotificationPreferenceKey: string
{
    case GoldRateUpdates = 'gold_rate_updates';
    case PaymentUpdates = 'payment_updates';
    case KycUpdates = 'kyc_updates';
    case PromotionalNotifications = 'promotional_notifications';

    public function label(): string
    {
        return match ($this) {
            self::GoldRateUpdates => 'Daily gold rate updates',
            self::PaymentUpdates => 'Payment and gold credit updates',
            self::KycUpdates => 'KYC verification updates',
            self::PromotionalNotifications => 'Offers and announcements',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::GoldRateUpdates => 'When our team publishes the 22K and 24K gold rate.',
            self::PaymentUpdates => 'Payment results and the gold credited to your schemes.',
            self::KycUpdates => 'When your KYC is submitted, approved or needs an update.',
            self::PromotionalNotifications => 'Occasional news from Sri Sindhu Jewellery. Off by default.',
        };
    }

    public function default(): bool
    {
        return $this !== self::PromotionalNotifications;
    }
}
