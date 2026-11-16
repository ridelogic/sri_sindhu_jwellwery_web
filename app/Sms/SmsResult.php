<?php

namespace App\Sms;

/** Outcome of an SMS send. $error is a safe, customer-neutral reason (no secrets, no OTP). */
final class SmsResult
{
    private function __construct(public readonly bool $sent, public readonly ?string $error = null) {}

    public static function sent(): self
    {
        return new self(true);
    }

    public static function failed(string $error): self
    {
        return new self(false, $error);
    }
}
