<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\Admin\Auth\AdminLoginController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GoldRateController as AdminGoldRateController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\CustomerKycController as AdminCustomerKycController;
use App\Http\Controllers\Admin\CustomerSchemeController as AdminCustomerSchemeController;
use App\Http\Controllers\Admin\KycController as AdminKycController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\SchemeController as AdminSchemeController;
use App\Http\Controllers\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Customer\DashboardController as CustomerDashboardController;
use App\Http\Controllers\Customer\DeviceController as CustomerDeviceController;
use App\Http\Controllers\Customer\NotificationController as CustomerNotificationController;
use App\Http\Controllers\Customer\KycController as CustomerKycController;
use App\Http\Controllers\Customer\PaymentController as CustomerPaymentController;
use App\Http\Controllers\Customer\PlanController as CustomerPlanController;
use App\Http\Controllers\Customer\ProfileController as CustomerProfileController;
use App\Http\Controllers\Customer\SchemeController as CustomerSchemeController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\GoldRateController;
use App\Http\Controllers\GoldSavingController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PaymentWebhookController;
use App\Models\User;
use App\Services\KycService;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public website
|--------------------------------------------------------------------------
*/

Route::get('/', HomeController::class)->name('home');
Route::get('/about', AboutController::class)->name('about');
Route::get('/gold-rate', GoldRateController::class)->name('gold.rate');

Route::get('/gold-saving', [GoldSavingController::class, 'index'])->name('gold.saving');
Route::get('/gold-saving/{slug}', [GoldSavingController::class, 'show'])
    ->where('slug', '[a-z0-9-]+')
    ->name('gold.saving.show');

Route::get('/faq', FaqController::class)->name('faq');

Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:10,1')->name('contact.store');

/*
|--------------------------------------------------------------------------
| Customer accounts ("customer" guard — separate from admin)
|--------------------------------------------------------------------------
*/

// Gateway → server notifications. CSRF-exempt (see bootstrap/app.php); authenticated by signature.
Route::post('/payments/webhook/{gateway}', PaymentWebhookController::class)
    ->where('gateway', '[a-z]+')->middleware('throttle:120,1')->name('payments.webhook');

Route::middleware('customer.guest')->group(function () {
    // Mobile number + OTP (no customer passwords). OTP sends/attempts are also limited in OtpService.
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
    Route::post('/login/verify', [LoginController::class, 'verify'])->middleware('throttle:20,1')->name('login.verify');
    Route::post('/login/resend', [LoginController::class, 'resend'])->middleware('throttle:10,1')->name('login.resend');
    Route::post('/login/change-number', [LoginController::class, 'cancel'])->name('login.cancel');

    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1')->name('register.store');
    Route::post('/register/verify', [RegisterController::class, 'verify'])->middleware('throttle:20,1')->name('register.verify');
    Route::post('/register/resend', [RegisterController::class, 'resend'])->middleware('throttle:10,1')->name('register.resend');
    Route::post('/register/change-details', [RegisterController::class, 'cancel'])->name('register.cancel');
});

Route::prefix('customer')->name('customer.')->middleware('customer')->group(function () {
    Route::get('/dashboard', CustomerDashboardController::class)->name('dashboard');

    Route::get('/schemes', [CustomerSchemeController::class, 'index'])->name('schemes.index');
    // Addressed by scheme number and always scoped to the signed-in customer.
    Route::get('/schemes/{schemeNumber}', [CustomerSchemeController::class, 'show'])
        ->where('schemeNumber', '[A-Za-z0-9\-]+')->name('schemes.show');

    Route::get('/plans', [CustomerPlanController::class, 'index'])->name('plans.index');
    Route::get('/plans/{slug}', [CustomerPlanController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('plans.show');
    Route::post('/plans/{slug}/join', [CustomerPlanController::class, 'join'])
        ->where('slug', '[a-z0-9-]+')->middleware('throttle:10,1')->name('plans.join');

    // Installment payments. Schemes by number and payments by reference, both
    // looked up within the signed-in customer's own records.
    Route::controller(CustomerPaymentController::class)->group(function () {
        Route::get('/schemes/{schemeNumber}/payment', 'create')->where('schemeNumber', '[A-Za-z0-9\-]+')->name('schemes.payment');
        Route::post('/schemes/{schemeNumber}/payment', 'store')->where('schemeNumber', '[A-Za-z0-9\-]+')->middleware('throttle:10,1')->name('schemes.payment.store');

        Route::get('/payments', 'index')->name('payments.index');
        Route::prefix('/payments/{reference}')->where(['reference' => '[A-Z0-9\-]+'])->name('payments.')->group(function () {
            Route::get('/', 'show')->name('show');
            Route::get('/checkout', 'checkout')->name('checkout');
            Route::post('/callback', 'callback')->middleware('throttle:20,1')->name('callback');
            Route::post('/cancel', 'cancel')->middleware('throttle:20,1')->name('cancel');
            Route::post('/simulate', 'simulate')->middleware('throttle:20,1')->name('simulate'); // local simulator only
            Route::get('/receipt', 'receipt')->name('receipt');
        });
    });

    // Notification centre: own notifications only; state changes are POST/PUT + CSRF.
    Route::controller(CustomerNotificationController::class)->prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/read-all', 'readAll')->middleware('throttle:30,1')->name('read-all');
        Route::get('/settings', 'settings')->name('settings');
        Route::put('/settings', 'updateSettings')->middleware('throttle:20,1')->name('settings.update');
        Route::get('/{notification}', 'show')->whereUuid('notification')->name('show');
        Route::post('/{notification}/read', 'read')->whereUuid('notification')->middleware('throttle:60,1')->name('read');
    });

    // Push device registration (foundation for the future mobile app / web push).
    Route::post('/devices', [CustomerDeviceController::class, 'store'])->middleware('throttle:20,1')->name('devices.store');
    Route::post('/devices/{device}/deactivate', [CustomerDeviceController::class, 'deactivate'])->whereNumber('device')->middleware('throttle:20,1')->name('devices.deactivate');

    Route::controller(CustomerKycController::class)->prefix('kyc')->group(function () {
        Route::get('/', 'show')->name('kyc');
        Route::get('/edit', 'edit')->name('kyc.edit');
        Route::put('/', 'update')->middleware('throttle:20,1')->name('kyc.update');
        // Documents are addressed by type within the customer's own profile — no ids in the URL.
        Route::get('/documents/{type}', 'document')->whereIn('type', array_keys(KycService::DOCUMENTS))->name('kyc.documents.show');
    });

    Route::get('/profile', CustomerProfileController::class)->name('profile');

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});

/*
|--------------------------------------------------------------------------
| Admin area (separate sign-in; admins only)
|--------------------------------------------------------------------------
| Only routes that are built exist. Sections without a route (e.g. Exchange
| Scheme Approval) appear in the sidebar as "coming soon".
*/

// Admin customer management: a route id can only ever resolve to a customer account.
Route::bind('customer', fn (string $value) => User::query()->customers()->whereKey((int) $value)->firstOrFail());

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('admin.guest')->group(function () {
        Route::get('/login', [AdminLoginController::class, 'create'])->name('login');
        // Per-IP ceiling on top of the per-email lockout inside the controller.
        Route::post('/login', [AdminLoginController::class, 'store'])->middleware('throttle:20,1')->name('login.store');
    });

    Route::middleware('admin')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::post('/logout', [AdminLoginController::class, 'destroy'])->name('logout');

        // Gold rates are append-only: no edit/update/delete routes exist by design.
        Route::controller(AdminGoldRateController::class)->prefix('gold-rates')->name('gold-rates.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/history', 'history')->name('history');
        });

        // Plans are archived via deactivate — there is no delete route by design.
        // Admin routes bind plans by id, so editing a slug never breaks admin URLs.
        Route::get('/notifications', [AdminNotificationController::class, 'index'])->name('notifications.index');

        // Company / website details (no credentials). Single settings record set — no create/delete routes.
        Route::controller(AdminSettingsController::class)->prefix('settings')->name('settings.')->group(function () {
            Route::get('/', 'edit')->name('edit');
            Route::put('/', 'update')->middleware('throttle:20,1')->name('update');
        });

        // {customer} resolves to NON-admin users only (binding below): admin accounts are never managed here.
        Route::controller(AdminCustomerController::class)->prefix('customers')->name('customers.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->middleware('throttle:20,1')->name('store');
            Route::get('/{customer}', 'show')->whereNumber('customer')->name('show');
            Route::get('/{customer}/edit', 'edit')->whereNumber('customer')->name('edit');
            Route::match(['put', 'patch'], '/{customer}', 'update')->whereNumber('customer')->name('update');
            Route::post('/{customer}/block', 'block')->whereNumber('customer')->name('block');
            Route::post('/{customer}/unblock', 'unblock')->whereNumber('customer')->name('unblock');
            Route::post('/{customer}/activate', 'activate')->whereNumber('customer')->name('activate');
            Route::post('/{customer}/deactivate', 'deactivate')->whereNumber('customer')->name('deactivate');
        });

        // Read-only list of all customer schemes (with filters).
        Route::get('/schemes', [AdminSchemeController::class, 'index'])->name('schemes.index');

        // Schemes of one customer. {scheme} is resolved WITHIN {customer} (404 otherwise).
        // Admin entry of a customer's KYC (on their behalf). Submission ≠ approval.
        Route::controller(AdminCustomerKycController::class)->prefix('customers/{customer}/kyc')->whereNumber('customer')->name('customers.kyc.')->group(function () {
            Route::get('/', 'edit')->name('edit');
            Route::put('/', 'update')->middleware('throttle:20,1')->name('update');
        });

        Route::controller(AdminCustomerSchemeController::class)->prefix('customers/{customer}/schemes')->whereNumber('customer')->name('customers.schemes.')->group(function () {
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->middleware('throttle:20,1')->name('store');
            Route::get('/{scheme}', 'show')->whereNumber('scheme')->name('show');
        });

        Route::controller(AdminPaymentController::class)->prefix('payments')->name('payments.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{payment}', 'show')->whereNumber('payment')->name('show');
        });

        Route::controller(AdminKycController::class)->prefix('kyc')->name('kyc.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{kyc}', 'show')->whereNumber('kyc')->name('show');
            Route::post('/{kyc}/approve', 'approve')->whereNumber('kyc')->middleware('throttle:30,1')->name('approve');
            Route::post('/{kyc}/reject', 'reject')->whereNumber('kyc')->middleware('throttle:30,1')->name('reject');
            Route::get('/documents/{document}', 'document')->whereNumber('document')->name('documents.show');
        });

        Route::controller(AdminPlanController::class)->prefix('plans')->name('plans.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{plan:id}', 'show')->whereNumber('plan')->name('show');
            Route::get('/{plan:id}/edit', 'edit')->whereNumber('plan')->name('edit');
            Route::match(['put', 'patch'], '/{plan:id}', 'update')->whereNumber('plan')->name('update');
            Route::post('/{plan:id}/activate', 'activate')->whereNumber('plan')->name('activate');
            Route::post('/{plan:id}/deactivate', 'deactivate')->whereNumber('plan')->name('deactivate');
        });
    });
});
