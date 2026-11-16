@extends('layouts.app')

@section('title', 'Pay Installment | '.$scheme->scheme_number.' | Sri Sindhu Jewellery')
@section('meta_description', 'Pay your gold saving installment.')
@section('robots', 'noindex, nofollow')

@use('App\Support\Money')

@php
    $installments = $eligibility['installments'];
    $rate = $eligibility['rate'];
    $amountPaise = $eligibility['amount_paise'];
    $creditsGold = $eligibility['credits_gold'];
    $fixedAmount = $eligibility['fixed_amount'];
    $estimate = $rate && $creditsGold ? app(\App\Services\GoldCalculationService::class)->gramsFor($amountPaise, \App\Support\Money::toPaise($rate->rate_per_gram)) : null;
@endphp

@section('content')
    <x-customer-shell title="Pay Installment" emphasis="Installment" :eyebrow="'Scheme '.$scheme->scheme_number">
        <a href="{{ route('customer.schemes.show', $scheme->scheme_number) }}" class="link-line text-obsidian">
            <x-icon name="arrow-right" class="size-3.5 rotate-180" />
            Back to Scheme
        </a>

        <div class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <section class="border border-line bg-white" aria-labelledby="summary-heading">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-6 py-5">
                    <h2 id="summary-heading" class="text-2xl">Payment Summary</h2>
                    <x-scheme-status :status="$scheme->status" />
                </header>
                <dl class="grid gap-px bg-line sm:grid-cols-2" data-payment-summary>
                    @foreach ([
                        ['Scheme Number', $scheme->scheme_number, 'font-mono text-base'],
                        ['Plan', $scheme->plan?->name ?? 'Gold Saving Plan', ''],
                        [$scheme->contributionLabel(), $scheme->amountRangeLabel(), 'tabular-nums'],
                        ['Installments Paid', $scheme->installmentsLabel($installments['paid']), ''],
                    ] as [$label, $value, $class])
                        <div class="bg-white px-6 py-4">
                            <dt class="text-xs text-taupe">{{ $label }}</dt>
                            <dd class="mt-1 text-lg font-semibold {{ $class }}">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>

                <div class="border-t border-line bg-parchment/50 px-6 py-6">
                    <p class="text-xs text-taupe">
                        Amount Due
                        @if ($installments['next_number']) — Installment {{ $scheme->installmentsLabel($installments['next_number']) }} @endif
                    </p>
                    @if ($fixedAmount)
                        <p class="mt-1 font-serif text-5xl font-semibold tabular-nums" data-amount-due>{{ Money::inr(Money::fromPaise($amountPaise)) }}</p>
                        <p class="mt-2 text-xs leading-relaxed text-taupe">Your installment is the amount agreed when you joined and is not affected by later changes to the plan.</p>
                    @else
                        <p class="mt-1 font-serif text-3xl font-semibold tabular-nums" data-amount-range>{{ $scheme->amountRangeLabel() }}</p>
                        <p class="mt-2 text-xs leading-relaxed text-taupe">Choose any amount within your scheme's limits, agreed when you joined.</p>
                    @endif
                </div>

                <div class="border-t border-line px-6 py-6">
                    @if ($eligibility['allowed'])
                        @error('payment')
                            <div class="mb-5 border-l-2 border-red-400 bg-red-50 px-5 py-4 text-sm text-red-800" role="alert">{{ $message }}</div>
                        @enderror
                        <form method="POST" action="{{ route('customer.schemes.payment.store', $scheme->scheme_number) }}" data-payment-form>
                            @csrf
                            <input type="hidden" name="payment_token" value="{{ $token }}">
                            @unless ($fixedAmount)
                                <div class="mb-5 max-w-xs">
                                    <x-form.input name="amount" label="Amount (₹)" type="number" step="0.01" inputmode="decimal" required data-payment-amount
                                        :min="Money::fromPaise($amountPaise)" :max="$eligibility['amount_max_paise'] !== null ? Money::fromPaise($eligibility['amount_max_paise']) : null"
                                        :value="old('amount', Money::fromPaise($amountPaise))" />
                                </div>
                            @endunless
                            <button type="submit" class="btn btn-gold w-full sm:w-auto" data-submit-once>
                                <x-icon name="lock" class="size-4" />
                                {{ $fixedAmount ? 'Pay '.Money::inr(Money::fromPaise($amountPaise)).' Securely' : 'Pay Securely' }}
                            </button>
                        </form>
                        <p class="mt-4 text-xs leading-relaxed text-taupe">You will be taken to our secure payment partner. {{ $creditsGold ? 'Gold is credited only after the payment is confirmed with the bank.' : 'Your payment is recorded once it is confirmed with the bank.' }}</p>
                    @else
                        <div class="border-l-2 border-champagne bg-parchment px-5 py-4" data-payment-blocked="{{ $eligibility['reason'] }}">
                            <p class="text-sm leading-relaxed">{{ $eligibility['message'] }}</p>
                            @if ($eligibility['reason'] === 'kyc')
                                <a href="{{ route('customer.kyc') }}" class="link-line mt-3 text-obsidian">Go to KYC <x-icon name="arrow-right" class="size-3.5" /></a>
                            @elseif ($eligibility['reason'] === 'gateway')
                                <a href="{{ route('contact') }}" class="link-line mt-3 text-obsidian">Contact Us <x-icon name="arrow-right" class="size-3.5" /></a>
                            @endif
                        </div>
                    @endif
                </div>
            </section>

            <aside class="h-fit space-y-4">
                @if (! $creditsGold)
                    <div class="bg-obsidian p-6 text-ivory" data-output-note="{{ $scheme->outputType()->value }}">
                        <h2 class="font-sans text-[0.7rem] font-semibold tracking-[0.22em] text-champagne uppercase">Output: {{ $scheme->outputType()->label() }}</h2>
                        <p class="mt-4 text-sm leading-relaxed text-ivory/80">Your payments are recorded as money in this scheme. No gold is credited with each payment; settlement follows your scheme terms.</p>
                    </div>
                @else
                <div class="bg-obsidian p-6 text-ivory">
                    <h2 class="font-sans text-[0.7rem] font-semibold tracking-[0.22em] text-champagne uppercase">How your gold is calculated</h2>
                    <p class="mt-4 text-sm leading-relaxed text-ivory/80">Your payment buys {{ $purity->value }} gold at the rate in effect when the payment is confirmed. That rate and the gold credited are recorded permanently with your payment.</p>
                    @if ($rate)
                        <dl class="mt-5 space-y-3 border-t border-ivory/10 pt-5 text-sm">
                            <div class="flex justify-between gap-4"><dt class="text-stone">Today's {{ $rate->purity->value }} rate</dt><dd class="font-semibold tabular-nums" data-today-rate>{{ Money::inr($rate->rate_per_gram) }}/g</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-stone">Estimated gold</dt><dd class="font-semibold tabular-nums text-gold-bright" data-estimated-gold>{{ $estimate }} g</dd></div>
                        </dl>
                        <p class="mt-3 text-xs text-stone">Estimate only. If the rate changes before your payment is confirmed, the confirmed rate applies.</p>
                    @endif
                </div>
                @endif
                <div class="border border-line bg-white px-6 py-5 text-sm leading-relaxed text-taupe">
                    <x-icon name="shield" class="size-5 text-gold-deep" />
                    <p class="mt-3">We never see or store your card or bank details. Payments are verified with the payment gateway before any gold is credited.</p>
                </div>
            </aside>
        </div>
    </x-customer-shell>
@endsection
