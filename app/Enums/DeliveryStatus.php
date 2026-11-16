<?php

namespace App\Enums;

/**
 * Push delivery state. "sent" is only recorded when the provider accepted the
 * message; with no credentials the state is "not_configured", never "sent".
 */
enum DeliveryStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
    case NotConfigured = 'not_configured';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Sent => 'Sent',
            self::Failed => 'Failed',
            self::NotConfigured => 'Not configured',
        };
    }
}
