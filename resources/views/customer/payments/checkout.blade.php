@extends('layouts.app')

@section('title', 'Secure Payment | Sri Sindhu Jewellery')
@section('meta_description', 'Complete your gold saving installment payment.')
@section('robots', 'noindex, nofollow')

@use('App\Support\Money')

@section('content')
    <x-customer-shell title="Secure Payment" emphasis="Payment" :eyebrow="'Scheme '.$payment->customerPlan->scheme_number">
        <div class="mx-auto max-w-xl">
            <section class="border border-line bg-white" aria-labelledby="checkout-heading" data-checkout="{{ $payment->reference }}">
                <header class="border-b border-line px-6 py-5">
                    <h2 id="checkout-heading" class="text-2xl">Installment {{ $payment->installment_number }}</h2>
                    <p class="mt-1 text-xs text-taupe">{{ $payment->customerPlan->plan?->name }} · Reference <span class="font-mono">{{ $payment->reference }}</span></p>
                </header>
                <div class="px-6 py-6">
                    <p class="text-xs text-taupe">Amount</p>
                    <p class="mt-1 font-serif text-5xl font-semibold tabular-nums">{{ Money::inr($payment->amount()) }}</p>

                    <div class="mt-6">
                        @include($gatewayView, $gatewayData + ['payment' => $payment])
                    </div>
                </div>
                <footer class="border-t border-line px-6 py-4">
                    <form method="POST" action="{{ route('customer.payments.cancel', $payment->reference) }}" data-cancel-form>
                        @csrf
                        <input type="hidden" name="outcome" value="cancelled">
                        <button type="submit" class="text-xs text-taupe underline underline-offset-4 hover:text-obsidian">Cancel this payment</button>
                    </form>
                </footer>
            </section>
            <p class="mt-4 flex items-start gap-2 text-xs leading-relaxed text-taupe">
                <x-icon name="lock" class="mt-0.5 size-4 text-gold-deep" />
                Gold is credited only after the payment is verified with the payment gateway. Do not close this page until you see the result.
            </p>
        </div>
    </x-customer-shell>
@endsection
