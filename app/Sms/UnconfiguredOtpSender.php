<?php

namespace App\Sms;

/** No SMS provider configured: every send fails honestly — an OTP is never "sent". */
class UnconfiguredOtpSender implements OtpSender
{
    public function isDevelopmentOnly(): bool
    {
        return false;
    }

    public function send(string $mobile, string $otp, int $expiresMinutes): SmsResult
    {
        return SmsResult::failed('not_configured');
    }
}
