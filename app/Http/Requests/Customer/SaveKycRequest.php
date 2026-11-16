<?php

namespace App\Http\Requests\Customer;

use App\Models\KycProfile;
use App\Services\KycService;
use App\Support\IndianStates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Customer KYC details + documents.
 *
 * "draft" accepts a partly completed form (anything entered must still be
 * valid); "submit" requires every field and every required document, counting
 * documents already on file. The customer is always the signed-in user —
 * user_id, status and review fields are never read from the request.
 */
class SaveKycRequest extends FormRequest
{
    public const MAX_UPLOAD_KB = 5120;

    public const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    public const ALLOWED_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    private const NAME_PATTERN = "/^[\pL\s.'-]+$/u";

    private ?KycProfile $profile = null;

    public function authorize(): bool
    {
        return $this->user() !== null && ! $this->user()->isAdmin();
    }

    public function isSubmission(): bool
    {
        return $this->input('intent') === 'submit';
    }

    protected function prepareForValidation(): void
    {
        $clean = fn ($value) => is_string($value) ? Str::squish($value) : $value;
        $compact = fn ($value) => is_string($value) ? preg_replace('/[\s-]+/', '', $value) : $value;

        $this->merge([
            'full_name' => $clean($this->input('full_name')),
            'address_line1' => $clean($this->input('address_line1')),
            'address_line2' => $clean($this->input('address_line2')),
            'city' => $clean($this->input('city')),
            'pincode' => $compact($this->input('pincode')),
            'aadhaar_last4' => $compact($this->input('aadhaar_last4')),
            'pan_number' => is_string($this->input('pan_number')) ? Str::upper($compact($this->input('pan_number'))) : $this->input('pan_number'),
        ]);
    }

    public function rules(): array
    {
        $submit = $this->isSubmission();
        $profile = $this->existingProfile();
        $required = fn (bool $alreadyOnFile = false) => $submit && ! $alreadyOnFile ? 'required' : 'nullable';

        $rules = [
            'intent' => ['required', Rule::in(['draft', 'submit'])],
            'full_name' => [$required(), 'string', 'min:2', 'max:100', 'regex:'.self::NAME_PATTERN],
            'date_of_birth' => [$required(), 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:'.now()->subYears(18)->toDateString()],
            'gender' => [$required(), Rule::in(array_keys(KycService::GENDERS))],
            'address_line1' => [$required(), 'string', 'min:3', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => [$required(), 'string', 'min:2', 'max:100', 'regex:'.self::NAME_PATTERN],
            'state' => [$required(), Rule::in(IndianStates::ALL)],
            'pincode' => [$required(), 'regex:/^[1-9][0-9]{5}$/'],
            // Never the full Aadhaar number — exactly 4 digits. Blank keeps the value on file.
            'aadhaar_last4' => [$required(filled($profile?->aadhaar_last4)), 'regex:/^\d{4}$/'],
            'pan_number' => [$required(filled($profile?->pan_number)), 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
            'documents' => ['nullable', 'array'],
        ];

        foreach (KycService::DOCUMENTS as $type => $slot) {
            $onFile = $profile?->documents->contains(fn ($document) => $document->document_type->value === $type) ?? false;

            // "extensions" checks the client filename; "mimes"/"mimetypes" check the detected file content.
            $rules["documents.$type"] = [
                $slot['required'] ? $required($onFile) : 'nullable',
                'file',
                'extensions:'.implode(',', self::ALLOWED_EXTENSIONS),
                'mimes:'.implode(',', self::ALLOWED_EXTENSIONS),
                'mimetypes:'.implode(',', self::ALLOWED_MIME_TYPES),
                'max:'.self::MAX_UPLOAD_KB,
            ];
        }

        return $rules;
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $unknown = array_diff(array_keys((array) $this->file('documents', [])), array_keys(KycService::DOCUMENTS));
                if ($unknown !== []) {
                    $validator->errors()->add('documents', 'Unsupported document type.');
                }
            },
        ];
    }

    public function messages(): array
    {
        $types = 'Upload a PDF, JPG or PNG file.';
        $size = 'Each file must be '.(self::MAX_UPLOAD_KB / 1024).' MB or smaller.';

        $messages = [
            'full_name.regex' => 'Enter your name using letters only.',
            'city.regex' => 'Enter a valid city name.',
            'date_of_birth.before_or_equal' => 'You must be at least 18 years old.',
            'date_of_birth.after_or_equal' => 'Enter a valid date of birth.',
            'date_of_birth.date_format' => 'Enter a valid date of birth.',
            'pincode.regex' => 'Enter a valid 6-digit pincode.',
            'aadhaar_last4.regex' => 'Enter only the last 4 digits of your Aadhaar number.',
            'pan_number.regex' => 'Enter a valid PAN, e.g. ABCDE1234F.',
            'state.in' => 'Choose your state from the list.',
        ];

        foreach (array_keys(KycService::DOCUMENTS) as $type) {
            $messages["documents.$type.required"] = 'Please upload this document.';
            $messages["documents.$type.file"] = 'The file could not be uploaded. Please try again.';
            $messages["documents.$type.uploaded"] = 'The file could not be uploaded. Please try again.';
            $messages["documents.$type.extensions"] = $types;
            $messages["documents.$type.mimes"] = $types;
            $messages["documents.$type.mimetypes"] = $types;
            $messages["documents.$type.max"] = $size;
        }

        return $messages;
    }

    public function attributes(): array
    {
        return [
            'full_name' => 'full name',
            'date_of_birth' => 'date of birth',
            'address_line1' => 'address',
            'address_line2' => 'address line 2',
            'aadhaar_last4' => 'Aadhaar last 4 digits',
            'pan_number' => 'PAN',
        ] + collect(KycService::DOCUMENTS)->mapWithKeys(fn ($slot, $type) => ["documents.$type" => $slot['label']])->all();
    }

    /** Only the validated, customer-editable fields — never status or review fields. */
    public function details(): array
    {
        return collect($this->validated())
            ->only(['full_name', 'date_of_birth', 'gender', 'address_line1', 'address_line2', 'city', 'state', 'pincode', 'aadhaar_last4', 'pan_number'])
            ->all();
    }

    /** @return array<string, UploadedFile> */
    public function uploads(): array
    {
        return array_intersect_key((array) $this->file('documents', []), KycService::DOCUMENTS);
    }

    /** Whose KYC is being saved: the signed-in customer (admin entry overrides this). */
    protected function kycOwner(): ?\App\Models\User
    {
        return $this->user();
    }

    private function existingProfile(): ?KycProfile
    {
        return $this->profile ??= $this->kycOwner()?->kycProfile()->with('documents')->first();
    }
}
