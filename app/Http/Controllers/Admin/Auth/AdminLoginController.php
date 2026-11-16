<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin sign-in / sign-out (session authentication on the "web" guard).
 *
 * Only users with is_admin = true can sign in here. A customer's credentials
 * fail with the same generic message as a wrong password, so the form never
 * reveals whether an email exists or belongs to a customer.
 */
class AdminLoginController extends Controller
{
    /** Failed attempts allowed per email + IP before a temporary lockout. */
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 60;

    public function create(): View
    {
        return view('admin.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:150'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => 'Too many sign-in attempts. Please try again in '.RateLimiter::availableIn($throttleKey).' seconds.',
            ]);
        }

        $authenticated = Auth::guard('web')->attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'is_admin' => true, // customers can never authenticate here
        ]);

        if (! $authenticated) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            throw ValidationException::withMessages(['email' => 'Invalid credentials.']);
        }

        RateLimiter::clear($throttleKey);

        // Prevent session fixation.
        $request->session()->regenerate();

        return redirect()->to($this->intendedAdminUrl($request));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('status', 'You have been signed out.');
    }

    private function throttleKey(Request $request): string
    {
        return 'admin-login|'.Str::transliterate(Str::lower((string) $request->input('email'))).'|'.$request->ip();
    }

    /**
     * Honour the originally requested URL only when it is inside the admin area.
     */
    private function intendedAdminUrl(Request $request): string
    {
        $intended = (string) $request->session()->pull('url.intended', '');
        $adminRoot = rtrim(route('admin.dashboard'), '/');

        return $intended !== '' && ($intended === $adminRoot || str_starts_with($intended, $adminRoot.'/'))
            ? $intended
            : route('admin.dashboard');
    }
}
