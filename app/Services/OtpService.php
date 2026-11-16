<?php

namespace App\Services;

use App\Enums\OtpPurpose;
use App\Models\CustomerOtp;
use App\Sms\OtpSender;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * One-time passwords for customer sign-in and registration.
 *
 * Policy (all server-side):
 *  - 6 digits from a CSPRNG (random_int); valid for 5 minutes.
 *  - Only an HMAC-SHA256 of the code is stored, bound to mobile + purpose and
 *    keyed with APP_KEY. The plain code is never stored or logged.
 *  - Requesting a new OTP invalidates every earlier open OTP for the same
 *    mobile + purpose, so only the latest code can ever work.
 *  - Resend cooldown per mobile + purpose; hourly send caps per mobile + purpose
 *    and per IP. Limits apply to unregistered numbers too, so responses do not
 *    reveal whether a number is registered.
 *  - At most 5 wrong codes per OTP, after which it is locked (invalidated).
 *  - A correct code is consumed atomically and can never be reused.
 *  - If the SMS provider fails, the new OTP is invalidated and the caller is
 *    told it was NOT sent.
 */
class OtpService
{
    public const SENT = 'sent';

    public const COOLDOWN = 'cooldown';

    public const RATE_LIMITED = 'rate_limited';

    public const SEND_FAILED = 'send_failed';

    public const VERIFIED = 'verified';

    public const INVALID = 'invalid';

    public const EXPIRED = 'expired';

    public const LOCKED = 'locked';

    public function __construct(private OtpSender $sender) {}

    /**
     * Request an OTP for $mobile. Pass $deliver = false for an unregistered number on
     * sign-in: the limits are applied identically but nothing is created or sent.
     *
     * @return array{status: string, retry_after: int, development_code: ?string}
     */
    public function request(string $mobile, OtpPurpose $purpose, ?string $ip, bool $deliver = true, ?int $userId = null): array
    {
        $cooldownKey = "otp-cooldown|{$purpose->value}|{$mobile}";
        $mobileKey = "otp-send|{$purpose->value}|{$mobile}";
        $ipKey = 'otp-send-ip|'.($ip ?? 'unknown');
        $config = config('sms.otp');

        if (RateLimiter::tooManyAttempts($cooldownKey, 1)) {
            return $this->result(self::COOLDOWN, RateLimiter::availableIn($cooldownKey));
        }
        if (RateLimiter::tooManyAttempts($mobileKey, $config['max_sends_per_hour']) || RateLimiter::tooManyAttempts($ipKey, $config['max_sends_per_ip_per_hour'])) {
            return $this->result(self::RATE_LIMITED, max(RateLimiter::availableIn($mobileKey), RateLimiter::availableIn($ipKey)));
        }

        RateLimiter::hit($cooldownKey, $config['resend_cooldown_seconds']);
        RateLimiter::hit($mobileKey, 3600);
        RateLimiter::hit($ipKey, 3600);

        if (! $deliver) {
            return $this->result(self::SENT, $config['resend_cooldown_seconds']);
        }

        $code = str_pad((string) random_int(0, 10 ** $config['length'] - 1), $config['length'], '0', STR_PAD_LEFT);

        $otp = DB::transaction(function () use ($mobile, $purpose, $userId, $code, $ip, $config) {
            // Only the newest code can work: close every earlier open one.
            CustomerOtp::query()->where('mobile_number', $mobile)->where('purpose', $purpose->value)->open()
                ->update(['invalidated_at' => now()]);
            // Housekeeping: drop this number's records older than a day.
            CustomerOtp::query()->where('mobile_number', $mobile)->where('created_at', '<', now()->subDay())->delete();

            return CustomerOtp::query()->create([
                'mobile_number' => $mobile,
                'purpose' => $purpose,
                'user_id' => $userId,
                'code_hash' => $this->hash($mobile, $purpose, $code),
                'expires_at' => now()->addMinutes($config['expires_minutes']),
                'ip_address' => $ip,
            ]);
        });

        $sent = $this->sender->send($mobile, $code, $config['expires_minutes']);

        if (! $sent->sent) {
            // Never leave a usable code behind for an SMS that did not go out.
            $otp->forceFill(['invalidated_at' => now()])->save();
            RateLimiter::clear($cooldownKey); // let the customer retry straight away

            return $this->result(self::SEND_FAILED, 0);
        }

        return $this->result(self::SENT, $config['resend_cooldown_seconds'], $this->sender->isDevelopmentOnly() ? $code : null);
    }

    /**
     * Verify $code for $mobile. Returns VERIFIED (and consumes the OTP) or a failure status.
     *
     * @return array{status: string, otp: ?CustomerOtp}
     */
    public function verify(string $mobile, OtpPurpose $purpose, string $code): array
    {
        return DB::transaction(function () use ($mobile, $purpose, $code) {
            /** @var CustomerOtp|null $otp */
            $otp = CustomerOtp::query()->where('mobile_number', $mobile)->where('purpose', $purpose->value)->open()
                ->latest('id')->lockForUpdate()->first();

            if ($otp === null) {
                return ['status' => self::INVALID, 'otp' => null];
            }

            if ($otp->expires_at->isPast()) {
                $otp->forceFill(['invalidated_at' => now()])->save();

                return ['status' => self::EXPIRED, 'otp' => null];
            }

            if (! preg_match('/^\d{'.config('sms.otp.length').'}$/', $code) || ! hash_equals($otp->code_hash, $this->hash($mobile, $purpose, $code))) {
                $attempts = $otp->attempts + 1;
                $locked = $attempts >= config('sms.otp.max_verify_attempts');
                $otp->forceFill(['attempts' => $attempts, 'invalidated_at' => $locked ? now() : null])->save();

                return ['status' => $locked ? self::LOCKED : self::INVALID, 'otp' => null];
            }

            // Atomic single use: only one request can flip consumed_at.
            $consumed = CustomerOtp::query()->whereKey($otp->id)->whereNull('consumed_at')->whereNull('invalidated_at')
                ->update(['consumed_at' => now(), 'updated_at' => now()]);

            return $consumed === 1 ? ['status' => self::VERIFIED, 'otp' => $otp->fresh()] : ['status' => self::INVALID, 'otp' => null];
        });
    }

    /** Seconds until another OTP may be requested for this mobile + purpose (0 = now). */
    public function cooldownRemaining(string $mobile, OtpPurpose $purpose): int
    {
        $key = "otp-cooldown|{$purpose->value}|{$mobile}";

        return RateLimiter::tooManyAttempts($key, 1) ? RateLimiter::availableIn($key) : 0;
    }

    private function hash(string $mobile, OtpPurpose $purpose, string $code): string
    {
        return hash_hmac('sha256', $purpose->value.'|'.$mobile.'|'.$code, (string) config('app.key'));
    }

    /** @return array{status: string, retry_after: int, development_code: ?string} */
    private function result(string $status, int $retryAfter, ?string $developmentCode = null): array
    {
        return ['status' => $status, 'retry_after' => $retryAfter, 'development_code' => $developmentCode];
    }
}
