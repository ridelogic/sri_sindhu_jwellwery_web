<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\EnrolmentService;
use App\Services\GoldSavingPlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Browse active plans and join another scheme.
 * Plan data comes from GoldSavingPlanService (no duplication); only active
 * database plans are joinable.
 */
class PlanController extends Controller
{
    private function plans(): GoldSavingPlanService
    {
        return app(GoldSavingPlanService::class);
    }

    public function index(): View
    {
        $plans = $this->plans()->joinable();

        return view('customer.plans.index', [
            'plans' => $plans,
            'benefits' => collect($plans)->mapWithKeys(fn ($plan) => [$plan['slug'] => $this->plans()->benefitsFor($plan)]),
        ]);
    }

    public function show(Request $request, string $slug): View
    {
        $plan = $this->plans()->findJoinable($slug);

        abort_if($plan === null, 404); // inactive or unknown plans are not joinable

        return view('customer.plans.show', [
            'plan' => $plan,
            'benefits' => $this->plans()->benefitsFor($plan),
            'notes' => $this->plans()->importantNotes(),
            'existingCount' => $request->user()->customerPlans()->whereHas('plan', fn ($q) => $q->where('slug', $slug))->count(),
            'enrolmentToken' => (string) Str::uuid(), // one-time token against double submission
        ]);
    }

    public function join(Request $request, string $slug, EnrolmentService $enrolments): RedirectResponse
    {
        $request->validate(['enrolment_token' => ['required', 'string', 'uuid']]);

        $plan = Plan::query()->where('slug', $slug)->first();

        abort_if($plan === null, 404);

        if (! $plan->status) {
            return redirect()->route('customer.plans.index')
                ->withErrors(['plan' => 'This plan is no longer open for new enrolments. Please choose another plan.']);
        }

        // Only the plan identity comes from the request; all financial values come from the database.
        [$scheme, $created] = $enrolments->enrol($request->user(), $plan, $request->input('enrolment_token'));

        return redirect()->route('customer.schemes.show', $scheme->scheme_number)
            ->with('status', $created
                ? "You have joined {$plan->name}. Your scheme number is {$scheme->scheme_number}."
                : "Your enrolment in {$plan->name} was already received (scheme {$scheme->scheme_number}).");
    }
}
