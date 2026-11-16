<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContributionFrequency;
use App\Enums\OutputType;
use App\Enums\SchemeType;
use App\Enums\TenureUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlanRequest;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin management of gold saving plans.
 *
 * There is deliberately NO delete action: plans are archived by deactivating
 * them. Editing a plan never touches existing customer_plans, which hold their
 * own snapshot of the amounts agreed at enrolment.
 */
class PlanController extends Controller
{
    public function index(): View
    {
        return view('admin.plans.index', [
            'plans' => Plan::query()->withCustomerCounts()->ordered()->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.plans.create', [
            // Defaults for a NEW plan: Saving, Months tenure, Money output.
            'plan' => new Plan([
                'status' => true,
                'is_featured' => false,
                'scheme_type' => SchemeType::Saving,
                'contribution_frequency' => ContributionFrequency::Monthly,
                'tenure_unit' => TenureUnit::Months,
                'maximum_tenure' => 11,
                'output_type' => OutputType::Money,
                'bonus_applicable' => false,
                'early_exit_allowed' => false,
                'sort_order' => (int) Plan::query()->max('sort_order') + 1,
            ]),
        ]);
    }

    public function store(PlanRequest $request): RedirectResponse
    {
        $plan = Plan::query()->create($request->planAttributes());

        return redirect()->route('admin.plans.show', $plan)->with('status', "Plan “{$plan->name}” created.");
    }

    public function show(Plan $plan): View
    {
        $plan->loadCount([
            'customerPlans as enrolments_count',
        ]);

        return view('admin.plans.show', [
            'plan' => $plan,
            'customersCount' => $plan->customerPlans()->distinct()->count('user_id'),
        ]);
    }

    public function edit(Plan $plan): View
    {
        return view('admin.plans.edit', [
            'plan' => $plan,
            'enrolmentsCount' => $plan->customerPlans()->count(),
        ]);
    }

    public function update(PlanRequest $request, Plan $plan): RedirectResponse
    {
        // Only the plan row changes; customer_plans keep their own snapshot values.
        $plan->update($request->planAttributes());

        return redirect()->route('admin.plans.show', $plan)->with('status', "Plan “{$plan->name}” updated.");
    }

    public function activate(Plan $plan): RedirectResponse
    {
        $plan->update(['status' => true]);

        return back()->with('status', "“{$plan->name}” is now active and offered on the website.");
    }

    public function deactivate(Plan $plan): RedirectResponse
    {
        // Existing enrolments are untouched; the plan is simply no longer offered.
        $plan->update(['status' => false]);

        return back()->with('status', "“{$plan->name}” is now inactive and no longer offered to new customers.");
    }
}
