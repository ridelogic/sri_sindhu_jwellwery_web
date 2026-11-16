<?php

namespace App\Http\Requests\Customer;

use App\Models\User;
use App\Support\IndianMobile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Customer registration (step 1) + first plan selection. Passwordless: the
 * mobile number is verified by OTP before the account is created.
 *
 * Only identity fields and a plan SLUG are accepted. No financial value
 * (amount, duration, total, scheme number, status) is read from the request.
 */
class RegisterCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => Str::lower(trim((string) $this->input('email'))),
            'mobile_number' => IndianMobile::normalise($this->input('mobile')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'mobile' => ['required', 'string', 'max:20'],
            'email' => ['required', 'string', 'email', 'max:150', Rule::unique('users', 'email')],
            'plan' => ['required', 'string', Rule::exists('plans', 'slug')->where('status', true)],
            'terms' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter your full name.',
            'mobile.required' => 'Please enter your mobile number.',
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'An account with this email already exists. Please log in instead.',
            'plan.required' => 'Please choose a gold saving plan.',
            'plan.exists' => 'Please choose one of the available plans.',
            'terms.accepted' => 'Please accept the terms to continue.',
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
                } elseif (User::query()->where('mobile_number', $mobile)->exists()) {
                    $validator->errors()->add('mobile', 'An account with this mobile number already exists. Please log in instead.');
                }
            },
        ];
    }
}
