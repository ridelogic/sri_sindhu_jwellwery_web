@extends('layouts.app')

@section('title', 'Payment History | Sri Sindhu Jewellery')
@section('meta_description', 'Your gold saving payment history.')
@section('robots', 'noindex, nofollow')

@use('App\Support\Money')

@section('content')
    <x-customer-shell title="Payment History" emphasis="History" text="Every installment you have paid, with the gold rate it was credited at.">
        @if ($payments->isEmpty())
            <div class="border border-dashed border-line px-6 py-12 text-center" data-empty-payments>
                <p class="font-serif text-2xl">No payments yet.</p>
                <p class="mt-2 text-sm text-taupe">Your installments will appear here once you make a payment.</p>
                <a href="{{ route('customer.schemes.index') }}" class="btn btn-gold mt-6">My Schemes <x-icon name="arrow-right" class="size-4" /></a>
            </div>
        @else
            {{-- Phones: cards --}}
            <ul class="space-y-4 md:hidden" data-payment-cards>
                @foreach ($payments as $payment)
                    @php $gold = $payment->goldTransaction; @endphp
                    <li class="border border-line bg-white">
                        <a href="{{ route('customer.payments.show', $payment->reference) }}" class="block px-5 py-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs text-taupe">{{ ($payment->paid_at ?? $payment->initiated_at)?->format('d M Y, g:i A') }}</p>
                                    <p class="mt-1 font-serif text-2xl font-semibold tabular-nums">{{ Money::inr($payment->amount()) }}</p>
                                </div>
                                <x-payment-status :status="$payment->status" />
                            </div>
                            <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-xs">
                                <div><dt class="text-taupe">Scheme</dt><dd class="font-mono font-semibold">{{ $payment->customerPlan?->scheme_number }}</dd></div>
                                <div><dt class="text-taupe">Gold Earned</dt><dd class="font-semibold tabular-nums">{{ $gold ? $gold->gold_grams.' g' : '—' }}</dd></div>
                                <div><dt class="text-taupe">Gold Rate</dt><dd class="font-semibold tabular-nums">{{ $gold ? Money::inr($gold->ratePerGram()).'/g' : '—' }}</dd></div>
                                <div><dt class="text-taupe">Reference</dt><dd class="font-mono break-all">{{ $payment->reference }}</dd></div>
                            </dl>
                        </a>
                    </li>
                @endforeach
            </ul>

            {{-- Tablets and up: table --}}
            <div class="hidden border border-line bg-white md:block">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm" data-table="payments">
                        <thead class="border-b border-line text-[0.64rem] tracking-[0.18em] text-taupe uppercase">
                            <tr>
                                <th scope="col" class="px-5 py-3 font-semibold">Payment Date</th>
                                <th scope="col" class="px-5 py-3 font-semibold">Scheme</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">Amount</th>
                                <th scope="col" class="px-5 py-3 font-semibold">Status</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">Gold Rate</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">Gold Earned</th>
                                <th scope="col" class="px-5 py-3 font-semibold">Reference</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($payments as $payment)
                                @php $gold = $payment->goldTransaction; @endphp
                                <tr data-payment-row="{{ $payment->reference }}">
                                    <td class="px-5 py-3 whitespace-nowrap">{{ ($payment->paid_at ?? $payment->initiated_at)?->format('d M Y') }}</td>
                                    <td class="px-5 py-3 font-mono text-xs whitespace-nowrap">{{ $payment->customerPlan?->scheme_number }}</td>
                                    <td class="px-5 py-3 text-right font-semibold whitespace-nowrap tabular-nums">{{ Money::inr($payment->amount()) }}</td>
                                    <td class="px-5 py-3"><x-payment-status :status="$payment->status" /></td>
                                    <td class="px-5 py-3 text-right whitespace-nowrap tabular-nums" data-payment-rate>{{ $gold ? Money::inr($gold->ratePerGram()).'/g' : '—' }}</td>
                                    <td class="px-5 py-3 text-right font-semibold whitespace-nowrap tabular-nums" data-payment-gold>{{ $gold ? $gold->gold_grams.' g' : '—' }}</td>
                                    <td class="px-5 py-3 whitespace-nowrap"><a href="{{ route('customer.payments.show', $payment->reference) }}" class="font-mono text-xs text-gold-deep underline underline-offset-4 hover:text-obsidian">{{ $payment->reference }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($payments->hasPages())
                <div class="mt-6">{{ $payments->links() }}</div>
            @endif
        @endif
    </x-customer-shell>
@endsection
