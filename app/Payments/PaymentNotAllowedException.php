<?php

namespace App\Payments;

use RuntimeException;

/**
 * A payment cannot be started for this scheme right now. The message is
 * customer-facing; $reason is a stable machine code (kyc, scheme, complete,
 * not_due, gateway, rate, ownership).
 */
class PaymentNotAllowedException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }
}
