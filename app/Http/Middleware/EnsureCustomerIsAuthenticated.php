<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Auth\LoginController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the customer portal (/customer/*) using the separate "customer" guard.
 *
 *  - not signed in as a customer → redirect to the public customer login
 *    (an admin session on the "web" guard does NOT count)
 *  - signed in but the account is an admin → signed out of the customer guard, 403
 *  - customer → continue, with "customer" as the default guard for this request
 *
 * Responses are marked private / no-store / noindex.
 */
class EnsureCustomerIsAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('customer');
        $user = $guard->user();

        if ($user === null) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], 401)
                : redirect()->guest(route('login'));
        }

        if ($user->isAdmin()) {
            // Defence in depth: customer login never accepts admins, but never trust a stale session.
            $guard->logout();
            abort(403, 'Administrator accounts cannot use the customer portal.');
        }

        if (! $user->canSignIn()) {
            // Blocked (or not activated) while signed in, or via a remember-me cookie: end the customer session now.
            $message = $user->isBlocked() ? LoginController::BLOCKED_MESSAGE : LoginController::INACTIVE_MESSAGE;
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => $message], 403)
                : redirect()->route('login')->withErrors(['mobile' => $message]);
        }

        // $request->user() and auth()->user() resolve the customer for the rest of this request.
        Auth::shouldUse('customer');

        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
