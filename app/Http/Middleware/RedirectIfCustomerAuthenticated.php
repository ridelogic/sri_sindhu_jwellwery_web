<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a signed-in customer from /login and /register to their dashboard.
 */
class RedirectIfCustomerAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('customer')->user();

        if ($user !== null && ! $user->isAdmin()) {
            return redirect()->route('customer.dashboard');
        }

        return $next($request);
    }
}
