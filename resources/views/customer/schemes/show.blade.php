@extends('layouts.app')

@section('title', $scheme->scheme_number.' | My Schemes | Sri Sindhu Jewellery')
@section('meta_description', 'Your gold saving scheme details.')
@section('robots', 'noindex, nofollow')

@use('App\Support\Money')

@section('content')
    <x-customer-shell :title="$scheme->plan?->name ?? 'Gold Saving Scheme'" :eyebrow="'Scheme '.$scheme->scheme_number">
        <a href="{{ route('customer.schemes.index') }}" class="link-line text-obsidian">
            <x-icon name="arrow-right" class="size-3.5 rotate-180" />
            All Schemes
        </a>

        <div class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <section class="border border-line bg-white" aria-labelledby="commitment-heading">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-6 py-5">
                    <h2 id="commitment-heading" class="text-2xl">Your Commitment</h2>
                    <x-scheme-status :status="$scheme->status" />
                </header>
                <dl class="grid gap-px bg-line sm:grid-cols-2">
                    @foreach ([
                        ['Scheme Number', $scheme->scheme_number, 'font-mono text-base'],
                        ['Plan', $scheme->plan?->name, ''],
                        [$scheme->contributionLabel(), $scheme->amountRangeLabel(), 'tabular-nums'],
                        ['Contribution Frequency', $scheme->contributionFrequency()->label(), ''],
                        ['Output', $scheme->outputType()->label(), ''],
                        ['Tenure', $scheme->tenureLabel(), ''],
                        ['Total Commitment', ($scheme->total_commitment !== null ? Money::inr($scheme->total_commitment) : 'Open-ended'), 'tabular-nums'],
                        ['Bonus', $scheme->bonusLabel(), ''],
                        ['Early Exit', $scheme->early_exit_allowed ? 'Allowed' : 'Not allowed', ''],
                        ['Joined', $scheme->created_at?->format('d M Y'), ''],
                        ['Start Date', $scheme->start_date?->format('d M Y'), ''],
                        ['Expected Maturity', $scheme->maturity_date?->format('d M Y') ?? '—', ''],
                    ] as [$label, $value, $class])
                        <div class="bg-white px-6 py-4">
                            <dt class="text-xs text-taupe">{{ $label }}</dt>
                            <dd class="mt-1 text-lg font-semibold {{ $class }}">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
                <p class="border-t border-line px-6 py-4 text-xs leading-relaxed text-taupe">These amounts were fixed when you joined and are not affected by later changes to the plan.</p>
            </section>

            <aside class="h-fit bg-obsidian p-6 text-ivory" aria-labelledby="progress-heading">
                <h2 id="progress-heading" class="font-sans text-[0.7rem] font-semibold tracking-[0.22em] text-champagne uppercase">Progress</h2>
                <dl class="mt-5 space-y-4">
                    <div><dt class="text-xs text-stone">Total Contributed</dt><dd class="mt-1 font-serif text-3xl tabular-nums" data-scheme-paid>{{ Money::inr($progress['amount_paid']) }}</dd></div>
                    <div><dt class="text-xs text-stone">Installments Paid</dt><dd class="mt-1 text-lg font-semibold">{{ $scheme->installmentsLabel($progress['installments_paid']) }}</dd></div>
                    <div><dt class="text-xs text-stone">Gold Accumulated</dt><dd class="mt-1 text-lg font-semibold tabular-nums" data-scheme-gold>{{ $progress['gold_grams'] }} g</dd></div>
                    <div><dt class="text-xs text-stone">Current Value</dt><dd class="mt-1 text-lg font-semibold text-gold-bright">{{ $progress['current_value'] === null ? ($progress['installments_paid'] > 0 ? 'Rate unavailable' : 'Awaiting first payment') : Money::inr($progress['current_value']) }}</dd></div>
                    @if ($progress['last_payment_at'])
                        <div><dt class="text-xs text-stone">Last Payment</dt><dd class="mt-1 text-sm font-semibold">{{ $progress['last_payment_at']->format('d M Y') }}</dd></div>
                    @endif
                </dl>
                @if ($progress['current_value'] !== null)
                    <p class="mt-3 text-xs text-stone">Current value uses today's gold rate. The gold you hold never changes.</p>
                @endif

                <div class="mt-6 border-t border-ivory/10 pt-5" data-scheme-payment-cta="{{ $eligibility['allowed'] ? 'allowed' : $eligibility['reason'] }}">
                    @if ($eligibility['allowed'])
                        <p class="text-xs text-stone">Installment {{ $scheme->installmentsLabel($eligibility['installments']['next_number']) }} is due.</p>
                        <a href="{{ route('customer.schemes.payment', $scheme->scheme_number) }}" class="btn btn-gold mt-3 w-full">{{ $scheme->isFixedAmount() ? 'Pay '.Money::inr($scheme->minimumAmount()) : 'Pay Installment' }} <x-icon name="arrow-right" class="size-4" /></a>
                    @else
                        <p class="text-xs leading-relaxed text-stone">{{ $eligibility['message'] }}</p>
                        @if ($eligibility['reason'] === 'kyc')
                            <a href="{{ route('customer.kyc') }}" class="link-line mt-3 text-champagne">Go to KYC <x-icon name="arrow-right" class="size-3.5" /></a>
                        @endif
                    @endif
                </div>
            </aside>
        </div>

        {{-- Payment history for this scheme --}}
        <section class="mt-10" aria-labelledby="scheme-payments-heading">
            <h2 id="scheme-payments-heading" class="display text-[2rem] sm:text-4xl">Payment <em class="text-gold-deep">History</em></h2>
            @if ($payments->isEmpty())
                <p class="mt-6 border border-dashed border-line px-6 py-8 text-center text-sm text-taupe" data-empty-scheme-payments>No payments for this scheme yet.</p>
            @else
                <ul class="mt-6 divide-y divide-line border border-line bg-white" data-scheme-payments>
                    @foreach ($payments as $payment)
                        <li>
                            <a href="{{ route('customer.payments.show', $payment->reference) }}" class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 px-5 py-4 transition-colors hover:bg-parchment/50 sm:px-6">
                                <div class="min-w-0">
                                    <p class="font-semibold">Installment {{ $payment->installment_number }} · <span class="tabular-nums">{{ Money::inr($payment->amount()) }}</span></p>
                                    <p class="text-xs text-taupe">{{ ($payment->paid_at ?? $payment->initiated_at)?->format('d M Y, g:i A') }} · <span class="font-mono">{{ $payment->reference }}</span></p>
                                </div>
                                <div class="flex items-center gap-4">
                                    @if ($payment->goldTransaction)
                                        <span class="text-right text-xs text-taupe"><span class="block font-semibold text-obsidian tabular-nums">{{ $payment->goldTransaction->gold_grams }} g</span>at {{ Money::inr($payment->goldTransaction->ratePerGram()) }}/g</span>
                                    @endif
                                    <x-payment-status :status="$payment->status" />
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </x-customer-shell>
@endsection
