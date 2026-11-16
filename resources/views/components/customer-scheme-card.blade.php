@props(['scheme', 'progress'])

{{--
    A customer's own scheme enrolment. Commitment figures are the values COPIED
    at enrolment (customer_plans), never the plan's current values. Payment-
    based figures are genuine empty states until payments exist.
--}}
@use('App\Support\Money')

<article {{ $attributes->merge(['class' => 'flex h-full flex-col border border-line bg-white']) }} data-scheme-card="{{ $scheme->scheme_number }}">
    <header class="flex flex-wrap items-start justify-between gap-3 border-b border-line px-6 py-5">
        <div>
            <p class="font-mono text-xs tracking-wider text-taupe">{{ $scheme->scheme_number }}</p>
            <h3 class="mt-1 text-[1.75rem] leading-tight">{{ $scheme->plan?->name ?? 'Gold Saving Plan' }}</h3>
        </div>
        <x-scheme-status :status="$scheme->status" />
    </header>

    <dl class="grid grid-cols-2 gap-px bg-line">
        <div class="bg-white px-6 py-4">
            <dt class="text-xs text-taupe">{{ $scheme->contributionLabel() }}</dt>
            <dd class="mt-1 text-lg font-semibold tabular-nums" data-scheme-monthly>{{ $scheme->amountRangeLabel() }}</dd>
        </div>
        <div class="bg-white px-6 py-4">
            <dt class="text-xs text-taupe">Tenure</dt>
            <dd class="mt-1 text-lg font-semibold">{{ $scheme->tenureLabel() }}</dd>
        </div>
        <div class="bg-white px-6 py-4">
            <dt class="text-xs text-taupe">Total Commitment</dt>
            <dd class="mt-1 text-lg font-semibold tabular-nums" data-scheme-total>{{ ($scheme->total_commitment !== null ? Money::inr($scheme->total_commitment) : 'Open-ended') }}</dd>
        </div>
        <div class="bg-white px-6 py-4">
            <dt class="text-xs text-taupe">Joined</dt>
            <dd class="mt-1 text-lg font-semibold">{{ $scheme->created_at?->format('d M Y') }}</dd>
        </div>
    </dl>

    <dl class="grid grid-cols-3 gap-px border-t border-line bg-line text-sm">
        <div class="bg-parchment/50 px-4 py-3 sm:px-6">
            <dt class="text-xs text-taupe">Amount Paid</dt>
            <dd class="mt-1 font-semibold tabular-nums" data-scheme-paid>{{ Money::inr($progress['amount_paid']) }}</dd>
        </div>
        <div class="bg-parchment/50 px-4 py-3 sm:px-6">
            <dt class="text-xs text-taupe">Gold</dt>
            <dd class="mt-1 font-semibold tabular-nums" data-scheme-gold>{{ $progress['gold_grams'] }} g</dd>
        </div>
        <div class="bg-parchment/50 px-4 py-3 sm:px-6">
            <dt class="text-xs text-taupe">Current Value</dt>
            <dd class="mt-1 text-xs leading-snug font-semibold text-gold-deep" data-scheme-value>{{ $progress['current_value'] === null ? ($progress['installments_paid'] > 0 ? 'Rate unavailable' : 'Awaiting first payment') : Money::inr($progress['current_value']) }}</dd>
        </div>
    </dl>

    <div class="mt-auto flex flex-wrap items-center justify-between gap-x-6 gap-y-3 px-6 py-4">
        <div class="text-xs text-taupe" data-scheme-installments>
            {{ $scheme->installmentsLabel($progress['installments_paid']) }} installments paid
            @if ($progress['last_payment_at'])
                <span class="block">Last payment {{ $progress['last_payment_at']->format('d M Y') }}</span>
            @endif
        </div>
        <div class="flex items-center gap-5">
            @if ($progress['next_installment'] && in_array($scheme->status, \App\Enums\CustomerPlanStatus::open(), true))
                <a href="{{ route('customer.schemes.payment', $scheme->scheme_number) }}" class="link-line text-gold-deep" data-pay-link>Pay Installment</a>
            @endif
            <a href="{{ route('customer.schemes.show', $scheme->scheme_number) }}" class="link-line text-obsidian">
                View Scheme
                <x-icon name="arrow-right" class="size-3.5" />
            </a>
        </div>
    </div>
</article>
