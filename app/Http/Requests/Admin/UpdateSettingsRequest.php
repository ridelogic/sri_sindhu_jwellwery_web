<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for the admin Settings page (company / website details only).
 */
class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $clean = fn ($value) => is_string($value) ? (trim(preg_replace('/[ \t]+/', ' ', $value)) ?: null) : $value;

        $this->merge([
            'company_name' => $clean($this->input('company_name')),
            'website_name' => $clean($this->input('website_name')),
            'company_phone' => $clean($this->input('company_phone')),
            'company_email' => is_string($this->input('company_email')) ? (mb_strtolower(trim($this->input('company_email'))) ?: null) : $this->input('company_email'),
            'company_address' => is_string($this->input('company_address')) ? (trim(str_replace("\r\n", "\n", $this->input('company_address'))) ?: null) : $this->input('company_address'),
        ]);
    }

    public function rules(): array
    {
        $line = 'not_regex:/[\x00-\x1F\x7F<>]/'; // single line, no control characters or angle brackets

        return [
            'company_name' => ['required', 'string', 'min:2', 'max:120', $line],
            'website_name' => ['required', 'string', 'min:2', 'max:120', $line],
            // Digits with optional leading +, spaces, hyphens and brackets; 7–15 digits in total.
            'company_phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9][0-9 ()\-]*[0-9]$/', function ($attribute, $value, $fail) {
                $digits = strlen(preg_replace('/\D+/', '', (string) $value));
                if ($digits < 7 || $digits > 15) {
                    $fail('The contact phone must contain 7 to 15 digits.');
                }
            }],
            'company_email' => ['nullable', 'string', 'max:150', 'email:rfc'],
            'company_address' => ['nullable', 'string', 'max:500', 'not_regex:/[\x00-\x09\x0B-\x1F\x7F<>]/'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_name.required' => 'Please enter the jewellery / company name.',
            'website_name.required' => 'Please enter the website display name.',
            'company_name.not_regex' => 'The company name may not contain < or > or control characters.',
            'website_name.not_regex' => 'The website display name may not contain < or > or control characters.',
            'company_phone.regex' => 'Enter a phone number using digits, spaces, +, - or brackets, e.g. +91 98765 43210.',
            'company_email.email' => 'Please enter a valid email address.',
            'company_address.not_regex' => 'The address may not contain < or > or control characters.',
        ];
    }

    public function attributes(): array
    {
        return [
            'company_name' => 'company name',
            'website_name' => 'website display name',
            'company_phone' => 'contact phone',
            'company_email' => 'company email',
            'company_address' => 'company address',
        ];
    }

    /** @return array<string, string|null> */
    public function settings(): array
    {
        return $this->safe()->only(['company_name', 'website_name', 'company_phone', 'company_email', 'company_address']);
    }
}
