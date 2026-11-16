<?php

namespace App\Sms;

use LogicException;

/**
 * LOCAL DEVELOPMENT ONLY. Sends nothing: the OTP service hands the code back so
 * the OTP screen can show it in a clearly marked development box. Cannot be
 * constructed in production (see AppServiceProvider), and never logs the OTP.
 */
class DevelopmentOtpSender implements OtpSender
{
    public function __construct(string $environment)
    {
        if (! in_array($environment, ['local', 'testing'], true)) {
            throw new LogicException('The development OTP sender can only be used when APP_ENV is local or testing.');
        }
    }

    public function isDevelopmentOnly(): bool
    {
        return true;
    }

    public function send(string $mobile, string $otp, int $expiresMinutes): SmsResult
    {
        return SmsResult::sent();
    }
}
