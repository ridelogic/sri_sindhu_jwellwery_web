<?php

namespace App\Http\Controllers;

use App\Services\FaqService;
use App\Services\GoldRateService;
use App\Services\GoldSavingPlanService;
use App\Services\HomepageContentService;
use Illuminate\View\View;

class GoldSavingController extends Controller
{
    /*
     * Services are resolved per request (not held by the constructor): Laravel
     * caches controller instances on the route, so a constructor-held, request-
     * scoped service would serve stale plans in long-running processes.
     */
    private function plans(): GoldSavingPlanService
    {
        return app(GoldSavingPlanService::class);
    }

    private function faqs(): FaqService
    {
        return app(FaqService::class);
    }

    public function index(HomepageContentService $home): View
    {
        return view('gold-saving.index', [
            'plans' => $this->plans()->all(),
            'benefits' => $this->plans()->benefits(),
            'notes' => $this->plans()->importantNotes(),
            'savingSteps' => $home->savingSteps(),
            'pillars' => $home->pillars(),
            'faqs' => $this->faqs()->categories(['gold-saving', 'payments']),
            'lowestMonthlyAmount' => $this->plans()->lowestMonthlyAmount(),
        ]);
    }

    public function show(string $slug, GoldRateService $goldRates): View
    {
        $plan = $this->plans()->findForDisplay($slug); // inactive plans stay reachable (no 404)

        abort_if($plan === null, 404);

        return view('gold-saving.show', [
            'plan' => $plan,
            'otherPlans' => array_values(array_filter($this->plans()->all(), fn (array $other) => $other['slug'] !== $slug)),
            'benefits' => $this->plans()->benefitsFor($plan),
            'paymentSteps' => $this->plans()->paymentSteps(),
            'notes' => $this->plans()->importantNotes(),
            'referenceRate' => $goldRates->current()[0],
            'isSampleRate' => $goldRates->isSample(),
            'faqs' => $this->faqs()->categories(['gold-saving']),
        ]);
    }
}
