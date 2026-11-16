<?php

namespace App\Http\Controllers\Auth;

use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\RegisterCustomerRequest;
use App\Models\Plan;
use App\Models\User;
use App\Services\EnrolmentService;
use App\Services\GoldSavingPlanService;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Passwordless customer registration, verified by OTP.
 *
 *  1. store()  — details + plan validated, kept in the SERVER-SIDE session, OTP sent
 *  2. verify() — OTP verified → account + first scheme enrolment created in one
 *                transaction → signed in on the "customer" guard
 *
 * No account exists until the mobile number has been verified. Nothing from the
 * browser except the code is used at step 2.
 */
class RegisterController extends Controller
{
    public const SESSION_KEY = 'otp.register';

    public function __construct(private OtpService $otps) {}

    public function create(Request $request): View
    {
        $pending = $request->session()->get(self::SESSION_KEY);
        $plans = app(GoldSavingPlanService::class)->joinable();
        $requested = (string) $request->query('plan', '');
        $selected = collect($plans)->firstWhere('slug', $requested) ? $requested : null;

        return view('auth.register', [
            'plans' => $plans,
            'selectedSlug' => old('plan', $selected),
            'pendingMobile' => $pending['mobile_number'] ?? null,
            'maskedMobile' => isset($pending['mobile_number']) ? LoginController::mask($pending['mobile_number']) : null,
            'resendIn' => isset($pending['mobile_number']) ? $this->otps->cooldownRemaining($pending['mobile_number'], OtpPurpose::Register) : 0,
        ]);
    }

    /** Step 1: validate the details and send an OTP to the new mobile number. */
    public function store(RegisterCustomerRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $pending = [
            'name' => $data['name'],
            'email' => $data['email'],
            'mobile_number' => $request->input('mobile_number'), // normalised server-side
            'plan' => $data['plan'],
        ];

        $request->session()->put(self::SESSION_KEY, $pending);

        return $this->sendOtp($request, $pending['mobile_number'], withInput: true);
    }

    public function resend(Request $request): RedirectResponse
    {
        $mobile = $request->session()->get(self::SESSION_KEY.'.mobile_number');

        return $mobile ? $this->sendOtp($request, $mobile) : redirect()->route('register');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('register');
    }

    /** Step 2: verify the OTP, then create the account and the first enrolment. */
    public function verify(Request $request, EnrolmentService $enrolments): RedirectResponse
    {
        $pending = $request->session()->get(self::SESSION_KEY);
        if (! is_array($pending) || empty($pending['mobile_number'])) {
            return redirect()->route('register');
        }

        $code = (string) $request->validate(['otp' => ['required', 'string', 'max:10']], ['otp.required' => 'Please enter the 6-digit OTP.'])['otp'];

        $ipKey = 'otp-verify-ip|'.$request->ip();
        if (RateLimiter::tooManyAttempts($ipKey, LoginController::VERIFY_ATTEMPTS_PER_IP)) {
            return back()->withErrors(['otp' => 'Too many attempts. Please try again in '.ceil(RateLimiter::availableIn($ipKey) / 60).' minutes.']);
        }
        RateLimiter::hit($ipKey, 600);

        $result = $this->otps->verify($pending['mobile_number'], OtpPurpose::Register, preg_replace('/\s+/', '', $code));

        if ($result['status'] !== OtpService::VERIFIED) {
            return back()->withErrors(['otp' => match ($result['status']) {
                OtpService::EXPIRED => LoginController::EXPIRED_OTP_MESSAGE,
                OtpService::LOCKED => LoginController::LOCKED_OTP_MESSAGE,
                default => LoginController::INVALID_OTP_MESSAGE,
            }]);
        }

        // Re-check what may have changed while the OTP was outstanding (uniqueness, plan still open).
        $check = Validator::make($pending, [
            'email' => ['required', 'email', 'unique:users,email'],
            'mobile_number' => ['required', 'regex:/^[6-9]\d{9}$/', 'unique:users,mobile_number'],
            'plan' => ['required', 'exists:plans,slug,status,1'],
        ], [
            'email.unique' => 'An account with this email already exists. Please log in instead.',
            'mobile_number.unique' => 'An account with this mobile number already exists. Please log in instead.',
            'plan.exists' => 'This plan is no longer open for new enrolments. Please choose another plan.',
        ]);
        if ($check->fails()) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()->route('register')->withErrors($check->errors()->toArray());
        }

        [$customer, $scheme] = DB::transaction(function () use ($pending, $enrolments) {
            $customer = User::query()->create([
                'name' => $pending['name'],
                'email' => $pending['email'],
                'mobile_number' => $pending['mobile_number'],
                // Customers sign in with OTP only. The password column is required, so it holds
                // a random, never-disclosed value (hashed by the model cast) that nobody knows.
                'password' => Str::password(64),
            ]);

            [$scheme] = $enrolments->enrol($customer, Plan::query()->where('slug', $pending['plan'])->firstOrFail());

            return [$customer, $scheme];
        });

        $request->session()->forget(self::SESSION_KEY);
        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return redirect()->route('customer.dashboard')
            ->with('status', "Welcome to Sri Sindhu Jewellery! You have joined {$scheme->plan->name} — scheme number {$scheme->scheme_number}.");
    }

    private function sendOtp(Request $request, string $mobile, bool $withInput = false): RedirectResponse
    {
        $result = $this->otps->request($mobile, OtpPurpose::Register, $request->ip());

        if ($result['status'] === OtpService::SENT || $result['status'] === OtpService::COOLDOWN) {
            $redirect = redirect()->route('register');

            return $result['status'] === OtpService::SENT
                ? $redirect->with('status', 'A 6-digit OTP has been sent to '.LoginController::mask($mobile).'. It is valid for 5 minutes.')
                    ->with('otp_development_code', $result['development_code'])
                : $redirect->withErrors(['otp' => 'Please wait '.$result['retry_after'].' seconds before requesting another OTP.']);
        }

        $message = $result['status'] === OtpService::RATE_LIMITED
            ? 'Too many OTP requests. Please try again in '.max(1, (int) ceil($result['retry_after'] / 60)).' minutes.'
            : LoginController::SEND_FAILED_MESSAGE;

        if (! $withInput) {
            // A failed resend keeps the customer on the OTP step.
            return redirect()->route('register')->withErrors(['otp' => $message]);
        }

        // Not sent: back to the details form with their input (nothing was created).
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('register')->withErrors(['mobile' => $message])->withInput($request->except(['_token']));
    }
}
