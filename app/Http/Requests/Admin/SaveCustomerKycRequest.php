<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Customer\SaveKycRequest;
use App\Models\User;

/**
 * An administrator entering a customer's existing KYC details and documents.
 *
 * Exactly the same validation as the customer's own KYC form (fields, PAN /
 * Aadhaar-last-4 formats, PDF/JPG/PNG uploads up to 5 MB, required documents on
 * submission). The customer is the {customer} route binding — which only ever
 * resolves to a non-admin account — never a user id from the request body.
 * Status and review fields are never accepted.
 */
class SaveCustomerKycRequest extends SaveKycRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin() && $this->kycOwner() !== null;
    }

    protected function kycOwner(): ?User
    {
        $customer = $this->route('customer');

        return $customer instanceof User && ! $customer->isAdmin() ? $customer : null;
    }

    public function messages(): array
    {
        return ['date_of_birth.before_or_equal' => 'The customer must be at least 18 years old.'] + parent::messages();
    }
}
