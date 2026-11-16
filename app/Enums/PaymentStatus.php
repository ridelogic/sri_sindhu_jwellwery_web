<?php

namespace App\Enums;

/**
 * created   — attempt recorded, gateway order opened, customer not yet back
 * pending   — gateway reported an unconfirmed state (e.g. authorised, not captured)
 * success   — verified with the gateway server-side; gold has been credited (final)
 * failed    — declined, unverifiable or errored; no gold
 * cancelled — the customer abandoned checkout; no gold
 */
enum PaymentStatus: string
{
    case Created = 'created';
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Initiated',
            self::Pending => 'Pending',
            self::Success => 'Successful',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    /** Still waiting for the gateway outcome. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Created, self::Pending], true);
    }

    /** @return list<string> */
    public static function openValues(): array
    {
        return [self::Created->value, self::Pending->value];
    }
}
