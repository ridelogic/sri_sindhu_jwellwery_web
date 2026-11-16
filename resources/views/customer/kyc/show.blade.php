@extends('layouts.app')

@section('title', 'KYC Verification | Sri Sindhu Jewellery')
@section('meta_description', 'Your Sri Sindhu Jewellery KYC verification.')
@section('robots', 'noindex, nofollow')

@use('App\Enums\KycStatus')
@use('App\Enums\KycDocumentType')
@use('App\Services\KycService')

@php
    $key = $status->key();
    $hasDraft = $profile->exists && ($profile->pan_number || $profile->documents->isNotEmpty() || $profile->date_of_birth);
    $state = match ($status) {
        KycStatus::Pending => [
            'icon' => 'shield', 'title' => 'Complete your KYC to continue.',
            'text' => 'Verifying your identity keeps your gold savings secure. It takes about five minutes — keep your PAN card and Aadhaar ready.',
            'cta' => $hasDraft ? 'Continue KYC' : 'Start KYC',
        ],
        KycStatus::Submitted => [
            'icon' => 'clock', 'title' => 'Your KYC is currently under review.',
            'text' => 'Our team is checking your details and documents. You will see the decision here.',
            'cta' => null,
        ],
        KycStatus::Verified => [
            'icon' => 'check', 'title' => 'Your KYC has been verified.',
            'text' => 'Thank you. Your identity has been verified by our team.',
            'cta' => null,
        ],
        KycStatus::Rejected => [
            'icon' => 'alert', 'title' => 'Your KYC needs attention.',
            'text' => 'Please review the note from our team, update your details or documents, and resubmit.',
            'cta' => 'Update & Resubmit',
        ],
    };
    $dateLine = match ($status) {
        KycStatus::Submitted => $profile->submitted_at ? 'Submitted on '.$profile->submitted_at->format('d M Y, g:i A') : null,
        KycStatus::Verified => ($profile->verified_at ?? $profile->reviewed_at) ? 'Verified on '.($profile->verified_at ?? $profile->reviewed_at)->format('d M Y') : null,
        KycStatus::Rejected => $profile->reviewed_at ? 'Reviewed on '.$profile->reviewed_at->format('d M Y') : null,
        default => null,
    };
@endphp

@section('content')
    <x-customer-shell title="KYC Verification" emphasis="Verification" text="Verify your identity to keep your gold savings secure.">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="min-w-0 space-y-6">
                {{-- Current status --}}
                <section @class([
                    'border bg-white',
                    'border-red-200' => $status === KycStatus::Rejected,
                    'border-line' => $status !== KycStatus::Rejected,
                ]) aria-labelledby="kyc-state-heading" data-kyc-state="{{ $key }}">
                    <div class="flex flex-col gap-5 p-6 sm:flex-row sm:items-start sm:p-8">
                        <span @class([
                            'inline-flex size-12 shrink-0 items-center justify-center',
                            'bg-obsidian text-champagne' => in_array($status, [KycStatus::Pending, KycStatus::Submitted], true),
                            'bg-emerald-700 text-white' => $status === KycStatus::Verified,
                            'bg-red-700 text-white' => $status === KycStatus::Rejected,
                        ])>
                            <x-icon :name="$state['icon']" class="size-6" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-3">
                                <p class="text-xs text-taupe">Current status</p>
                                <x-kyc-status :status="$status" />
                            </div>
                            <h2 id="kyc-state-heading" class="mt-3 text-[1.9rem] leading-tight sm:text-4xl">{{ $state['title'] }}</h2>
                            <p class="mt-2 max-w-xl text-sm leading-relaxed text-taupe">{{ $state['text'] }}</p>
                            @if ($dateLine)
                                <p class="mt-2 text-xs text-taupe" data-kyc-date>{{ $dateLine }}</p>
                            @endif

                            @if ($status === KycStatus::Rejected && $profile->rejection_reason)
                                <div class="mt-5 border-l-2 border-red-400 bg-red-50 px-5 py-4" data-rejection-reason>
                                    <p class="text-[0.66rem] font-semibold tracking-[0.2em] text-red-800 uppercase">Reason</p>
                                    <p class="mt-1 text-sm leading-relaxed text-red-900">{{ $profile->rejection_reason }}</p>
                                </div>
                            @endif

                            @if ($state['cta'])
                                <a href="{{ route('customer.kyc.edit') }}" class="btn btn-dark mt-6 w-full sm:w-auto" data-kyc-cta>
                                    {{ $state['cta'] }}
                                    <x-icon name="arrow-right" class="size-4" />
                                </a>
                            @endif
                        </div>
                    </div>
                </section>

                {{-- What we have on file (masked) --}}
                @if ($profile->exists && $status !== KycStatus::Pending)
                    <section class="border border-line bg-white" aria-labelledby="kyc-details-heading">
                        <h2 id="kyc-details-heading" class="border-b border-line px-6 py-5 text-2xl">Your Details</h2>
                        <dl class="divide-y divide-line" data-kyc-details>
                            @foreach ([
                                ['Full Name', $profile->full_name],
                                ['Date of Birth', $profile->date_of_birth?->format('d M Y')],
                                ['Gender', KycService::GENDERS[$profile->gender] ?? null],
                                ['Address', collect([$profile->address_line1, $profile->address_line2, $profile->city, $profile->state.' '.$profile->pincode])->filter(fn ($line) => filled(trim((string) $line)))->implode(', ')],
                                ['Aadhaar', $profile->maskedAadhaar()],
                                ['PAN', $profile->maskedPan()],
                            ] as [$label, $value])
                                <div class="grid gap-1 px-6 py-4 sm:grid-cols-3">
                                    <dt class="text-sm text-taupe">{{ $label }}</dt>
                                    <dd class="font-semibold break-words sm:col-span-2">{{ filled($value) ? $value : '—' }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </section>
                @endif

                @if ($profile->documents->isNotEmpty())
                    <section class="border border-line bg-white" aria-labelledby="kyc-docs-heading">
                        <h2 id="kyc-docs-heading" class="border-b border-line px-6 py-5 text-2xl">Documents</h2>
                        <ul class="divide-y divide-line" data-kyc-documents>
                            @foreach ($documents as $type => $slot)
                                @php $document = $profile->documentOfType(KycDocumentType::from($type)); @endphp
                                @continue(! $document)
                                <li class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <x-icon name="file" class="size-5 text-gold-deep" />
                                        <div class="min-w-0">
                                            <p class="font-semibold">{{ $slot['label'] }}</p>
                                            <p class="text-xs text-taupe">{{ $document->fileKind() }}@if ($document->humanSize()) · {{ $document->humanSize() }}@endif · Uploaded {{ $document->updated_at?->format('d M Y') }}</p>
                                        </div>
                                    </div>
                                    <a href="{{ route('customer.kyc.documents.show', $type) }}" target="_blank" rel="noopener" class="link-line shrink-0 text-obsidian">View <x-icon name="arrow-up-right" class="size-3.5" /></a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>

            <aside class="h-fit space-y-4">
                <div class="border border-line bg-white px-6 py-5">
                    <h2 class="font-sans text-[0.66rem] font-semibold tracking-[0.24em] text-taupe uppercase">What you need</h2>
                    <ul class="mt-4 space-y-3 text-sm">
                        @foreach (['Your PAN card', 'Your Aadhaar card (we store only the last 4 digits of the number)', 'Your current address'] as $item)
                            <li class="flex gap-3"><x-icon name="check" class="mt-0.5 size-4 text-gold-deep" stroke="2" /><span>{{ $item }}</span></li>
                        @endforeach
                    </ul>
                </div>
                <div class="bg-obsidian px-6 py-5 text-ivory">
                    <x-icon name="lock" class="size-5 text-champagne" />
                    <p class="mt-3 text-sm leading-relaxed text-ivory/80">Your documents are stored privately and are only visible to you and our verification team. They are never shared publicly.</p>
                </div>
            </aside>
        </div>
    </x-customer-shell>
@endsection
