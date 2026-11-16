<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\CustomerAccountService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CustomerAccountService $accounts): View
    {
        $customer = $request->user();
        $schemes = $accounts->schemes($customer);

        return view('customer.dashboard', [
            'customer' => $customer,
            'schemes' => $schemes,
            'summary' => $accounts->summary($customer, $schemes),
            'progress' => $accounts->progressFor($schemes),
            'latestNotifications' => $customer->notifications()->limit(3)->get(),
        ]);
    }
}
