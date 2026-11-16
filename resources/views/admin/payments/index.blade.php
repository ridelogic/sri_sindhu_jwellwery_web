@extends('layouts.admin')

@section('title', 'Payments')
@section('subtitle', 'All installment payment attempts and their outcomes.')

@use('App\Support\Money')

@php
    $filters = [
        ['key' => null, 'label' => 'All'],
        ['key' => 'pending', 'label' => 'Pending'],
        ['key' => 'success', 'label' => 'Success'],
        ['key' => 'failed', 'label' => 'Failed'],
        ['key' => 'cancelled', 'label' => 'Cancelled'],
    ];
@endphp

@php
    // Read-only display figures (no business logic): today's verified payments and unconfirmed attempts.
    $todaySuccessful = \App\Models\Payment::query()->successful()->where('paid_at', '>=', today());
    $todayCount = (clone $todaySuccessful)->count();
    $todayAmount = (int) (clone $todaySuccessful)->sum('amount_paise');
    $attention = \App\Models\Payment::query()->where('status', \App\Enums\PaymentStatus::Pending->value)->count();
@endphp

@section('content')
    <section aria-label="Payment summary" class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.kpi-card label="Today's Payments" :value="number_format($todayCount)" key="today" icon="receipt" :hint="Money::inr(Money::fromPaise($todayAmount)).' received today'" />
        <x-admin.kpi-card label="Pending" :value="number_format($counts['pending'])" key="pending" icon="clock" hint="Awaiting a gateway outcome" :href="route('admin.payments.index', ['status' => 'pending'])" link="View pending" tone="gold" />
        <x-admin.kpi-card label="Successful" :value="number_format($counts['success'])" key="successful" icon="check" hint="Verified and gold credited" :href="route('admin.payments.index', ['status' => 'success'])" link="View successful" />
        <x-admin.kpi-card label="Requires Attention" :value="number_format($attention)" key="attention" icon="alert" :tone="$attention > 0 ? 'gold' : 'emerald'" hint="Unconfirmed by the gateway — check before crediting" />
    </section>
    <x-admin.filter-bar :action="route('admin.payments.index')" :reset="route('admin.payments.index')" :active="(bool) $filter">
        <label class="block">
            <span data-form-label class="mb-1.5 block">Status</span>
            <select name="status" class="block w-full px-3 py-2 text-sm" data-payment-status-filter>
                @foreach ($filters as $item)
                    <option value="{{ $item['key'] }}" @selected($filter === $item['key'])>{{ $item['label'] }} ({{ $counts[$item['key'] ?? 'all'] }})</option>
                @endforeach
            </select>
        </label>
        <p class="self-center text-xs text-ad-muted lg:col-span-2">Payments are permanent records — no edit or delete. “Pending” includes attempts still awaiting a gateway outcome.</p>
    </x-admin.filter-bar>

    <section class="rounded-lg border border-line bg-white" aria-label="Payments">
        @if ($payments->isEmpty())
            <p class="px-5 py-8 text-sm text-taupe" data-payments-empty>No payments {{ $filter ? 'with this status' : 'yet' }}.</p>
        @else
            <p class="px-5 pt-3 text-xs text-taupe lg:hidden">Swipe the table sideways to see all columns.</p>
            <div class="overflow-x-auto" data-scroll-table>
                <table class="w-full min-w-[54rem] text-left text-sm" data-table="payments">
                    <thead class="text-[0.68rem] tracking-[0.14em] text-taupe uppercase">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-semibold">Date</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Customer</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Scheme</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">Amount</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Status</th>
                            <th scope="col" class="hidden px-5 py-3 font-semibold 2xl:table-cell">Gateway</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Reference</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">Gold</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($payments as $payment)
                            <tr data-payment-row="{{ $payment->id }}" data-payment-row-status="{{ $payment->status->value }}">
                                <td class="px-5 py-3 whitespace-nowrap text-taupe">{{ ($payment->paid_at ?? $payment->initiated_at)?->format('d M Y, g:i A') }}</td>
                                <td class="px-5 py-3 font-semibold whitespace-nowrap">{{ $payment->user?->name }}</td>
                                <td class="px-5 py-3 font-mono text-xs whitespace-nowrap">{{ $payment->customerPlan?->scheme_number }}</td>
                                <td class="px-5 py-3 text-right font-semibold whitespace-nowrap tabular-nums">{{ Money::inr($payment->amount()) }}</td>
                                <td class="px-5 py-3"><x-payment-status :status="$payment->status" variant="admin" /></td>
                                <td class="hidden px-5 py-3 whitespace-nowrap text-taupe 2xl:table-cell">{{ ucfirst($payment->gateway) }}</td>
                                <td class="px-5 py-3 whitespace-nowrap"><a href="{{ route('admin.payments.show', $payment) }}" class="font-mono text-xs font-semibold text-gold-deep hover:text-ad-ink">{{ $payment->reference }}</a></td>
                                <td class="px-5 py-3 text-right whitespace-nowrap tabular-nums">{{ $payment->goldTransaction ? $payment->goldTransaction->gold_grams.' g' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($payments->hasPages())
                <div class="border-t border-line px-5 py-4">{{ $payments->links('admin.partials.pagination') }}</div>
            @endif
        @endif
    </section>
@endsection
