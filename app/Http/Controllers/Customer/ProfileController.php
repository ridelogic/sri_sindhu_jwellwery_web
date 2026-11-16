<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\CustomerAccountService;
use App\Support\IndianMobile;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only profile for Stage 4. Only safe, customer-owned fields are passed
 * to the view — never the model itself — so no internal or sensitive field
 * (is_admin, password hash, KYC identity data) can leak into the page.
 */
class ProfileController extends Controller
{
    public function __invoke(Request $request, CustomerAccountService $accounts): View
    {
        $customer = $request->user();

        return view('customer.profile', [
            'profile' => [
                'name' => $customer->name,
                'mobile' => IndianMobile::format($customer->mobile_number),
                'email' => $customer->email,
                'member_since' => $customer->created_at,
                'kyc_status' => $accounts->kycStatus($customer),
                'schemes' => $customer->customerPlans()->count(),
            ],
        ]);
    }
}
