<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * A rejection must tell the customer what to fix. The reason is shown to the
 * customer, so it must not contain internal notes.
 */
class RejectKycRequest extends FormRequest
{
    public const MAX_REASON = 500;

    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('rejection_reason'))) {
            $this->merge(['rejection_reason' => Str::squish($this->input('rejection_reason'))]);
        }
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'min:5', 'max:'.self::MAX_REASON],
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Please enter a reason so the customer knows what to fix.',
            'rejection_reason.min' => 'Please give a little more detail for the customer.',
        ];
    }
}
