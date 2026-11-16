<?php

namespace App\Http\Controllers;

use App\Services\HomepageContentService;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(HomepageContentService $content): View
    {
        return view('home.index', [
            'hero' => $content->hero(),
            'goldRates' => $content->goldRates(),
            'goldSaving' => $content->goldSaving(),
            'editorials' => $content->editorials(),
            'pillars' => $content->pillars(),
            'savingSteps' => $content->savingSteps(),
            'appPreview' => $content->appPreview(),
            'stats' => $content->stats(),
            'faqs' => $content->faqs(),
            'finalCta' => $content->finalCta(),
        ]);
    }
}
