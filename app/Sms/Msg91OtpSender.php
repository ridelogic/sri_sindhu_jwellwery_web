<?php

namespace App\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MSG91 Flow API (https://control.msg91.com/api/v5/flow) with a DLT-approved
 * OTP template. The template text lives in MSG91; we send the template id,
 * the recipient (91 + 10-digit number) and the OTP as a template variable.
 *
 * Not verified against the live MSG91 service in this project — see docs/SMS-OTP.md.
 */
class Msg91OtpSender implements OtpSender
{
    /** @param  array{auth_key: ?string, template_id: ?string, otp_variable: ?string, url: string, timeout: int}  $config */
    public function __construct(private array $config) {}

    public function isConfigured(): bool
    {
        return filled($this->config['auth_key'] ?? null) && filled($this->config['template_id'] ?? null);
    }

    public function isDevelopmentOnly(): bool
    {
        return false;
    }

    public function send(string $mobile, string $otp, int $expiresMinutes): SmsResult
    {
        if (! $this->isConfigured()) {
            return SmsResult::failed('not_configured');
        }

        try {
            $response = Http::timeout((int) ($this->config['timeout'] ?? 10))
                ->acceptJson()
                ->withHeaders(['authkey' => $this->config['auth_key']])
                ->post($this->config['url'], [
                    'template_id' => $this->config['template_id'],
                    'short_url' => '0',
                    'recipients' => [[
                        'mobiles' => '91'.$mobile,
                        ($this->config['otp_variable'] ?: 'otp') => $otp,
                    ]],
                ]);
        } catch (ConnectionException) {
            $this->logFailure('connection', null);

            return SmsResult::failed('unreachable');
        }

        if ($response->successful() && $response->json('type') === 'success') {
            return SmsResult::sent();
        }

        $this->logFailure('rejected', $response->status());

        return SmsResult::failed('rejected');
    }

    /** Never logs the OTP, the auth key or the full mobile number. */
    private function logFailure(string $reason, ?int $status): void
    {
        Log::warning('SMS OTP could not be sent.', ['provider' => 'msg91', 'reason' => $reason, 'http_status' => $status]);
    }
}
