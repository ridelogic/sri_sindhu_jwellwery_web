<?php

/*
|--------------------------------------------------------------------------
| SMS / OTP
|--------------------------------------------------------------------------
|
| Customer sign-in and registration use a one-time password (OTP) sent by SMS.
| Credentials come ONLY from the environment — never commit real keys.
|
| SMS_DRIVER
|   msg91        MSG91 Flow API with a DLT-approved OTP template (production)
|   development  LOCAL DEVELOPMENT ONLY: no SMS is sent; the OTP is shown on the
|                OTP screen in a clearly marked development box. Refused (treated
|                as "not configured") unless APP_ENV is local or testing.
|   (empty)      not configured: OTPs cannot be sent and customers are told so.
|
| MSG91 setup (see docs/SMS-OTP.md):
|   SMS_MSG91_AUTH_KEY         MSG91 auth key (Dashboard → API → Auth Key)
|   SMS_MSG91_TEMPLATE_ID      Flow/template id of a DLT-approved OTP template
|   SMS_MSG91_OTP_VARIABLE     template variable holding the OTP (default: otp)
|
*/

return [

    'driver' => env('SMS_DRIVER'),

    'otp' => [
        'length' => 6,
        'expires_minutes' => 5,
        'max_verify_attempts' => 5,         // wrong codes before an OTP is locked
        'resend_cooldown_seconds' => 60,    // per mobile number + purpose
        'max_sends_per_hour' => 5,          // per mobile number + purpose
        'max_sends_per_ip_per_hour' => 20,  // per client IP, all numbers
    ],

    'drivers' => [
        'msg91' => [
            'auth_key' => env('SMS_MSG91_AUTH_KEY'),
            'template_id' => env('SMS_MSG91_TEMPLATE_ID'),
            'otp_variable' => env('SMS_MSG91_OTP_VARIABLE', 'otp'),
            'url' => env('SMS_MSG91_URL', 'https://control.msg91.com/api/v5/flow'),
            'timeout' => 10,
        ],
    ],

];
