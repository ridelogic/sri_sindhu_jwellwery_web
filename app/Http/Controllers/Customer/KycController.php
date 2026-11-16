<?php

namespace App\Http\Controllers\Customer;

use App\Enums\KycStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\SaveKycRequest;
use App\Models\KycDocument;
use App\Services\KycService;
use App\Support\IndianStates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The signed-in customer's own KYC. The customer is always taken from the
 * customer guard — no user or profile id is ever read from the request, and
 * documents are addressed by type within the customer's own profile.
 */
class KycController extends Controller
{
    /** Resolved per request — see GoldSavingController for why not via the constructor. */
    private function kyc(): KycService
    {
        return app(KycService::class);
    }

    public function show(Request $request): View
    {
        $profile = $this->kyc()->profileFor($request->user());

        return view('customer.kyc.show', [
            'profile' => $profile,
            'status' => $profile->kyc_status,
            'documents' => KycService::DOCUMENTS,
        ]);
    }

    public function edit(Request $request): View|RedirectResponse
    {
        $profile = $this->kyc()->profileFor($request->user());

        if (! $profile->isEditableByCustomer()) {
            return redirect()->route('customer.kyc')->with('status', $profile->kyc_status === KycStatus::Verified
                ? 'Your KYC has been verified. Please contact our team if your details have changed.'
                : 'Your KYC is currently under review and cannot be changed.');
        }

        return view('customer.kyc.edit', [
            'profile' => $profile,
            'documents' => KycService::DOCUMENTS,
            'genders' => KycService::GENDERS,
            'states' => IndianStates::options(),
            'missing' => $profile->exists ? $this->kyc()->missingForSubmission($profile) : [],
        ]);
    }

    public function update(SaveKycRequest $request): RedirectResponse
    {
        $submit = $request->isSubmission();

        $this->kyc()->save($request->user(), $request->details(), $request->uploads(), $submit);

        return $submit
            ? redirect()->route('customer.kyc')->with('status', 'KYC submitted successfully. Our team will review your documents.')
            : redirect()->route('customer.kyc.edit')->with('status', 'Your KYC draft has been saved. Submit it for review when you are ready.');
    }

    public function document(Request $request, string $type): StreamedResponse
    {
        /** @var KycDocument $document */
        $document = KycDocument::query()
            ->where('user_id', $request->user()->id)
            ->where('document_type', $type)
            ->firstOrFail();

        Gate::authorize('view', $document);

        return $this->kyc()->documentResponse($document, $request->boolean('download'));
    }
}
