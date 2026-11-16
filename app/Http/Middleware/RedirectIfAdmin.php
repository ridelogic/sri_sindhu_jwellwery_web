<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends an already signed-in admin from the admin login page to the dashboard.
 */
class RedirectIfAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('web')->user()?->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return $next($request);
    }
}
