<?php

namespace Tests\Concerns;

use Illuminate\Testing\TestResponse;

/**
 * Customer sign-in for feature tests: mobile number → OTP → verify, using the
 * development OTP sender (no SMS; the code is handed back via the session).
 */
trait SignsInWithOtp
{
    protected function useDevelopmentSms(): void
    {
        config(['sms.driver' => 'development']);
    }

    /** Step 1. Moves past any resend cooldown left by an earlier request in the same test. */
    protected function requestLoginOtp(string $mobile): TestResponse
    {
        $this->travel(61)->seconds();

        return $this->from(route('login'))->post(route('login.store'), ['mobile' => $mobile]);
    }

    /** The OTP the development sender handed back for the last request (null if none was sent). */
    protected function developmentOtp(): ?string
    {
        return session('otp_development_code');
    }

    protected function submitLoginOtp(string $otp): TestResponse
    {
        return $this->from(route('login'))->post(route('login.verify'), ['otp' => $otp]);
    }

    /** Full sign-in. Uses a wrong code if no OTP was issued (e.g. unregistered number). */
    protected function otpLogin(string $mobile): TestResponse
    {
        $this->requestLoginOtp($mobile);

        return $this->submitLoginOtp($this->developmentOtp() ?? '000000');
    }
}
