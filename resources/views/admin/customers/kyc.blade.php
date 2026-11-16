@extends('layouts.admin')

@section('title', 'KYC — '.$customer->name)
@section('form_page', '1')

@use('App\Enums\KycStatus')
@use('App\Enums\KycDocumentType')
@use('App\Http\Requests\Customer\SaveKycRequest')

@php
    $rejected = $profile->kyc_status === KycStatus::Rejected;
    $maxMb = SaveKycRequest::MAX_UPLOAD_KB / 1024;
@endphp

@section('content')
    <form method="POST" action="{{ route('admin.customers.kyc.update', $customer) }}" enctype="multipart/form-data" novalidate data-admin-kyc-form>
        @csrf
        @method('PUT')

        <x-admin.form-card :title="($rejected ? 'Update KYC' : 'Add KYC').' — '.$customer->name" :back="route('admin.customers.show', $customer)" back-label="Back to Customer">
            @if ($errors->any())
                <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-800" role="alert">
                    {{ $errors->first('kyc') ?: 'Please correct the highlighted fields. Nothing has been submitted yet.' }}
                </div>
            @endif

            <div class="mb-4 rounded-md border border-ad-line bg-ad-panel px-3 py-2.5 text-xs leading-relaxed text-ad-muted" data-kyc-admin-note>
                Enter the details exactly as they appear on the customer's documents. <strong class="text-ad-ink">Submitting does not approve the KYC</strong> —
                it moves to <strong class="text-ad-ink">Pending Review</strong>, and approval or rejection is a separate step on the KYC review page.
                Every save is recorded in the KYC audit log.
            </div>

            @if ($rejected && $profile->rejection_reason)
                <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-800" data-rejection-reason>
                    <strong class="font-semibold">Previously rejected:</strong> {{ $profile->rejection_reason }}
                </div>
            @endif

            <x-admin.form-grid>
                {{-- Personal --}}
                <x-form.input name="full_name" label="Full Name (as on PAN)" :value="$profile->full_name ?? $customer->name" maxlength="100" autocomplete="off" required />
                <x-form.input name="date_of_birth" label="Date of Birth" type="date" :value="$profile->date_of_birth?->toDateString()" :max="now()->subYears(18)->toDateString()" min="1900-01-01" required />
                <x-form.select name="gender" label="Gender" :options="$genders" :selected="$profile->gender" placeholder="Select" required />
                <x-form.input name="pincode" label="Pincode" :value="$profile->pincode" inputmode="numeric" maxlength="6" placeholder="600001" required />

                {{-- Address --}}
                <x-form.input name="address_line1" label="Address" :value="$profile->address_line1" maxlength="255" placeholder="House number, street" required />
                <x-form.input name="address_line2" label="Address Line 2 (optional)" :value="$profile->address_line2" maxlength="255" placeholder="Area, landmark" />
                <x-form.input name="city" label="City" :value="$profile->city" maxlength="100" required />
                <x-form.select name="state" label="State" :options="$states" :selected="$profile->state" placeholder="Select state" required />

                {{-- Identity numbers are never pre-filled; blank keeps what is on file --}}
                <x-form.input name="aadhaar_last4" label="Aadhaar — Last 4 Digits" inputmode="numeric" maxlength="4" autocomplete="off" placeholder="1234"
                    :hint="$profile->aadhaar_last4 ? 'On file: '.$profile->maskedAadhaar().'. Leave blank to keep it.' : 'Only the last 4 digits are stored — never the full Aadhaar number.'"
                    :required="! $profile->aadhaar_last4" />
                <x-form.input name="pan_number" label="PAN" maxlength="10" autocomplete="off" placeholder="ABCDE1234F" class="[&_input]:uppercase"
                    :hint="$profile->pan_number ? 'On file: '.$profile->maskedPan().'. Leave blank to keep it.' : '10 characters, e.g. ABCDE1234F. Stored encrypted.'"
                    :required="! $profile->pan_number" />

                {{-- Documents --}}
                <div class="md:col-span-2" data-kyc-documents>
                    <p data-form-label>Documents</p>
                    <p class="mt-0.5 text-xs text-ad-muted">PDF, JPG or PNG, up to {{ $maxMb }} MB each. Stored in private storage — never publicly accessible.</p>
                    <div class="mt-3 grid gap-3 md:grid-cols-3">
                        @foreach ($documents as $type => $slot)
                            @php
                                $document = $profile->documentOfType(KycDocumentType::from($type));
                                $error = $errors->first("documents.$type");
                            @endphp
                            <div @class(['rounded-md border p-3', 'border-red-300' => $error, 'border-ad-line bg-ad-panel' => ! $error]) data-document-slot="{{ $type }}">
                                <label for="doc-{{ $type }}" class="block text-sm font-semibold text-ad-ink">
                                    {{ $slot['label'] }}
                                    @if ($slot['required'])<span class="text-ad-gold-soft" aria-hidden="true">*</span>@else<span class="text-xs font-normal text-ad-muted">(optional)</span>@endif
                                </label>
                                @if ($document)
                                    <p class="mt-1 text-xs text-ad-muted" data-document-on-file>
                                        On file · {{ $document->fileKind() }}@if ($document->humanSize()) · {{ $document->humanSize() }}@endif ·
                                        <a href="{{ route('admin.kyc.documents.show', $document) }}" target="_blank" rel="noopener" class="font-semibold text-ad-gold underline underline-offset-2">View</a>
                                    </p>
                                @else
                                    <p class="mt-1 text-xs text-ad-muted">{{ $slot['hint'] }}</p>
                                @endif
                                <input id="doc-{{ $type }}" name="documents[{{ $type }}]" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                    @if ($error) aria-invalid="true" aria-describedby="doc-{{ $type }}-error" @endif
                                    class="mt-2 block w-full min-w-0 cursor-pointer text-xs text-ad-muted file:mr-3 file:cursor-pointer file:rounded file:border file:border-ad-gold-soft/60 file:bg-ad-dark file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-ad-gold">
                                @if ($error)
                                    <p id="doc-{{ $type }}-error" class="mt-1 text-xs text-red-700">{{ $error }}</p>
                                @elseif ($document)
                                    <p class="mt-1 text-[0.7rem] text-ad-muted">Choose a file only to replace it.</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-admin.form-grid>

            @if ($missing !== [])
                <p class="mt-4 text-xs text-ad-muted" data-kyc-missing>Still needed before submitting: {{ implode(', ', $missing) }}.</p>
            @endif

            <x-slot:actions>
                <a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" name="intent" value="draft" class="btn btn-secondary" formnovalidate data-submit-once>Save Draft</button>
                <button type="submit" name="intent" value="submit" class="btn btn-primary" data-submit-once>Submit for Review</button>
            </x-slot:actions>
        </x-admin.form-card>
    </form>
@endsection
