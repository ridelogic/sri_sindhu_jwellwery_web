<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCustomerKycRequest;
use App\Models\User;
use App\Services\KycService;
use App\Support\IndianStates;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * An administrator adding a customer's KYC on their behalf (e.g. documents
 * collected in the showroom). Uses the same KycService workflow as the
 * customer's own form:
 *
 *  - only a not-submitted or rejected KYC can be entered/changed;
 *  - "Submit for Review" moves it to Pending Review — it is NEVER approved here;
 *    approval/rejection stays on the separate review page (admin.kyc.show);
 *  - documents go to the private "kyc" disk; every save is in the KYC audit log.
 *
 * {customer} only resolves to non-admin accounts (see the route binding).
 */
class CustomerKycController extends Controller
{
    private function kyc(): KycService
    {
        return app(KycService::class);
    }

    public function edit(User $customer): View|RedirectResponse
    {
        $profile = $this->kyc()->profileFor($customer);

        if ($profile->exists && ! $profile->isEditableByCustomer()) {
            return redirect()->route('admin.kyc.show', $profile)
                ->with('status', 'This KYC is '.strtolower($profile->kyc_status->label()).' and cannot be changed. Review it here.');
        }

        return view('admin.customers.kyc', [
            'customer' => $customer,
            'profile' => $profile,
            'documents' => KycService::DOCUMENTS,
            'genders' => KycService::GENDERS,
            'states' => IndianStates::options(),
            'missing' => $profile->exists ? $this->kyc()->missingForSubmission($profile) : [],
        ]);
    }

    public function update(SaveCustomerKycRequest $request, User $customer): RedirectResponse
    {
        $submit = $request->isSubmission();

        $profile = $this->kyc()->save($customer, $request->details(), $request->uploads(), $submit, actor: $request->user());

        return $submit
            ? redirect()->route('admin.kyc.show', $profile)->with('status', "KYC for {$customer->name} submitted for review. It is now Pending Review — approve or reject it here.")
            : redirect()->route('admin.customers.kyc.edit', $customer)->with('status', 'KYC draft saved. Submit it for review when all details and documents are complete.');
    }
}
