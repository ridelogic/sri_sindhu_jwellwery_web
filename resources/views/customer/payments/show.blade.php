@extends('layouts.app')

@section('title', 'Payment '.$payment->reference.' | Sri Sindhu Jewellery')
@section('meta_description', 'Your gold saving payment details.')
@section('robots', 'noindex, nofollow')

@use('App\Enums\PaymentStatus')
@use('App\Support\Money')

@php
    $gold = $payment->goldTransaction;
    $scheme = $payment->customerPlan;
    $heading = match ($payment->status) {
        PaymentStatus::Success => 'Payment Successful',
        PaymentStatus::Pending => 'Payment Being Confirmed',
        PaymentStatus::Created => 'Payment Not Completed Yet',
        PaymentStatus::Failed => 'Payment Failed',
        PaymentStatus::Cancelled => 'Payment Cancelled',
    };
@endphp

@section('content')
    <x-customer-shell title="Payment Details" emphasis="Details" :eyebrow="'Payment '.$payment->reference">
        <a href="{{ route('customer.payments.index') }}" class="link-line text-obsidian">
            <x-icon name="arrow-right" class="size-3.5 rotate-180" />
            Payment History
        </a>

        @error('payment')
            <div class="mt-6 border-l-2 border-red-400 bg-red-50 px-5 py-4 text-sm text-red-800" role="alert">{{ $message }}</div>
        @enderror

        <div class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <section class="border border-line bg-white" aria-labelledby="payment-heading" data-payment-detail="{{ $payment->status->value }}">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-6 py-5">
                    <h2 id="payment-heading" class="text-2xl">{{ $heading }}</h2>
                    <x-payment-status :status="$payment->status" />
                </header>

                <dl class="grid gap-px bg-line sm:grid-cols-2">
                    @foreach (array_filter([
                        ['Amount', Money::inr($payment->amount()), 'font-serif text-3xl tabular-nums', 'amount'],
                        ['Payment Date', ($payment->paid_at ?? $payment->initiated_at)?->format('d M Y, g:i A'), '', 'date'],
                        ['Scheme Number', $scheme?->scheme_number, 'font-mono text-base', 'scheme'],
                        ['Plan', $scheme?->plan?->name, '', 'plan'],
                        ['Installment', ($scheme ? $scheme->installmentsLabel($payment->installment_number) : $payment->installment_number), '', 'installment'],
                        ['Transaction Reference', $payment->reference, 'font-mono text-base break-all', 'reference'],
                        $payment->gateway_payment_id ? ['Gateway Reference', $payment->gateway_payment_id, 'font-mono text-sm break-all', 'gateway-reference'] : null,
                        $payment->payment_method ? ['Payment Method', \Illuminate\Support\Str::upper($payment->payment_method), '', 'method'] : null,
                    ]) as [$label, $value, $class, $key])
                        <div class="bg-white px-6 py-4" data-field="{{ $key }}">
                            <dt class="text-xs text-taupe">{{ $label }}</dt>
                            <dd class="mt-1 font-semibold {{ str_contains($class, 'text-') ? $class : 'text-lg '.$class }}">{{ $value ?? '—' }}</dd>
                        </div>
                    @endforeach
                </dl>

                @if ($payment->status === PaymentStatus::Failed && $payment->failure_reason)
                    <p class="border-t border-line bg-red-50 px-6 py-4 text-sm text-red-800" data-failure-reason>{{ $payment->failure_reason }}</p>
                @endif

                @if (in_array($payment->status, [PaymentStatus::Failed, PaymentStatus::Cancelled], true))
                    <div class="border-t border-line px-6 py-5">
                        <p class="text-sm text-taupe">No gold was credited for this attempt.</p>
                        <a href="{{ route('customer.schemes.payment', $scheme->scheme_number) }}" class="btn btn-dark mt-4 w-full sm:w-auto" data-retry>Try Again</a>
                    </div>
                @elseif ($payment->status === PaymentStatus::Created && $payment->gateway_order_id)
                    <div class="border-t border-line px-6 py-5">
                        <a href="{{ route('customer.payments.checkout', $payment->reference) }}" class="btn btn-gold w-full sm:w-auto">Continue Payment</a>
                    </div>
                @elseif ($payment->status === PaymentStatus::Pending)
                    <p class="border-t border-line px-6 py-4 text-sm leading-relaxed text-taupe">We are waiting for the bank to confirm this payment. Gold is credited only once it is confirmed — please check back shortly.</p>
                @endif
            </section>

            <aside class="h-fit space-y-4">
                @if ($gold)
                    <section class="bg-obsidian p-6 text-ivory" aria-labelledby="gold-heading" data-gold-credit>
                        <h2 id="gold-heading" class="font-sans text-[0.7rem] font-semibold tracking-[0.22em] text-champagne uppercase">Gold Credited</h2>
                        <p class="mt-4 font-serif text-4xl text-gold-bright tabular-nums" data-gold-grams>{{ $gold->gold_grams }} g</p>
                        <dl class="mt-5 space-y-3 border-t border-ivory/10 pt-5 text-sm">
                            <div class="flex justify-between gap-4"><dt class="text-stone">Purity</dt><dd class="font-semibold" data-gold-purity>{{ $gold->purity->value }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-stone">Gold Rate</dt><dd class="font-semibold tabular-nums" data-gold-rate>{{ Money::inr($gold->ratePerGram()) }}/g</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-stone">Rate Effective</dt><dd class="text-right font-semibold">{{ $gold->rateEffectiveLabel() }}</dd></div>
                        </dl>
                        <p class="mt-4 text-xs leading-relaxed text-stone">This rate is fixed to this payment permanently. Later rate changes do not affect it.</p>
                    </section>
                    <a href="{{ route('customer.payments.receipt', $payment->reference) }}" class="btn btn-ghost-dark w-full" data-receipt-link>
                        <x-icon name="receipt" class="size-4" /> View Receipt
                    </a>
                @endif
                <a href="{{ route('customer.schemes.show', $scheme->scheme_number) }}" class="link-line text-obsidian">View Scheme <x-icon name="arrow-right" class="size-3.5" /></a>
            </aside>
        </div>
    </x-customer-shell>
@endsection
