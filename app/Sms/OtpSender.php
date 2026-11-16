<?php

namespace App\Sms;

/**
 * Sends a one-time password by SMS. Implementations must never log the OTP
 * and must report failure honestly — an OTP is only "sent" when the provider
 * accepted the message.
 */
interface OtpSender
{
    /**
     * @param  string  $mobile  10-digit Indian national number (normalised)
     * @param  string  $otp  the plain one-time password (never persisted or logged)
     */
    public function send(string $mobile, string $otp, int $expiresMinutes): SmsResult;

    /** True only for the local development sender (OTP shown on screen, no SMS). */
    public function isDevelopmentOnly(): bool;
}
