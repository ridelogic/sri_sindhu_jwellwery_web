@extends('layouts.admin')

@section('title', 'KYC · '.$kyc->user->name)
@section('subtitle', 'Review details and documents, then approve or reject.')
@section('back', route('admin.kyc.index'))
@section('back_label', 'All KYC')

@use('App\Enums\KycStatus')
@use('App\Enums\KycDocumentType')
@use('App\Http\Requests\Admin\RejectKycRequest')
@use('App\Services\KycService')
@use('App\Support\IndianMobile')

@php
    $status = $kyc->kyc_status;
    $customer = $kyc->user;
    $docStyles = [
        'pending' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'verified' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        'rejected' => 'bg-red-50 text-red-800 ring-red-200',
    ];
    $row = 'grid gap-1 px-5 py-3 sm:grid-cols-3 sm:px-6';
@endphp

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-3">
            <x-kyc-status :status="$status" variant="admin" data-kyc-detail-status />
        </div>
        @if ($kyc->submitted_at && $status !== KycStatus::Pending)
            <p class="text-sm text-taupe">Submitted {{ $kyc->submitted_at->format('d M Y, g:i A') }}</p>
        @endif
    </div>

    @if ($errors->has('kyc'))
        <div class="mt-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $errors->first('kyc') }}</div>
    @endif

    @if ($status === KycStatus::Pending)
        <div class="mt-6 rounded-md border border-stone-300 bg-stone-50 px-4 py-3 text-sm text-stone-700" data-kyc-draft-notice>
            This customer has not submitted their KYC yet. It can be reviewed once they submit it.
        </div>
    @endif

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="min-w-0 space-y-6">
            {{-- Customer --}}
            <section class="rounded-lg border border-line bg-white" aria-labelledby="customer-heading">
                <h2 id="customer-heading" class="border-b border-line px-5 py-4 font-serif text-2xl font-semibold sm:px-6">Customer</h2>
                <dl class="divide-y divide-line text-sm">
                    @foreach ([
                        ['Account Name', $customer->name],
                        ['Email', $customer->email],
                        ['Mobile', IndianMobile::format($customer->mobile_number)],
                        ['Customer Since', $customer->created_at?->format('d M Y')],
                    ] as [$label, $value])
                        <div class="{{ $row }}"><dt class="text-taupe">{{ $label }}</dt><dd class="font-medium break-all sm:col-span-2">{{ filled($value) ? $value : '—' }}</dd></div>
                    @endforeach
                </dl>
            </section>

            {{-- KYC details (visible to signed-in admins only; page is no-store / noindex) --}}
            <section class="rounded-lg border border-line bg-white" aria-labelledby="details-heading">
                <h2 id="details-heading" class="border-b border-line px-5 py-4 font-serif text-2xl font-semibold sm:px-6">KYC Details</h2>
                <dl class="divide-y divide-line text-sm" data-kyc-details>
                    @foreach ([
                        ['Full Name (as on PAN)', $kyc->full_name],
                        ['Date of Birth', $kyc->date_of_birth ? $kyc->date_of_birth->format('d M Y').' (age '.$kyc->date_of_birth->age.')' : null],
                        ['Gender', KycService::GENDERS[$kyc->gender] ?? null],
                        ['Address', collect([$kyc->address_line1, $kyc->address_line2])->filter()->implode(', ')],
                        ['City', $kyc->city],
                        ['State', $kyc->state],
                        ['Pincode', $kyc->pincode],
                        ['PAN', $kyc->pan_number],
                        ['Aadhaar', $kyc->maskedAadhaar()],
                    ] as [$label, $value])
                        <div class="{{ $row }}">
                            <dt class="text-taupe">{{ $label }}</dt>
                            <dd @class(['font-medium break-words sm:col-span-2', 'font-mono tracking-wider' => in_array($label, ['PAN', 'Aadhaar'], true)])>{{ filled($value) ? $value : '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
                <p class="border-t border-line px-5 py-3 text-xs text-taupe sm:px-6">Only the last 4 digits of Aadhaar are collected. Compare the PAN with the uploaded PAN card.</p>
            </section>

            {{-- Documents --}}
            <section class="rounded-lg border border-line bg-white" aria-labelledby="documents-heading">
                <h2 id="documents-heading" class="border-b border-line px-5 py-4 font-serif text-2xl font-semibold sm:px-6">Documents</h2>
                <ul class="divide-y divide-line" data-kyc-documents>
                    @foreach ($documentSlots as $type => $slot)
                        @php $document = $kyc->documentOfType(KycDocumentType::from($type)); @endphp
                        <li class="px-5 py-4 sm:px-6" data-document="{{ $type }}">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="flex min-w-0 gap-3">
                                    <x-icon name="file" class="mt-0.5 size-5 text-gold-deep" />
                                    <div class="min-w-0">
                                        <p class="font-semibold">{{ $slot['label'] }} @unless ($slot['required'])<span class="text-xs font-normal text-taupe">(optional)</span>@endunless</p>
                                        @if ($document)
                                            <p class="mt-0.5 text-xs text-taupe">{{ $document->fileKind() }}@if ($document->humanSize()) · {{ $document->humanSize() }}@endif · Uploaded {{ $document->updated_at?->format('d M Y, g:i A') }}</p>
                                        @else
                                            <p class="mt-0.5 text-xs text-taupe">Not uploaded</p>
                                        @endif
                                    </div>
                                </div>
                                @if ($document)
                                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $docStyles[$document->status->value] ?? $docStyles['pending'] }}">{{ $document->status->label() }}</span>
                                        <a href="{{ route('admin.kyc.documents.show', $document) }}" target="_blank" rel="noopener" class="rounded-md border border-ad-line px-3 py-1.5 text-xs font-semibold hover:border-ad-gold-soft">View</a>
                                        <a href="{{ route('admin.kyc.documents.show', [$document, 'download' => 1]) }}" class="inline-flex items-center gap-1 rounded-md border border-ad-line px-3 py-1.5 text-xs font-semibold hover:border-ad-gold-soft"><x-icon name="download" class="size-3.5" /> Download</a>
                                    </div>
                                @endif
                            </div>
                            @if ($document && str_starts_with((string) $document->mime_type, 'image/'))
                                <a href="{{ route('admin.kyc.documents.show', $document) }}" target="_blank" rel="noopener" class="mt-3 block w-fit max-w-full overflow-hidden rounded-md border border-line bg-stone-50">
                                    <img src="{{ route('admin.kyc.documents.show', $document) }}" alt="{{ $slot['label'] }} preview" loading="lazy" class="max-h-56 w-auto max-w-full object-contain">
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>

        <aside class="h-fit min-w-0 space-y-4">
            {{-- Decision --}}
            <section class="rounded-lg border border-line bg-white p-5" aria-labelledby="decision-heading">
                <h2 id="decision-heading" class="font-sans text-[0.7rem] font-semibold tracking-[0.22em] text-taupe uppercase">Review</h2>

                @if ($status->isAwaitingReview())
                    <form method="POST" action="{{ route('admin.kyc.approve', $kyc) }}" class="mt-4" data-kyc-approve>
                        @csrf
                        <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-md bg-emerald-700 px-4 text-sm font-semibold text-white transition-colors hover:bg-emerald-800" data-submit-once>
                            <x-icon name="check" class="size-4" stroke="2" /> Approve KYC
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.kyc.reject', $kyc) }}" class="mt-5 border-t border-line pt-5" data-kyc-reject>
                        @csrf
                        <x-form.textarea name="rejection_reason" label="Rejection Reason" rows="4" maxlength="{{ RejectKycRequest::MAX_REASON }}" required
                            placeholder="e.g. PAN document is unclear. Please upload a clearer copy." />
                        <p class="mt-2 text-xs text-taupe">Shown to the customer. Up to {{ RejectKycRequest::MAX_REASON }} characters.</p>
                        <button type="submit" class="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-md border border-red-300 px-4 text-sm font-semibold text-red-700 transition-colors hover:bg-red-50" data-submit-once>Reject KYC</button>
                    </form>
                @else
                    <p class="mt-3 text-sm text-taupe">
                        @switch($status)
                            @case(KycStatus::Verified) This KYC has been approved. @break
                            @case(KycStatus::Rejected) This KYC was rejected. The customer can update and resubmit it. @break
                            @default No decision is needed until the customer submits.
                        @endswitch
                    </p>
                @endif
            </section>

            <dl class="space-y-3 rounded-lg border border-line bg-white p-5 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-taupe">Status</dt><dd class="font-medium">{{ $status->label() }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-taupe">Submitted</dt><dd class="text-right font-medium">{{ $status !== KycStatus::Pending ? $kyc->submitted_at?->format('d M Y, g:i A') ?? '—' : '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-taupe">Reviewed</dt><dd class="text-right font-medium" data-kyc-reviewed-at>{{ $kyc->reviewed_at?->format('d M Y, g:i A') ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-taupe">Reviewed by</dt><dd class="text-right font-medium" data-kyc-reviewer>{{ $kyc->reviewer?->name ?? '—' }}</dd></div>
                @if ($status === KycStatus::Rejected && $kyc->rejection_reason)
                    <div class="border-t border-line pt-3"><dt class="text-taupe">Rejection reason</dt><dd class="mt-1 font-medium break-words" data-rejection-reason>{{ $kyc->rejection_reason }}</dd></div>
                @endif
            </dl>

            {{-- Audit trail --}}
            <section class="rounded-lg border border-line bg-white p-5" aria-labelledby="history-heading">
                <h2 id="history-heading" class="font-sans text-[0.7rem] font-semibold tracking-[0.22em] text-taupe uppercase">History</h2>
                @if ($kyc->auditLogs->isEmpty())
                    <p class="mt-3 text-sm text-taupe">No activity yet.</p>
                @else
                    <ol class="mt-4 space-y-4 border-l border-line pl-4 text-sm" data-kyc-history>
                        @foreach ($kyc->auditLogs as $entry)
                            <li>
                                <p class="font-semibold">{{ $entry->action->label() }}</p>
                                <p class="text-xs text-taupe">{{ $entry->created_at?->format('d M Y, g:i A') }}@if ($entry->actor) · {{ $entry->actor->name }}@endif</p>
                                @if ($entry->note)
                                    <p class="mt-1 text-xs break-words text-taupe">“{{ $entry->note }}”</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </aside>
    </div>
@endsection
