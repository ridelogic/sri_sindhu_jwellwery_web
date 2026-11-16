@extends('layouts.app')

@section('title', 'Complete Your KYC | Sri Sindhu Jewellery')
@section('meta_description', 'Complete your Sri Sindhu Jewellery KYC verification.')
@section('robots', 'noindex, nofollow')

@use('App\Enums\KycStatus')
@use('App\Enums\KycDocumentType')
@use('App\Http\Requests\Customer\SaveKycRequest')

@php
    $rejected = $profile->kyc_status === KycStatus::Rejected;
    $maxMb = SaveKycRequest::MAX_UPLOAD_KB / 1024;
    $section = 'border border-line bg-white';
    $sectionHead = 'flex items-baseline gap-4 border-b border-line px-6 py-5 sm:px-8';
    $sectionBody = 'grid gap-6 px-6 py-6 sm:grid-cols-2 sm:px-8 sm:py-8';
@endphp

@section('content')
    <x-customer-shell
        :title="$rejected ? 'Update Your KYC' : 'Complete Your KYC'"
        emphasis="KYC"
        text="Enter your details exactly as they appear on your identity documents."
    >
        @if ($rejected && $profile->rejection_reason)
            <div class="mb-8 border-l-2 border-red-400 bg-red-50 px-5 py-4" data-rejection-reason>
                <p class="text-[0.66rem] font-semibold tracking-[0.2em] text-red-800 uppercase">Your KYC needs attention</p>
                <p class="mt-1 text-sm leading-relaxed text-red-900">{{ $profile->rejection_reason }}</p>
            </div>
        @endif

        <form method="POST" action="{{ route('customer.kyc.update') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]" data-kyc-form>
            @csrf
            @method('PUT')

            <div class="min-w-0 space-y-6">
                {{-- Personal details --}}
                <section class="{{ $section }}" aria-labelledby="personal-heading">
                    <div class="{{ $sectionHead }}">
                        <span class="font-serif text-xl text-gold-deep italic">01</span>
                        <h2 id="personal-heading" class="text-2xl">Personal Details</h2>
                    </div>
                    <div class="{{ $sectionBody }}">
                        <x-form.input name="full_name" label="Full Name (as on PAN)" :value="$profile->full_name" autocomplete="name" maxlength="100" class="sm:col-span-2" required />
                        <x-form.input name="date_of_birth" label="Date of Birth" type="date" :value="$profile->date_of_birth?->toDateString()" :max="now()->subYears(18)->toDateString()" min="1900-01-01" autocomplete="bday" required />
                        <x-form.select name="gender" label="Gender" :options="$genders" :selected="$profile->gender" placeholder="Select" required />
                    </div>
                </section>

                {{-- Address --}}
                <section class="{{ $section }}" aria-labelledby="address-heading">
                    <div class="{{ $sectionHead }}">
                        <span class="font-serif text-xl text-gold-deep italic">02</span>
                        <h2 id="address-heading" class="text-2xl">Contact &amp; Address</h2>
                    </div>
                    <div class="{{ $sectionBody }}">
                        <x-form.input name="address_line1" label="Address" :value="$profile->address_line1" autocomplete="address-line1" maxlength="255" placeholder="House number, street" class="sm:col-span-2" required />
                        <x-form.input name="address_line2" label="Address Line 2" :value="$profile->address_line2" autocomplete="address-line2" maxlength="255" placeholder="Area, landmark (optional)" class="sm:col-span-2" />
                        <x-form.input name="city" label="City" :value="$profile->city" autocomplete="address-level2" maxlength="100" required />
                        <x-form.select name="state" label="State" :options="$states" :selected="$profile->state" placeholder="Select state" required />
                        <x-form.input name="pincode" label="Pincode" :value="$profile->pincode" inputmode="numeric" maxlength="6" autocomplete="postal-code" placeholder="600001" required />
                    </div>
                </section>

                {{-- Identity (numbers are never pre-filled; blank keeps what is on file) --}}
                <section class="{{ $section }}" aria-labelledby="identity-heading">
                    <div class="{{ $sectionHead }}">
                        <span class="font-serif text-xl text-gold-deep italic">03</span>
                        <h2 id="identity-heading" class="text-2xl">Identity</h2>
                    </div>
                    <div class="{{ $sectionBody }}">
                        <x-form.input name="aadhaar_last4" label="Aadhaar — Last 4 Digits" inputmode="numeric" maxlength="4" autocomplete="off" placeholder="1234"
                            :hint="$profile->aadhaar_last4 ? 'On file: '.$profile->maskedAadhaar().'. Leave blank to keep it.' : 'Enter only the last 4 digits. We never store your full Aadhaar number.'"
                            :required="! $profile->aadhaar_last4" />
                        <x-form.input name="pan_number" label="PAN" maxlength="10" autocomplete="off" placeholder="ABCDE1234F" class="[&_input]:uppercase"
                            :hint="$profile->pan_number ? 'On file: '.$profile->maskedPan().'. Leave blank to keep it.' : '10 characters, e.g. ABCDE1234F. Stored encrypted.'"
                            :required="! $profile->pan_number" />
                    </div>
                </section>

                {{-- Documents --}}
                <section class="{{ $section }}" aria-labelledby="documents-heading">
                    <div class="{{ $sectionHead }}">
                        <span class="font-serif text-xl text-gold-deep italic">04</span>
                        <h2 id="documents-heading" class="text-2xl">Documents</h2>
                    </div>
                    <div class="space-y-6 px-6 py-6 sm:px-8 sm:py-8">
                        <p class="text-sm text-taupe">PDF, JPG or PNG, up to {{ $maxMb }} MB each. Make sure every detail is clear and readable.</p>
                        @foreach ($documents as $type => $slot)
                            @php
                                $document = $profile->documentOfType(KycDocumentType::from($type));
                                $field = "documents.$type";
                                $error = $errors->first($field);
                            @endphp
                            <div class="border border-line p-5" data-document-slot="{{ $type }}">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <label for="doc-{{ $type }}" class="font-semibold">
                                            {{ $slot['label'] }}
                                            @if ($slot['required'])<span class="text-champagne" aria-hidden="true">*</span>@else<span class="text-xs font-normal text-taupe">(optional)</span>@endif
                                        </label>
                                        <p class="mt-1 text-xs text-taupe">{{ $slot['hint'] }}</p>
                                    </div>
                                    @if ($document)
                                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-800" data-document-on-file>
                                            <x-icon name="check" class="size-3.5" stroke="2" /> On file
                                        </span>
                                    @endif
                                </div>

                                @if ($document)
                                    <p class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-taupe">
                                        <span>{{ $document->fileKind() }}@if ($document->humanSize()) · {{ $document->humanSize() }}@endif · Uploaded {{ $document->updated_at?->format('d M Y') }}</span>
                                        <a href="{{ route('customer.kyc.documents.show', $type) }}" target="_blank" rel="noopener" class="font-semibold text-gold-deep underline underline-offset-4">View</a>
                                    </p>
                                @endif

                                <input id="doc-{{ $type }}" name="documents[{{ $type }}]" type="file"
                                    accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                    @if ($slot['required'] && ! $document) required @endif
                                    @if ($error) aria-invalid="true" aria-describedby="doc-{{ $type }}-error" @endif
                                    @class([
                                        'mt-4 block w-full min-w-0 cursor-pointer border border-dashed bg-ivory/60 text-sm text-taupe transition-colors',
                                        'file:mr-4 file:cursor-pointer file:border-0 file:bg-obsidian file:px-4 file:py-3 file:text-[0.68rem] file:font-semibold file:tracking-[0.16em] file:text-ivory file:uppercase hover:border-champagne',
                                        'border-red-400' => $error,
                                        'border-obsidian/25' => ! $error,
                                    ])>
                                @if ($document)
                                    <p class="mt-2 text-xs text-taupe">Choose a new file only if you want to replace this document.</p>
                                @endif
                                @if ($error)
                                    <p id="doc-{{ $type }}-error" class="mt-2 text-sm text-red-700">{{ $error }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>

            <aside class="h-fit space-y-4 lg:sticky lg:top-28">
                <div class="border border-line bg-white px-6 py-6">
                    <h2 class="font-sans text-[0.66rem] font-semibold tracking-[0.24em] text-taupe uppercase">Ready to submit?</h2>
                    @if ($missing !== [])
                        <p class="mt-3 text-sm text-taupe">Still needed:</p>
                        <ul class="mt-2 space-y-1.5 text-sm" data-kyc-missing>
                            @foreach ($missing as $item)
                                <li class="flex gap-2"><span class="mt-2 size-1.5 shrink-0 rounded-full bg-champagne"></span>{{ $item }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-3 text-sm leading-relaxed text-taupe">Once submitted, your details are locked while our team reviews them.</p>
                    @endif

                    <div class="mt-6 grid gap-3">
                        <button type="submit" name="intent" value="submit" class="btn btn-gold w-full" data-submit-once>
                            {{ $rejected ? 'Resubmit for Review' : 'Submit for Review' }}
                            <x-icon name="arrow-right" class="size-4" />
                        </button>
                        <button type="submit" name="intent" value="draft" class="btn btn-ghost-dark w-full" formnovalidate data-submit-once>Save Draft</button>
                    </div>
                    <a href="{{ route('customer.kyc') }}" class="mt-4 inline-block text-xs text-taupe underline underline-offset-4 hover:text-obsidian">Cancel</a>
                </div>
                <div class="bg-obsidian px-6 py-5 text-ivory">
                    <x-icon name="lock" class="size-5 text-champagne" />
                    <p class="mt-3 text-sm leading-relaxed text-ivory/80">Your PAN is encrypted, only the last 4 digits of your Aadhaar are stored, and your documents are kept in private storage.</p>
                </div>
            </aside>
        </form>
    </x-customer-shell>
@endsection
