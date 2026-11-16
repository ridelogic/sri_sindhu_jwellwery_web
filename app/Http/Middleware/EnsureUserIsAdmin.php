<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the admin area.
 *
 *  - guest            → redirect to admin.login (never the public customer login)
 *  - signed-in non-admin → 403 Forbidden
 *  - admin            → continue
 *
 * Admin responses are also marked no-store / noindex so they are never cached
 * by the browser or indexed by search engines.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user(); // admin guard only

        if ($user === null) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], 401)
                : redirect()->guest(route('admin.login'));
        }

        if (! $user->isAdmin()) {
            abort(403, 'You do not have access to the admin area.');
        }

        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
