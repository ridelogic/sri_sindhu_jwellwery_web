<?php

namespace App\Http\Controllers;

use App\Services\GoldSavingPlanService;
use App\Services\PageContentService;
use Illuminate\View\View;

class AboutController extends Controller
{
    public function __invoke(PageContentService $pages, GoldSavingPlanService $plans): View
    {
        return view('about.index', [
            'about' => $pages->about(),
            'lowestMonthlyAmount' => $plans->lowestMonthlyAmount(),
        ]);
    }
}
