@extends('layouts.admin')

@section('title', 'Payment '.$payment->reference)
@section('subtitle', 'Payment, gateway and gold credit details.')
@section('back', route('admin.payments.index'))
@section('back_label', 'All Payments')

@use('App\Support\Money')
@use('App\Support\IndianMobile')

@php
    $gold = $payment->goldTransaction;
    $scheme = $payment->customerPlan;
    $row = 'grid gap-1 px-5 py-3 sm:grid-cols-3 sm:px-6';
@endphp

@section('content')
    {{-- Summary strip --}}
    <section class="admin-card mb-6 flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between" aria-label="Payment summary">
        <div class="flex items-center gap-4">
            <span class="inline-flex size-11 items-center justify-center rounded-[0.65rem] bg-ad-dark text-ad-gold-light"><x-icon name="receipt" class="size-5" /></span>
            <div>
                <p class="text-[1.75rem] leading-none font-semibold tracking-tight text-ad-ink tabular-nums">{{ Money::inr($payment->amount()) }}</p>
                <p class="mt-1 text-xs text-taupe">{{ $payment->user?->name }} · {{ $scheme?->scheme_number }} · Installment {{ $payment->installment_number }}</p>
            </div>
        </div>
        <x-payment-status :status="$payment->status" variant="admin" />
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="min-w-0 space-y-6" data-payment-fields>
            @foreach ([
                ['Payment Information', 'receipt', [
                    ['Reference', $payment->reference, true],
                    ['Status', $payment->status->label(), false],
                    ['Installment', ($scheme ? $scheme->installmentsLabel($payment->installment_number) : $payment->installment_number), false],
                    ['Initiated', $payment->initiated_at?->format('d M Y, g:i:s A'), false],
                    ['Paid', $payment->paid_at?->format('d M Y, g:i:s A'), false],
                    ['Failed', $payment->failed_at?->format('d M Y, g:i:s A'), false],
                    ['Cancelled', $payment->cancelled_at?->format('d M Y, g:i:s A'), false],
                    ['Failure Reason', $payment->failure_reason, false],
                    ['Created', $payment->created_at?->format('d M Y, g:i:s A'), false],
                ]],
                ['Gateway Information', 'lock', [
                    ['Gateway', ucfirst($payment->gateway), false],
                    ['Gateway Order ID', $payment->gateway_order_id, true],
                    ['Gateway Payment ID', $payment->gateway_payment_id, true],
                    ['Signature', $payment->gateway_signature ? 'Verified (hidden)' : '—', false],
                    ['Payment Method', $payment->payment_method ? \Illuminate\Support\Str::upper($payment->payment_method) : null, false],
                ]],
                ['Financial Information', 'wallet', [
                    ['Amount', Money::inr($payment->amount()).' '.$payment->currency, false],
                    ['Scheme Monthly Contribution', $scheme?->amountRangeLabel(), false],
                    ['Scheme Total Commitment', $scheme ? ($scheme->total_commitment !== null ? Money::inr($scheme->total_commitment) : 'Open-ended') : null, false], // the scheme's stored total
                ]],
            ] as [$heading, $icon, $fields])
                <section class="admin-card overflow-hidden" aria-label="{{ $heading }}">
                    <h2 class="flex items-center gap-2 border-b border-line px-5 py-4 text-sm font-semibold sm:px-6"><x-icon :name="$icon" class="size-4 text-ad-gold-soft" /> {{ $heading }}</h2>
                    <dl class="divide-y divide-line text-sm">
                        @foreach ($fields as [$label, $value, $mono])
                            <div class="{{ $row }}">
                                <dt class="text-taupe">{{ $label }}</dt>
                                <dd @class(['font-medium break-all sm:col-span-2', 'font-mono text-[0.82rem]' => $mono])>{{ filled($value) ? $value : '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endforeach

            <section class="rounded-lg border border-line bg-white" aria-labelledby="gold-heading">
                <h2 id="gold-heading" class="flex items-center gap-2 border-b border-line px-5 py-4 text-sm font-semibold sm:px-6"><x-icon name="gem" class="size-4 text-ad-gold-soft" /> Gold Credit Information</h2>
                @if ($gold)
                    <dl class="divide-y divide-line text-sm" data-gold-fields>
                        @foreach ([
                            ['Gold Credited', $gold->gold_grams.' g'],
                            ['Purity', $gold->purity->value],
                            ['Gold Rate (snapshot)', Money::inr($gold->ratePerGram()).' / gram'],
                            ['Rate Effective', $gold->rateEffectiveLabel()],
                            ['Amount', Money::inr(Money::fromPaise($gold->amount_paise))],
                            ['Transaction Date', $gold->transaction_date->format('d M Y, g:i:s A')],
                            ['Ledger Entry', '#'.$gold->id.' · '.$gold->transaction_type->label()],
                        ] as [$label, $value])
                            <div class="{{ $row }}"><dt class="text-taupe">{{ $label }}</dt><dd class="font-medium tabular-nums sm:col-span-2">{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                @else
                    <p class="px-5 py-6 text-sm text-taupe sm:px-6" data-no-gold>No gold credited — gold is only credited for verified successful payments.</p>
                @endif
            </section>
        </div>

        <aside class="h-fit min-w-0 space-y-4">
            <section class="rounded-lg border border-line bg-white p-5" aria-labelledby="customer-heading">
                <h2 id="customer-heading" class="font-sans text-[0.7rem] font-semibold tracking-[0.22em] text-taupe uppercase">Customer</h2>
                <p class="mt-3 font-semibold">{{ $payment->user?->name }}</p>
                <p class="text-sm break-all text-taupe">{{ $payment->user?->email }}</p>
                <p class="text-sm text-taupe">{{ IndianMobile::format($payment->user?->mobile_number) ?? '—' }}</p>
                <dl class="mt-4 space-y-2 border-t border-line pt-4 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-taupe">Scheme</dt><dd class="font-mono text-xs">{{ $scheme?->scheme_number }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-taupe">Plan</dt><dd class="text-right font-medium">{{ $scheme?->plan?->name }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-taupe">Monthly</dt><dd class="font-medium tabular-nums">{{ $scheme?->amountRangeLabel() ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-taupe">Scheme status</dt><dd class="font-medium">{{ $scheme?->status->label() }}</dd></div>
                </dl>
            </section>

            <section class="rounded-lg border border-line bg-white p-5" aria-labelledby="events-heading">
                <h2 id="events-heading" class="font-sans text-[0.7rem] font-semibold tracking-[0.22em] text-taupe uppercase">History</h2>
                @if ($payment->events->isEmpty())
                    <p class="mt-3 text-sm text-taupe">No activity recorded.</p>
                @else
                    <ol class="mt-4 space-y-4 border-l border-line pl-4 text-sm" data-payment-events>
                        @foreach ($payment->events as $event)
                            <li>
                                <p class="font-semibold">{{ $event->event->label() }}</p>
                                <p class="text-xs text-taupe">{{ $event->created_at?->format('d M Y, g:i:s A') }}@if ($event->actor) · {{ $event->actor->name }}@else · Gateway @endif</p>
                                @if ($event->note)
                                    <p class="mt-1 text-xs break-words text-taupe">{{ $event->note }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </aside>
    </div>
@endsection
