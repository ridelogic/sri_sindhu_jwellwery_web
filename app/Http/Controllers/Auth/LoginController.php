<?php

namespace App\Http\Controllers\Auth;

use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use App\Support\IndianMobile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Customer sign-in with mobile number + one-time password (no password),
 * on the separate "customer" guard. Administrators sign in at /admin/login
 * with their password; admin accounts can never sign in here.
 *
 *  1. store()  — mobile number → OTP sent (generic response either way)
 *  2. verify() — OTP → signed in, if the account is Active
 *
 * The mobile number being verified is kept in the SERVER-SIDE session between
 * the steps; the browser only ever submits the code. No client-supplied user
 * id or mobile number decides which account is signed in.
 */
class LoginController extends Controller
{
    public const SESSION_KEY = 'otp.login';

    public const VERIFY_ATTEMPTS_PER_IP = 30;     // per 10 minutes, across all numbers

    public const SENT_MESSAGE = 'If :mobile is registered with us, a 6-digit OTP has been sent to it. The OTP is valid for 5 minutes.';

    public const BLOCKED_MESSAGE = 'Your account is currently blocked. Please contact Sri Sindhu Jewellery for help.';

    public const INACTIVE_MESSAGE = 'Your account is inactive. Please contact the administrator to activate your account.';

    public const SEND_FAILED_MESSAGE = 'We could not send the OTP right now. Please try again in a few minutes.';

    public const INVALID_OTP_MESSAGE = 'The OTP is incorrect. Please check the code and try again.';

    public const EXPIRED_OTP_MESSAGE = 'This OTP has expired. Please request a new one.';

    public const LOCKED_OTP_MESSAGE = 'Too many incorrect attempts. Please request a new OTP.';

    public function __construct(private OtpService $otps) {}

    public function create(Request $request): View
    {
        $mobile = $request->session()->get(self::SESSION_KEY.'.mobile');

        return view('auth.login', [
            'pendingMobile' => $mobile,
            'maskedMobile' => $mobile ? self::mask($mobile) : null,
            'resendIn' => $mobile ? $this->otps->cooldownRemaining($mobile, OtpPurpose::Login) : 0,
        ]);
    }

    /** Step 1: send an OTP to the entered mobile number. */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['mobile' => ['required', 'string', 'max:20']], ['mobile.required' => 'Please enter your mobile number.']);

        $mobile = IndianMobile::normalise($request->input('mobile'));
        if ($mobile === null) {
            return back()->withInput($request->only('mobile'))->withErrors(['mobile' => 'Please enter a valid 10-digit Indian mobile number.']);
        }

        $request->session()->forget(self::SESSION_KEY);

        return $this->sendOtp($request, $mobile);
    }

    /** Resend to the number already being verified (never a client-supplied number). */
    public function resend(Request $request): RedirectResponse
    {
        $mobile = $request->session()->get(self::SESSION_KEY.'.mobile');

        return $mobile ? $this->sendOtp($request, $mobile) : redirect()->route('login');
    }

    /** "Change number": abandon the pending sign-in. */
    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('login');
    }

    /** Step 2: verify the OTP and sign in. */
    public function verify(Request $request): RedirectResponse
    {
        $mobile = $request->session()->get(self::SESSION_KEY.'.mobile');
        if (! $mobile) {
            return redirect()->route('login')->withErrors(['mobile' => 'Please enter your mobile number to receive a new OTP.']);
        }

        $code = (string) $request->validate(['otp' => ['required', 'string', 'max:10']], ['otp.required' => 'Please enter the 6-digit OTP.'])['otp'];

        $ipKey = 'otp-verify-ip|'.$request->ip();
        if (RateLimiter::tooManyAttempts($ipKey, self::VERIFY_ATTEMPTS_PER_IP)) {
            return back()->withErrors(['otp' => 'Too many attempts. Please try again in '.ceil(RateLimiter::availableIn($ipKey) / 60).' minutes.']);
        }
        RateLimiter::hit($ipKey, 600);

        $result = $this->otps->verify($mobile, OtpPurpose::Login, preg_replace('/\s+/', '', $code));

        if ($result['status'] !== OtpService::VERIFIED) {
            return back()->withErrors(['otp' => match ($result['status']) {
                OtpService::EXPIRED => self::EXPIRED_OTP_MESSAGE,
                OtpService::LOCKED => self::LOCKED_OTP_MESSAGE,
                default => self::INVALID_OTP_MESSAGE,
            }]);
        }

        // The account is resolved from the verified number only — admins never qualify.
        $customer = User::query()->customers()->where('mobile_number', $mobile)->first();
        if ($customer === null) {
            return back()->withErrors(['otp' => self::INVALID_OTP_MESSAGE]);
        }

        if (! $customer->canSignIn()) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()->route('login')->withErrors(['mobile' => $customer->isBlocked() ? self::BLOCKED_MESSAGE : self::INACTIVE_MESSAGE]);
        }

        $request->session()->forget(self::SESSION_KEY);
        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return redirect()->to($this->intendedCustomerUrl($request));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('notice', 'You have been signed out.');
    }

    /** "+91 98XXX XX210" */
    public static function mask(string $mobile): string
    {
        return '+91 '.substr($mobile, 0, 2).'XXX XX'.substr($mobile, -3);
    }

    private function sendOtp(Request $request, string $mobile): RedirectResponse
    {
        // Registered customers (any status) receive a code; inactive/blocked accounts are told
        // after verifying the code, so the response here never reveals whether a number is registered.
        $customer = User::query()->customers()->where('mobile_number', $mobile)->first();

        $result = $this->otps->request($mobile, OtpPurpose::Login, $request->ip(), deliver: $customer !== null, userId: $customer?->id);

        return match ($result['status']) {
            OtpService::SENT => $this->toOtpStep($request, $mobile)
                ->with('status', str_replace(':mobile', self::mask($mobile), self::SENT_MESSAGE))
                ->with('otp_development_code', $result['development_code']),
            OtpService::COOLDOWN => $this->toOtpStep($request, $mobile)
                ->withErrors(['otp' => 'Please wait '.$result['retry_after'].' seconds before requesting another OTP.']),
            OtpService::RATE_LIMITED => back()->withInput($request->only('mobile'))
                ->withErrors([$request->session()->has(self::SESSION_KEY) ? 'otp' : 'mobile' => 'Too many OTP requests. Please try again in '.max(1, (int) ceil($result['retry_after'] / 60)).' minutes.']),
            default => back()->withInput($request->only('mobile'))
                ->withErrors([$request->session()->has(self::SESSION_KEY) ? 'otp' : 'mobile' => self::SEND_FAILED_MESSAGE]),
        };
    }

    private function toOtpStep(Request $request, string $mobile): RedirectResponse
    {
        $request->session()->put(self::SESSION_KEY.'.mobile', $mobile);

        return redirect()->route('login');
    }

    /**
     * Only honour a previously requested URL inside the customer portal.
     */
    private function intendedCustomerUrl(Request $request): string
    {
        $intended = (string) $request->session()->pull('url.intended', '');
        $root = rtrim(url('/customer'), '/');

        return $intended !== '' && str_starts_with($intended, $root.'/') ? $intended : route('customer.dashboard');
    }
}
