<?php

namespace App\Enums;

enum PaymentEventType: string
{
    case Initiated = 'initiated';
    case Succeeded = 'succeeded';
    case Pending = 'pending';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case VerificationFailed = 'verification_failed';

    public function label(): string
    {
        return match ($this) {
            self::Initiated => 'Payment initiated',
            self::Succeeded => 'Payment successful — gold credited',
            self::Pending => 'Awaiting gateway confirmation',
            self::Failed => 'Payment failed',
            self::Cancelled => 'Payment cancelled',
            self::VerificationFailed => 'Gateway response could not be verified',
        };
    }
}
