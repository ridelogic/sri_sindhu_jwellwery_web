<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Support\IndianMobile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Admin create / edit of a customer's account details.
 *
 * Only name, email and mobile are accepted (no password — customers sign in by OTP).
 * Status (new accounts are always Inactive), admin rights, KYC, schemes,
 * payments and gold are never read from this request. On edit, uniqueness ignores the customer being edited.
 */
class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    private function customer(): ?User
    {
        $customer = $this->route('customer');

        return $customer instanceof User ? $customer : null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::squish((string) $this->input('name')),
            'email' => Str::lower(trim((string) $this->input('email'))),
            'mobile_number' => IndianMobile::normalise($this->input('mobile')),
        ]);
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:150', Rule::unique('users', 'email')->ignore($this->customer()?->id)],
            'mobile' => ['required', 'string', 'max:20'],
        ];

        // No password: customers sign in with their mobile number and a one-time password (OTP).
        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter the customer\'s full name.',
            'email.required' => 'Please enter an email address.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'Another account already uses this email address.',
            'mobile.required' => 'Please enter a mobile number.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('mobile')) {
                    return;
                }

                $mobile = $this->input('mobile_number');

                if ($mobile === null) {
                    $validator->errors()->add('mobile', 'Please enter a valid 10-digit Indian mobile number.');
                } elseif (User::query()->where('mobile_number', $mobile)->whereKeyNot($this->customer()?->id ?? 0)->exists()) {
                    $validator->errors()->add('mobile', 'Another account already uses this mobile number.');
                }
            },
        ];
    }

    /** @return array{name: string, email: string, mobile_number: string} */
    public function details(): array
    {
        return [
            'name' => $this->input('name'),
            'email' => $this->input('email'),
            'mobile_number' => $this->input('mobile_number'),
        ];
    }
}
