<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomerPlan;
use App\Services\CustomerAccountService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The signed-in customer's own scheme enrolments.
 *
 * Schemes are addressed by scheme number (not sequential ids) and are always
 * looked up WITHIN the customer's own enrolments — another customer's scheme
 * number simply returns 404 — with a policy check as a second layer.
 */
class SchemeController extends Controller
{
    public function index(Request $request, CustomerAccountService $accounts): View
    {
        $schemes = $accounts->schemes($request->user());

        return view('customer.schemes.index', [
            'schemes' => $schemes,
            'progress' => $accounts->progressFor($schemes),
        ]);
    }

    public function show(Request $request, string $schemeNumber, CustomerAccountService $accounts): View
    {
        /** @var CustomerPlan $scheme */
        $scheme = $request->user()->customerPlans()
            ->with('plan:id,name,slug,status,description')
            ->where('scheme_number', $schemeNumber)
            ->firstOrFail();

        Gate::authorize('view', $scheme);

        return view('customer.schemes.show', [
            'scheme' => $scheme,
            'progress' => $accounts->schemeProgress($scheme),
            'payments' => $scheme->payments()->with('goldTransaction')->latest('initiated_at')->latest('id')->get(),
            'eligibility' => app(PaymentService::class)->eligibility($request->user(), $scheme),
        ]);
    }
}
