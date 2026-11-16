<?php

namespace App\Http\Controllers;

use App\Services\FaqService;
use Illuminate\View\View;

class FaqController extends Controller
{
    public function __invoke(FaqService $faqs): View
    {
        return view('faq.index', ['categories' => $faqs->categories()]);
    }
}
