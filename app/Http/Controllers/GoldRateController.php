<?php

namespace App\Http\Controllers;

use App\Services\GoldRateService;
use App\Services\GoldSavingPlanService;
use Illuminate\View\View;

class GoldRateController extends Controller
{
    public function __invoke(GoldRateService $goldRates, GoldSavingPlanService $plans): View
    {
        return view('gold-rate.index', [
            'rates' => $goldRates->current(),
            'history' => $goldRates->history(),
            'updatedAt' => $goldRates->updatedAt(),
            'isSample' => $goldRates->isSample(),
            'lowestMonthlyAmount' => $plans->lowestMonthlyAmount(),
        ]);
    }
}
