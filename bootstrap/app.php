<?php

use App\Http\Middleware\EnsureCustomerIsAuthenticated;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\RedirectIfAdmin;
use App\Http\Middleware\RedirectIfCustomerAuthenticated;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'admin.guest' => RedirectIfAdmin::class,
            'customer' => EnsureCustomerIsAuthenticated::class,
            'customer.guest' => RedirectIfCustomerAuthenticated::class,
        ]);

        // Authorise BEFORE route-model binding, so guests and customers can't probe
        // which admin-only record ids exist (404 vs redirect).
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureUserIsAdmin::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureCustomerIsAuthenticated::class);

        // Payment gateway webhooks are authenticated by their signature, not a session.
        $middleware->validateCsrfTokens(except: ['payments/webhook/*']);

        // Guests hitting a protected admin URL go to the ADMIN login,
        // never to the public customer login page.
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('admin', 'admin/*') ? route('admin.login') : route('login'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Identity numbers are never flashed back into the session or the form
        // after a validation error (passwords are excluded by default).
        $exceptions->dontFlash(['pan_number', 'aadhaar_last4']);
    })->create();
