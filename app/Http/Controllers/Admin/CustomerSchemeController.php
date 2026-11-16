<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerPlan;
use App\Models\Plan;
use App\Models\User;
use App\Services\CustomerAccountService;
use App\Services\CustomerManagementService;
use App\Support\IndianMobile;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin enrolment of an existing customer into an active plan, and a
 * read-only view of one of their schemes.
 *
 * {customer} only resolves to non-admin users; {scheme} is always looked up
 * WITHIN that customer's own enrolments, so /admin/customers/1/schemes/999
 * returns 404 unless scheme 999 belongs to customer 1. Only the plan id comes
 * from the request — every financial value is copied from the plan row by the
 * existing EnrolmentService.
 */
class CustomerSchemeController extends Controller
{
    public function create(User $customer): View
    {
        return view('admin.customers.schemes.create', [
            'customer' => $customer,
            'mobile' => IndianMobile::format($customer->mobile_number),
            'plans' => Plan::query()->active()->ordered()->get(),
            'existing' => $customer->customerPlans()->with('plan:id,name')->latest('id')->get(),
            'token' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request, User $customer): RedirectResponse
    {
        $data = $request->validate([
            // Must exist AND be active — a manually submitted inactive plan id is rejected here.
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('status', true)],
            'enrolment_token' => ['required', 'uuid'],
        ], [
            'plan_id.required' => 'Please choose a plan.',
            'plan_id.exists' => 'Please choose one of the active plans.',
        ]);

        $plan = Plan::query()->findOrFail($data['plan_id']);

        // The service re-checks the plan is active inside a locked transaction.
        [$scheme, $created] = app(CustomerManagementService::class)->enrol($request->user(), $customer, $plan, $data['enrolment_token']);

        return redirect()->route('admin.customers.schemes.show', [$customer, $scheme->id])
            ->with('status', $created
                ? "{$customer->name} has been enrolled in {$plan->name}. Scheme number {$scheme->scheme_number}."
                : "This enrolment was already received (scheme {$scheme->scheme_number}). No duplicate was created.");
    }

    public function show(User $customer, int $scheme, CustomerAccountService $accounts): View
    {
        /** @var CustomerPlan $model */
        $model = $customer->customerPlans()->with('plan:id,name,slug,status')->whereKey($scheme)->firstOrFail();

        $progress = $accounts->schemeProgress($model);
        $remainingPaise = max(0, Money::toPaise($model->total_commitment) - Money::toPaise($progress['amount_paid']));

        return view('admin.customers.schemes.show', [
            'customer' => $customer,
            'mobile' => IndianMobile::format($customer->mobile_number),
            'scheme' => $model,
            'progress' => $progress,
            'remaining' => Money::fromPaise($remainingPaise),
            'payments' => $model->payments()->with('goldTransaction')->latest('initiated_at')->latest('id')->get(),
            'goldTransactions' => $model->goldTransactions()->with('payment:id,reference')->latest('transaction_date')->latest('id')->get(),
            // Admin actions on this customer that concern this scheme (e.g. the enrolment itself).
            'activity' => $customer->customerAuditLogs()->with('actor:id,name')
                ->where('note', 'like', '%'.$model->scheme_number.'%')->get(),
        ]);
    }
}
