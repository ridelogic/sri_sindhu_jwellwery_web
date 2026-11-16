@use('App\Support\Money')
@php
    $gold = $payment->goldTransaction;
    $scheme = $payment->customerPlan;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Receipt {{ $payment->reference }} · {{ config('site.name', 'Sri Sindhu Jewellery') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            body { background: #fff !important; }
            [data-receipt-actions] { display: none !important; }
            [data-receipt] { border: 0 !important; box-shadow: none !important; margin: 0 !important; max-width: none !important; }
        }
    </style>
</head>
<body class="min-h-screen bg-parchment font-sans text-obsidian antialiased">
    <div class="mx-auto max-w-2xl px-4 py-8 sm:py-12">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3" data-receipt-actions>
            <a href="{{ route('customer.payments.show', $payment->reference) }}" class="link-line text-obsidian"><x-icon name="arrow-right" class="size-3.5 rotate-180" /> Back to Payment</a>
            <button type="button" onclick="window.print()" class="btn btn-dark">Print Receipt</button>
        </div>

        <article class="border border-line bg-white" data-receipt>
            <header class="flex flex-wrap items-start justify-between gap-4 border-b border-line px-6 py-6 sm:px-10">
                <div>
                    <p class="font-serif text-3xl font-semibold">{{ config('site.name', 'Sri Sindhu Jewellery') }}</p>
                    <p class="mt-1 text-[0.66rem] font-semibold tracking-[0.28em] text-gold-deep uppercase">Gold Saving Scheme</p>
                </div>
                <div class="sm:text-right">
                    <p class="text-[0.66rem] font-semibold tracking-[0.24em] text-taupe uppercase">Payment Receipt</p>
                    <p class="mt-1 font-mono text-sm">{{ $payment->reference }}</p>
                </div>
            </header>

            <div class="px-6 py-6 sm:px-10">
                <div class="flex flex-wrap items-end justify-between gap-4 border-b border-dashed border-line pb-6">
                    <div>
                        <p class="text-xs text-taupe">Amount Paid</p>
                        <p class="mt-1 font-serif text-5xl font-semibold tabular-nums" data-receipt-amount>{{ Money::inr($payment->amount()) }}</p>
                    </div>
                    <span class="inline-flex items-center border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-[0.66rem] font-semibold tracking-[0.18em] text-emerald-800 uppercase" data-receipt-status>{{ $payment->status->label() }}</span>
                </div>

                <dl class="divide-y divide-line text-sm">
                    @foreach ([
                        ['Customer Name', $payment->user->name],
                        ['Scheme Number', $scheme->scheme_number],
                        ['Plan', $scheme->plan?->name],
                        ['Installment', $scheme->installmentsLabel($payment->installment_number)],
                        ['Payment Date', $payment->paid_at?->format('d M Y, g:i A')],
                        ['Payment Amount', Money::inr($payment->amount())],
                        ['Gold Rate', Money::inr($gold->ratePerGram()).' per gram ('.$gold->rateEffectiveLabel().')'],
                        ['Gold Accumulated', $gold->gold_grams.' g'],
                        ['Purity', $gold->purity->value.' — '.$gold->purity->description()],
                        ['Payment Method', $payment->payment_method ? \Illuminate\Support\Str::upper($payment->payment_method) : '—'],
                        ['Gateway Reference', $payment->gateway_payment_id],
                        ['Payment Status', $payment->status->label()],
                    ] as [$label, $value])
                        <div class="grid gap-1 py-3 sm:grid-cols-3">
                            <dt class="text-taupe">{{ $label }}</dt>
                            <dd class="font-semibold break-words sm:col-span-2">{{ $value ?? '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            <footer class="border-t border-line px-6 py-5 text-xs leading-relaxed text-taupe sm:px-10">
                The gold rate shown is the rate in effect when this payment was confirmed and is fixed to this payment.
                This is a computer-generated receipt for a gold saving scheme installment and does not require a signature.
                Generated {{ now()->format('d M Y, g:i A') }} IST.
            </footer>
        </article>
    </div>
</body>
</html>
