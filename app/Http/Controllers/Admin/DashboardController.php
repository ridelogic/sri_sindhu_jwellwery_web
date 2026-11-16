<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Enums\CustomerPlanStatus;
use App\Enums\KycStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\CustomerPlan;
use App\Models\GoldRate;
use App\Models\KycProfile;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\GoldRateService;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin dashboard. Every figure is read directly from the database — there
 * are no placeholder statistics and no demo fallback here: empty tables
 * show 0, and a missing gold rate is shown as "not recorded".
 */
class DashboardController extends Controller
{
    public function __invoke(GoldRateService $goldRates): View
    {
        $rates = collect($goldRates->today()); // database only — never the website's demo fallback

        // Status breakdowns for the summary charts (plain grouped counts).
        $customerStatus = User::query()->customers()->groupBy('status')->pluck(DB::raw('count(*)'), 'status');
        $schemeStatus = CustomerPlan::query()->groupBy('status')->pluck(DB::raw('count(*)'), 'status');
        $paymentStatus = Payment::query()->groupBy('status')->pluck(DB::raw('count(*)'), 'status');
        // Payment groups match the Payments page filters (pending = created + pending).
        $paymentGroup = fn (string $filter) => (int) collect(PaymentController::FILTERS[$filter])->sum(fn (PaymentStatus $s) => $paymentStatus[$s->value] ?? 0);

        return view('admin.dashboard', [
            'breakdowns' => [
                'customers' => [
                    'active' => (int) ($customerStatus[AccountStatus::Active->value] ?? 0),
                    'inactive' => (int) ($customerStatus[AccountStatus::Inactive->value] ?? 0),
                    'blocked' => (int) ($customerStatus[AccountStatus::Blocked->value] ?? 0),
                ],
                'schemes' => [
                    'active' => (int) ($schemeStatus[CustomerPlanStatus::Active->value] ?? 0),
                    'completed' => (int) ($schemeStatus[CustomerPlanStatus::Completed->value] ?? 0),
                    'matured' => (int) ($schemeStatus[CustomerPlanStatus::Matured->value] ?? 0),
                ],
                'payments' => [
                    'success' => $paymentGroup('success'),
                    'pending' => $paymentGroup('pending'),
                    'failed' => $paymentGroup('failed'),
                ],
            ],
            'stats' => [
                'customers' => User::query()->customers()->count(),
                'blockedCustomers' => User::query()->customers()->where('status', \App\Enums\AccountStatus::Blocked->value)->count(),
                'inactiveCustomers' => User::query()->customers()->where('status', \App\Enums\AccountStatus::Inactive->value)->count(),
                'activeSchemes' => CustomerPlan::query()->status(CustomerPlanStatus::Active)->count(),
                'pendingKyc' => KycProfile::query()->where('kyc_status', KycStatus::Submitted->value)->count(), // submitted, awaiting review
                'activePlans' => Plan::query()->active()->count(),
                // Successful payments only — verified with the gateway.
                'todayPayments' => Payment::query()->successful()->where('paid_at', '>=', today())->count(),
                'todayAmountPaise' => (int) Payment::query()->successful()->where('paid_at', '>=', today())->sum('amount_paise'),
                'totalPayments' => Payment::query()->successful()->count(),
            ],
            'rates' => $rates,
            'ratesUpdatedAt' => $rates->filter()->map(fn (GoldRate $rate) => $rate->effectiveAtMoment())->max(),
            'recentCustomers' => User::query()->customers()->latest()->latest('id')->limit(5)->get(['id', 'name', 'email', 'created_at']),
            'recentEnrolments' => CustomerPlan::query()
                ->with(['user:id,name,email', 'plan:id,name'])
                ->latest()->latest('id')
                ->limit(5)
                ->get(),
        ]);
    }
}
