@extends('layouts.admin')

@section('title', $customer->name)

@use('App\Enums\AccountStatus')
@use('App\Enums\CustomerPlanStatus')
@use('App\Enums\KycStatus')
@use('App\Support\Money')
@use('Illuminate\Support\Str')

@php
    $kyc = $customer->kycProfile;
    $kycStatus = $kyc?->kyc_status ?? KycStatus::Pending;
    $kycEditable = $kycStatus->isEditableByCustomer(); // not submitted (incl. draft) or rejected
    $kycDraft = $kyc && $kycStatus === KycStatus::Pending; // a saved, unsubmitted draft
    $kycEditLabel = $kycStatus === KycStatus::Rejected ? 'Update KYC' : ($kyc ? 'Continue KYC' : 'Add KYC');
    $blocked = $customer->isBlocked();
    $openSchemes = $schemes->filter(fn ($s) => in_array($s->status, CustomerPlanStatus::open(), true))->count();
    $schemeTone = fn ($scheme) => ['active' => 'success', 'pending' => 'warning', 'completed' => 'info', 'matured' => 'gold'][$scheme->status->value] ?? 'neutral';
    $schemeLabel = fn ($scheme) => $scheme->status === CustomerPlanStatus::Pending ? 'Awaiting Payment' : $scheme->status->label();
    // Oldest first, so the account's story reads top to bottom.
    $history = $customer->customerAuditLogs->sortBy([['created_at', 'asc'], ['id', 'asc']])->values();
    // Shared styles for the detail cards.
    $cardHeading = 'font-sans text-[0.68rem] font-semibold tracking-[0.2em] text-ad-gold uppercase';
    $label = 'text-xs leading-5 text-ad-muted';
    $missing = 'text-xs text-ad-muted italic';
@endphp

@section('page-header')
    <a href="{{ route('admin.customers.index') }}" class="admin-back mb-3" data-page-back>
        <x-icon name="arrow-right" class="size-3.5 rotate-180" /> Back to Customers
    </a>

    {{-- Header: who the customer is, their account / KYC status, and the main actions. KYC actions live on the KYC card. --}}
    <section class="admin-card mb-4 flex flex-col gap-4 p-4 sm:p-5 lg:flex-row lg:items-center lg:justify-between" aria-labelledby="customer-name" data-customer-header>
        <div class="flex min-w-0 items-center gap-4">
            <span class="inline-flex size-12 shrink-0 items-center justify-center rounded-full bg-ad-dark text-xl font-bold text-ad-gold-light ring-2 ring-ad-gold-soft/60" aria-hidden="true">{{ Str::upper(Str::substr($customer->name, 0, 1)) }}</span>
            <div class="min-w-0">
                <h1 id="customer-name" class="text-xl leading-tight font-bold [overflow-wrap:anywhere]">{{ $customer->name }}</h1>
                <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                    @include('admin.customers._status', ['status' => $customer->status])
                    <x-kyc-status :status="$kycStatus" variant="admin" data-header-kyc />
                    <span class="text-xs text-ad-muted">Customer #{{ $customer->id }}</span>
                </div>
                <p class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-sm text-ad-muted">
                    <span class="inline-flex min-w-0 items-center gap-1.5 [overflow-wrap:anywhere]"><x-icon name="mail" class="size-3.5 shrink-0 text-ad-gold-soft" />{{ $customer->email }}</span>
                    <span class="inline-flex items-center gap-1.5"><x-icon name="phone" class="size-3.5 shrink-0 text-ad-gold-soft" />{{ $mobile ?? 'No mobile number' }}</span>
                    <span class="inline-flex items-center gap-1.5"><x-icon name="calendar" class="size-3.5 shrink-0 text-ad-gold-soft" />Since {{ $customer->created_at?->format('d M Y') }}</span>
                </p>
            </div>
        </div>
        <div class="grid shrink-0 grid-cols-2 gap-2 sm:flex" data-customer-actions>
            <a href="{{ route('admin.customers.edit', $customer) }}" class="btn btn-secondary">Edit Customer</a>
            <a href="{{ route('admin.customers.schemes.create', $customer) }}" class="btn btn-primary" data-add-scheme><x-icon name="plus" class="size-4" /> Add Scheme</a>
        </div>
    </section>
@endsection

@section('content')
    @if ($blocked)
        <div class="mb-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" data-blocked-notice>
            This customer is blocked and cannot sign in. Their schemes, payments and gold records are unchanged.
        </div>
    @endif

    {{-- KPIs --}}
    <section aria-label="Summary" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.kpi-card label="Active Schemes" :value="number_format($openSchemes)" key="active-schemes" icon="layers" :hint="$schemes->count().' '.Str::plural('scheme', $schemes->count()).' in total'" />
        <x-admin.kpi-card label="Total Paid" :value="Money::inr(Money::fromPaise($payments['total_paise']))" key="payment-total" icon="wallet">
            <p class="mt-1 text-xs text-taupe" data-stat="payment-count"><span data-stat-value>{{ number_format($payments['count']) }}</span> successful {{ Str::plural('payment', $payments['count']) }}</p>
        </x-admin.kpi-card>
        <x-admin.kpi-card label="Gold Accumulated" :value="$gold['grams'].' g'" key="gold" icon="gem" tone="gold" :hint="$gold['count'].' gold '.Str::plural('credit', $gold['count'])" />
        <x-admin.kpi-card label="KYC Status" :value="$kycStatus->label()" key="kyc" icon="shield" :hint="$kyc?->reviewed_at ? 'Reviewed '.$kyc->reviewed_at->format('d M Y') : ($kycDraft ? 'Draft saved' : null)" />
    </section>

    {{-- Customer details: two columns from 1024px, both starting at the same top edge and each card at its
         natural height. Left: Profile, Gold Summary, History. Right: KYC, Account Access.
         Below 1024px the columns dissolve (display: contents) and order-* stacks the cards as
         Profile, KYC, Gold Summary, Account Access, History. --}}
    <div class="mt-3 flex flex-col gap-3 lg:grid lg:grid-cols-2 lg:items-start" data-customer-layout>
        <div class="contents lg:flex lg:min-w-0 lg:flex-col lg:gap-3" data-customer-column="left">
            {{-- Profile --}}
            <section class="order-1 rounded-lg border border-ad-line bg-ad-card px-4 py-3" aria-labelledby="profile-heading">
                <h2 id="profile-heading" class="{{ $cardHeading }}">Profile</h2>
                <dl class="mt-2 grid grid-cols-[6rem_minmax(0,1fr)] gap-x-3 gap-y-1.5 text-sm leading-snug" data-customer-profile>
                    <dt class="{{ $label }}">Name</dt>
                    <dd class="font-semibold [overflow-wrap:anywhere] text-ad-ink">{{ $customer->name }}</dd>
                    <dt class="{{ $label }}">Email</dt>
                    <dd class="font-medium [overflow-wrap:anywhere] text-ad-ink">{{ $customer->email }}</dd>
                    <dt class="{{ $label }}">Mobile</dt>
                    <dd class="font-medium text-ad-ink">@if ($mobile){{ $mobile }}@else<span class="{{ $missing }}">Not provided</span>@endif</dd>
                    <dt class="{{ $label }}">Account</dt>
                    <dd>@include('admin.customers._status', ['status' => $customer->status])</dd>
                    <dt class="{{ $label }}">Registered</dt>
                    <dd class="font-medium text-ad-ink">{{ $customer->created_at?->format('d M Y, g:i A') }}</dd>
                </dl>
            </section>

            {{-- Gold --}}
            <section class="order-3 rounded-lg border border-ad-line bg-ad-card px-4 py-3 text-ad-ink" aria-labelledby="gold-heading">
                <h2 id="gold-heading" class="{{ $cardHeading }}">Gold Summary</h2>
                <div class="mt-1.5 flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                    <p class="text-2xl leading-tight font-bold tabular-nums" data-customer-gold>{{ $gold['grams'] }} g</p>
                    <p class="text-xs text-ad-muted" data-customer-gold-count>{{ number_format($gold['count']) }} gold {{ Str::plural('transaction', $gold['count']) }}</p>
                </div>
                <p class="mt-1 text-xs text-ad-muted">Total across all schemes. Gold per scheme is listed under Gold Saving Schemes.</p>
            </section>

            {{-- Audit history (oldest first) --}}
            <section class="order-5 rounded-lg border border-ad-line bg-ad-card px-4 py-3" aria-labelledby="history-heading">
                <div class="flex items-baseline justify-between gap-3">
                    <h2 id="history-heading" class="{{ $cardHeading }}">History</h2>
                    @if ($history->isNotEmpty())
                        <span class="text-xs text-ad-muted">{{ $history->count() }} {{ Str::plural('entry', $history->count()) }} · oldest first</span>
                    @endif
                </div>
                @if ($history->isEmpty())
                    <p class="mt-1.5 text-sm text-ad-muted" data-no-history>No admin actions recorded.</p>
                @else
                    <ol class="mt-2 space-y-2 border-l border-ad-line pl-3 text-sm leading-snug" data-customer-history>
                        @foreach ($history as $entry)
                            <li data-history-entry="{{ $entry->action->value }}">
                                <p class="font-semibold">{{ $entry->action->label() }}</p>
                                <p class="text-xs text-ad-muted">
                                    <time datetime="{{ $entry->created_at?->toIso8601String() }}">{{ $entry->created_at?->format('d M Y, g:i A') }}</time>@if ($entry->actor) · by {{ $entry->actor->name }}@endif
                                </p>
                                @if ($entry->fields)
                                    <p class="text-xs text-ad-muted">Changed: {{ collect($entry->fields)->map(fn ($f) => str_replace('_', ' ', $f))->implode(', ') }}</p>
                                @endif
                                @if ($entry->note)
                                    <p class="text-xs [overflow-wrap:anywhere] text-ad-muted">“{{ $entry->note }}”</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </div>

        <div class="contents lg:flex lg:min-w-0 lg:flex-col lg:gap-3" data-customer-column="right">
            {{-- KYC (status only — no identity numbers here). Actions follow the existing workflow:
                 View/Review for any KYC record; Add/Continue/Update only while not submitted or rejected.
                 Approval happens only on the KYC review screen. --}}
            <section class="order-2 rounded-lg border border-ad-line bg-ad-card px-4 py-3" aria-labelledby="kyc-heading">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="kyc-heading" class="{{ $cardHeading }}">KYC</h2>
                    <span class="flex flex-wrap items-center justify-end gap-1.5">
                        @if ($kycDraft)
                            <span class="rounded border border-ad-line px-1.5 text-[0.65rem] font-semibold text-ad-muted" data-kyc-draft>Draft saved</span>
                        @endif
                        <x-kyc-status :status="$kycStatus" variant="admin" />
                    </span>
                </div>
                <dl class="mt-2 grid grid-cols-[6rem_minmax(0,1fr)] gap-x-3 gap-y-1.5 text-sm leading-snug" data-customer-kyc>
                    <dt class="{{ $label }}">Submitted</dt>
                    <dd class="font-medium">
                        @if ($kycStatus === KycStatus::Pending)
                            <span class="{{ $missing }}">Not submitted yet</span>
                        @elseif ($kyc?->submitted_at)
                            {{ $kyc->submitted_at->format('d M Y') }}
                        @else
                            <span class="{{ $missing }}">Date not recorded</span>
                        @endif
                    </dd>
                    <dt class="{{ $label }}">Reviewed</dt>
                    <dd class="font-medium">
                        @if ($kyc?->reviewed_at)
                            {{ $kyc->reviewed_at->format('d M Y') }}
                        @elseif (in_array($kycStatus, [KycStatus::Verified, KycStatus::Rejected], true))
                            <span class="{{ $missing }}">Date not recorded</span>
                        @else
                            <span class="{{ $missing }}">Not reviewed yet</span>
                        @endif
                    </dd>
                    @if ($kycStatus === KycStatus::Rejected && $kyc?->rejection_reason)
                        <dt class="{{ $label }}">Rejection reason</dt>
                        <dd class="font-medium [overflow-wrap:anywhere]">{{ $kyc->rejection_reason }}</dd>
                    @endif
                </dl>

                {{-- Documents on file (private storage; opened through the admin-only document route) --}}
                <div class="mt-2.5 border-t border-ad-line pt-2" data-customer-kyc-documents>
                    <p class="{{ $label }} font-semibold">Documents</p>
                    <ul class="mt-1 space-y-1 text-sm">
                        @foreach (\App\Services\KycService::DOCUMENTS as $type => $slot)
                            @php $document = $kyc?->documentOfType(\App\Enums\KycDocumentType::from($type)); @endphp
                            <li class="flex items-start justify-between gap-3 leading-snug" data-kyc-document="{{ $type }}">
                                <span class="min-w-0">
                                    {{ $slot['label'] }}@unless ($slot['required'])<span class="text-xs text-ad-muted"> (optional)</span>@endunless
                                    @if ($document)
                                        <span class="block text-xs text-ad-muted">{{ $document->fileKind() }}@if ($document->humanSize()) · {{ $document->humanSize() }}@endif · {{ $document->updated_at?->format('d M Y') }}</span>
                                    @endif
                                </span>
                                @if ($document)
                                    <a href="{{ route('admin.kyc.documents.show', $document) }}" target="_blank" rel="noopener" class="shrink-0 text-xs font-semibold text-ad-gold underline underline-offset-2 hover:text-ad-ink">View</a>
                                @else
                                    <span class="shrink-0 text-xs text-ad-muted">Not uploaded</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>

                @if (! $kyc || $kycStatus === KycStatus::Pending)
                    {{-- Nothing submitted yet: how KYC gets in --}}
                    <div class="mt-2.5 rounded-md border border-ad-line bg-ad-panel px-2.5 py-2 text-xs leading-snug text-ad-muted" data-kyc-not-submitted>
                        <span class="font-semibold text-ad-gold">KYC Not Submitted{{ $kyc ? ' (draft saved)' : '' }}.</span>
                        The customer can submit KYC through the customer portal (Login → KYC), or use <strong class="text-ad-ink">{{ $kycEditLabel }}</strong>.
                    </div>
                @endif

                @if ($kyc || $kycEditable)
                    <div class="mt-2.5 flex flex-wrap gap-2" data-kyc-actions>
                        @if ($kyc)
                            <a href="{{ route('admin.kyc.show', $kyc) }}" @class(['admin-action', 'admin-action-primary' => $kycStatus === KycStatus::Submitted]) data-kyc-action="view" data-kyc-review-link>
                                <x-icon name="shield" class="size-3.5" /> {{ $kycStatus === KycStatus::Submitted ? 'Review KYC — approve or reject' : 'View KYC' }}
                            </a>
                        @endif
                        @if ($kycEditable)
                            <a href="{{ route('admin.customers.kyc.edit', $customer) }}" class="admin-action" data-kyc-action="add">
                                <x-icon name="plus" class="size-3.5" /> {{ $kycEditLabel }}
                            </a>
                        @endif
                    </div>
                @endif
            </section>

            {{-- Account access: same forms, routes and audit logging as before --}}
            <section class="order-4 rounded-lg border border-ad-line bg-ad-card px-4 py-3" aria-labelledby="account-heading">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="account-heading" class="{{ $cardHeading }}">Account Access</h2>
                    @include('admin.customers._status', ['status' => $customer->status])
                </div>
                <p class="mt-1 text-xs text-ad-muted" data-account-access-note>
                    @if ($customer->status === AccountStatus::Active)
                        This customer can sign in.
                    @elseif ($customer->isInactive())
                        This account is inactive and cannot sign in until it is activated.
                    @else
                        This account is blocked and cannot sign in.
                    @endif
                </p>

                @if ($customer->isInactive())
                    <form method="POST" action="{{ route('admin.customers.activate', $customer) }}" class="mt-2" data-activate-form>
                        @csrf
                        <button type="submit" class="inline-flex min-h-9 w-full items-center justify-center rounded-md bg-emerald-700 px-3 text-sm font-semibold text-white hover:bg-emerald-800" data-submit-once>Activate Customer</button>
                    </form>
                @elseif ($customer->status === AccountStatus::Active)
                    <form method="POST" action="{{ route('admin.customers.deactivate', $customer) }}" class="mt-2" data-deactivate-form>
                        @csrf
                        <button type="submit" class="btn btn-secondary min-h-9 w-full py-1.5" data-submit-once>Deactivate Customer</button>
                        <p class="mt-1 text-xs text-ad-muted">An inactive customer cannot sign in until activated again. Schemes, payments and gold are not changed.</p>
                    </form>
                @endif

                @if ($blocked)
                    <form method="POST" action="{{ route('admin.customers.unblock', $customer) }}" class="mt-2" data-unblock-form>
                        @csrf
                        <button type="submit" class="inline-flex min-h-9 w-full items-center justify-center rounded-md bg-emerald-700 px-4 text-sm font-semibold text-white hover:bg-emerald-800" data-submit-once>Unblock Customer</button>
                    </form>
                @else
                    {{-- Blocking: visually separated and styled as a destructive action --}}
                    <form method="POST" action="{{ route('admin.customers.block', $customer) }}" class="mt-3 space-y-1.5 border-t border-ad-line pt-2.5" data-block-form>
                        @csrf
                        <p class="text-xs font-semibold tracking-[0.12em] text-ad-muted uppercase">Block access</p>
                        <x-form.textarea name="reason" label="Reason for blocking (internal, optional)" rows="2" maxlength="255" />
                        <button type="submit" class="btn btn-danger min-h-9 w-full py-1.5" data-submit-once>Block Customer</button>
                        <p class="text-xs leading-snug text-ad-muted">Blocking stops sign-in only. Schemes, payments and gold are not changed.</p>
                    </form>
                @endif
            </section>
        </div>
    </div>

    {{-- Schemes and recent payments: full width below the details --}}
    <div class="mt-3 space-y-3">
        {{-- Schemes --}}
        <section class="rounded-lg border border-line bg-white" aria-labelledby="schemes-heading" data-list-section>
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-2.5">
                <h2 id="schemes-heading" class="text-base font-bold">Gold Saving Schemes <span class="text-sm font-medium text-taupe">({{ $schemes->count() }})</span></h2>
                @if ($schemes->isNotEmpty())
                    <a href="{{ route('admin.schemes.index', ['user' => $customer->id]) }}" class="text-xs font-semibold text-ad-gold underline underline-offset-2 hover:text-ad-ink" data-customer-scheme-list>Open in scheme list</a>
                @endif
            </div>
            @if ($schemes->isEmpty())
                <p class="px-4 py-3 text-sm text-taupe" data-no-schemes>No scheme enrolments yet. Use <strong>Add Scheme</strong> above to enrol this customer.</p>
            @else
                {{-- Phones: cards --}}
                <ul class="divide-y divide-line md:hidden" data-scheme-cards>
                    @foreach ($schemes as $scheme)
                        @php $p = $progress[$scheme->id]; @endphp
                        <li class="px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-semibold">{{ $scheme->plan?->name }}</p>
                                    <p class="font-mono text-xs text-taupe">{{ $scheme->scheme_number }}</p>
                                </div>
                                <x-admin.badge class="shrink-0" :tone="$schemeTone($scheme)">{{ $schemeLabel($scheme) }}</x-admin.badge>
                            </div>
                            <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs">
                                <div><dt class="text-taupe">Contribution</dt><dd class="font-semibold tabular-nums">{{ $scheme->amountRangeLabel() }} · {{ $scheme->tenureLabel() }}</dd></div>
                                <div><dt class="text-taupe">Commitment</dt><dd class="font-semibold tabular-nums">{{ $scheme->total_commitment !== null ? Money::inr($scheme->total_commitment) : 'Open-ended' }}</dd></div>
                                <div><dt class="text-taupe">Paid</dt><dd class="font-semibold tabular-nums">{{ Money::inr($p['amount_paid']) }}</dd></div>
                                <div><dt class="text-taupe">Gold</dt><dd class="font-semibold tabular-nums">{{ $p['gold_grams'] }} g</dd></div>
                            </dl>
                            <a href="{{ route('admin.customers.schemes.show', [$customer, $scheme->id]) }}" class="admin-action mt-2">View Scheme</a>
                        </li>
                    @endforeach
                </ul>

                {{-- Tablets and up: table (scrolls inside its own container if ever too narrow) --}}
                <div class="relative hidden overflow-x-auto md:block" data-scroll-table>
                    <table class="w-full min-w-[42rem] text-left text-sm" data-table="customer-schemes">
                        <thead class="text-[0.68rem] tracking-[0.12em] uppercase">
                            <tr>
                                <th scope="col" class="px-3 py-2.5 font-semibold">Scheme</th>
                                <th scope="col" class="px-3 py-2.5 font-semibold">Status</th>
                                <th scope="col" class="px-3 py-2.5 text-right font-semibold">Contribution</th>
                                <th scope="col" class="px-3 py-2.5 text-right font-semibold">Commitment</th>
                                <th scope="col" class="px-3 py-2.5 text-right font-semibold">Paid</th>
                                <th scope="col" class="px-3 py-2.5 text-right font-semibold">Gold</th>
                                <th scope="col" class="px-3 py-2.5 text-right font-semibold"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($schemes as $scheme)
                                @php $p = $progress[$scheme->id]; @endphp
                                <tr data-scheme-row="{{ $scheme->scheme_number }}">
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <span class="block font-semibold">{{ $scheme->plan?->name }}</span>
                                        <span class="block font-mono text-xs text-taupe">{{ $scheme->scheme_number }}</span>
                                    </td>
                                    <td class="px-3 py-2"><x-admin.badge :tone="$schemeTone($scheme)">{{ $schemeLabel($scheme) }}</x-admin.badge></td>
                                    <td class="px-3 py-2 text-right whitespace-nowrap tabular-nums">{{ $scheme->amountRangeLabel() }}<span class="block text-xs text-taupe">{{ $scheme->tenureLabel() }}</span></td>
                                    <td class="px-3 py-2 text-right whitespace-nowrap tabular-nums">{{ $scheme->total_commitment !== null ? Money::inr($scheme->total_commitment) : 'Open-ended' }}</td>
                                    <td class="px-3 py-2 text-right whitespace-nowrap tabular-nums">{{ Money::inr($p['amount_paid']) }}</td>
                                    <td class="px-3 py-2 text-right whitespace-nowrap tabular-nums">{{ $p['gold_grams'] }} g</td>
                                    <td class="px-3 py-2 text-right"><a href="{{ route('admin.customers.schemes.show', [$customer, $scheme->id]) }}" class="admin-action" aria-label="View scheme {{ $scheme->scheme_number }}">View</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- Recent payments (latest 10, newest first) --}}
        <section class="rounded-lg border border-line bg-white" aria-labelledby="payments-heading" data-list-section>
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-2.5">
                <h2 id="payments-heading" class="text-base font-bold">Recent Payments</h2>
                <p class="text-xs text-taupe">
                    {{ $payments['count'] }} successful · {{ Money::inr(Money::fromPaise($payments['total_paise'])) }} in total
                    @if ($payments['recent']->isNotEmpty())
                        · showing latest {{ $payments['recent']->count() }}
                    @endif
                </p>
            </div>
            @if ($payments['recent']->isEmpty())
                <p class="px-4 py-3 text-sm text-taupe" data-no-payments>No payments yet.</p>
            @else
                <div data-customer-payments>
                    {{-- Phones: rows --}}
                    <ul class="divide-y divide-line md:hidden">
                        @foreach ($payments['recent'] as $payment)
                            <li>
                                <a href="{{ route('admin.payments.show', $payment) }}" class="block px-4 py-2.5 text-sm hover:bg-parchment/40">
                                    <span class="flex items-start justify-between gap-3">
                                        <span class="min-w-0 font-mono text-xs [overflow-wrap:anywhere]">{{ $payment->reference }}</span>
                                        <x-payment-status :status="$payment->status" variant="admin" />
                                    </span>
                                    <span class="mt-1 flex items-baseline justify-between gap-3">
                                        <span class="text-xs text-taupe">{{ ($payment->paid_at ?? $payment->initiated_at)?->format('d M Y, g:i A') }} · {{ $payment->customerPlan?->scheme_number }}</span>
                                        <span class="shrink-0 text-right font-semibold tabular-nums">{{ Money::inr($payment->amount()) }}@if ($payment->goldTransaction)<span class="block text-xs font-normal text-taupe">{{ $payment->goldTransaction->gold_grams }} g</span>@endif</span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>

                    {{-- Tablets and up: table --}}
                    <div class="relative hidden overflow-x-auto md:block" data-scroll-table>
                        <table class="w-full min-w-[42rem] text-left text-sm" data-table="customer-payments">
                            <thead class="text-[0.68rem] tracking-[0.12em] uppercase">
                                <tr>
                                    <th scope="col" class="px-3 py-2.5 font-semibold">Reference</th>
                                    <th scope="col" class="px-3 py-2.5 font-semibold">Date</th>
                                    <th scope="col" class="px-3 py-2.5 font-semibold">Scheme</th>
                                    <th scope="col" class="px-3 py-2.5 text-right font-semibold">Amount</th>
                                    <th scope="col" class="px-3 py-2.5 text-right font-semibold">Gold</th>
                                    <th scope="col" class="px-3 py-2.5 font-semibold">Status</th>
                                    <th scope="col" class="px-3 py-2.5 text-right font-semibold"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($payments['recent'] as $payment)
                                    <tr data-payment-row="{{ $payment->reference }}">
                                        <td class="px-3 py-2 font-mono text-xs whitespace-nowrap">{{ $payment->reference }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap">{{ ($payment->paid_at ?? $payment->initiated_at)?->format('d M Y, g:i A') }}</td>
                                        <td class="px-3 py-2 font-mono text-xs whitespace-nowrap">{{ $payment->customerPlan?->scheme_number ?? '—' }}</td>
                                        <td class="px-3 py-2 text-right font-semibold whitespace-nowrap tabular-nums">{{ Money::inr($payment->amount()) }}</td>
                                        <td class="px-3 py-2 text-right whitespace-nowrap tabular-nums">{{ $payment->goldTransaction ? $payment->goldTransaction->gold_grams.' g' : '—' }}</td>
                                        <td class="px-3 py-2"><x-payment-status :status="$payment->status" variant="admin" /></td>
                                        <td class="px-3 py-2 text-right"><a href="{{ route('admin.payments.show', $payment) }}" class="admin-action" aria-label="View payment {{ $payment->reference }}">View</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </section>
    </div>
@endsection
