@extends('layouts.admin')

@section('title', 'Scheme '.$scheme->scheme_number)
@section('subtitle', 'Scheme details, payments and gold credited.')
@section('back', route('admin.customers.show', $customer))
@section('back_label', 'Back to customer')

@use('App\Enums\CustomerPlanStatus')
@use('App\Support\Money')

@php
    $statusStyles = [
        'active' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        'pending' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'completed' => 'bg-sky-50 text-sky-800 ring-sky-200',
        'matured' => 'bg-violet-50 text-violet-800 ring-violet-200',
        'cancelled' => 'bg-stone-100 text-stone-700 ring-stone-300',
    ];
    $statusLabel = $scheme->status === CustomerPlanStatus::Pending ? 'Awaiting First Payment' : $scheme->status->label();
    $statusNote = match ($scheme->status) {
        CustomerPlanStatus::Pending => 'Awaiting the first payment.',
        CustomerPlanStatus::Active => $scheme->installmentsLabel($progress['installments_paid']).' installments paid.',
        CustomerPlanStatus::Completed => 'All '.$scheme->installments_total.' installments have been paid.',
        CustomerPlanStatus::Matured => 'This scheme has matured.',
        CustomerPlanStatus::Cancelled => 'This scheme was cancelled.',
    };
    $percent = $scheme->installments_total > 0 ? min(100, (int) round($progress['installments_paid'] / $scheme->installments_total * 100)) : 0;
    $row = 'flex justify-between gap-4';
@endphp

@section('content')

    {{-- Header --}}
    <section class="mt-5 rounded-lg bg-obsidian p-5 text-ivory sm:p-7" aria-labelledby="scheme-heading" data-scheme-header>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="font-mono text-xs tracking-wider text-ad-ink">{{ $scheme->scheme_number }}</p>
                <h2 id="scheme-heading" class="mt-2 font-serif text-[2rem] leading-tight">{{ $scheme->plan?->name ?? 'Gold Saving Plan' }}</h2>
                <p class="mt-1 text-sm text-stone">{{ $customer->name }}</p>
            </div>
            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $statusStyles[$scheme->status->value] }}" data-scheme-status>{{ $statusLabel }}</span>
        </div>
        <div class="mt-6">
            <div class="flex justify-between text-xs text-stone"><span>{{ $scheme->installmentsLabel($progress['installments_paid']) }} installments</span><span>{{ $percent }}%</span></div>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-ivory/10" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}" aria-label="Installments paid">
                <div class="h-full rounded-full bg-champagne" style="width: {{ $percent }}%"></div>
            </div>
        </div>
    </section>

    {{-- Summary --}}
    <section aria-label="Summary" class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            [$scheme->contributionLabel(), $scheme->amountRangeLabel(), 'monthly', $scheme->outputType()->label().' output'],
            ['Total Commitment', ($scheme->total_commitment !== null ? Money::inr($scheme->total_commitment) : 'Open-ended'), 'commitment', $scheme->tenureLabel()],
            ['Amount Paid', Money::inr($progress['amount_paid']), 'paid', Money::inr($remaining).' remaining'],
            ['Gold Accumulated', $progress['gold_grams'].' g', 'gold', $goldTransactions->count().' gold '.\Illuminate\Support\Str::plural('credit', $goldTransactions->count())],
        ] as [$label, $value, $key, $hint])
            <article class="rounded-lg border border-line bg-white p-5" data-stat="{{ $key }}">
                <h3 class="font-sans text-[0.7rem] font-semibold tracking-[0.18em] text-taupe uppercase">{{ $label }}</h3>
                <p class="mt-3 text-[1.75rem] leading-tight font-semibold tabular-nums" data-stat-value>{{ $value }}</p>
                @if ($hint)<p class="mt-1 text-xs text-taupe" data-stat-hint>{{ $hint }}</p>@endif
            </article>
        @endforeach
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="min-w-0 space-y-6">
            {{-- Payment history --}}
            <section class="rounded-lg border border-line bg-white" aria-labelledby="payments-heading">
                <h2 id="payments-heading" class="border-b border-line px-5 py-4 font-serif text-2xl font-semibold sm:px-6">Payment History</h2>
                @if ($payments->isEmpty())
                    <p class="px-5 py-6 text-sm text-taupe sm:px-6" data-no-scheme-payments>No payments for this scheme yet.</p>
                @else
                    <p class="px-5 pt-3 text-xs text-taupe lg:hidden">Swipe the table sideways to see all columns.</p>
                    <div class="overflow-x-auto" data-scroll-table>
                        <table class="w-full min-w-[46rem] text-left text-sm" data-table="scheme-payments">
                            <thead class="text-[0.68rem] tracking-[0.14em] text-taupe uppercase">
                                <tr>
                                    <th scope="col" class="px-5 py-3 font-semibold">Date</th>
                                    <th scope="col" class="px-5 py-3 font-semibold">Reference</th>
                                    <th scope="col" class="px-5 py-3 text-right font-semibold">Amount</th>
                                    <th scope="col" class="px-5 py-3 font-semibold">Status</th>
                                    <th scope="col" class="px-5 py-3 text-right font-semibold">Gold Credited</th>
                                    <th scope="col" class="px-5 py-3 text-right font-semibold">Rate Used</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($payments as $payment)
                                    @php $gold = $payment->goldTransaction; @endphp
                                    <tr data-scheme-payment="{{ $payment->reference }}">
                                        <td class="px-5 py-3 whitespace-nowrap text-taupe">{{ ($payment->paid_at ?? $payment->initiated_at)?->format('d M Y, g:i A') }}</td>
                                        <td class="px-5 py-3 whitespace-nowrap"><a href="{{ route('admin.payments.show', $payment) }}" class="font-mono text-xs font-semibold text-gold-deep hover:text-ad-ink">{{ $payment->reference }}</a></td>
                                        <td class="px-5 py-3 text-right font-semibold whitespace-nowrap tabular-nums">{{ Money::inr($payment->amount()) }}</td>
                                        <td class="px-5 py-3"><x-payment-status :status="$payment->status" variant="admin" /></td>
                                        <td class="px-5 py-3 text-right whitespace-nowrap tabular-nums">{{ $gold ? $gold->gold_grams.' g' : '—' }}</td>
                                        <td class="px-5 py-3 text-right whitespace-nowrap tabular-nums">{{ $gold ? Money::inr($gold->ratePerGram()).'/g' : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- Gold ledger --}}
            <section class="rounded-lg border border-line bg-white" aria-labelledby="gold-heading">
                <h2 id="gold-heading" class="border-b border-line px-5 py-4 font-serif text-2xl font-semibold sm:px-6">Gold Transaction History</h2>
                @if ($goldTransactions->isEmpty())
                    <p class="px-5 py-6 text-sm text-taupe sm:px-6" data-no-gold>No gold credited yet. Gold is credited only for verified successful payments.</p>
                @else
                    <ul class="divide-y divide-line" data-gold-ledger>
                        @foreach ($goldTransactions as $gold)
                            <li class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 px-5 py-4 sm:px-6" data-gold-row>
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-parchment text-gold-deep"><x-icon name="gem" class="size-4" /></span>
                                    <div class="min-w-0">
                                        <p class="font-semibold tabular-nums">{{ $gold->gold_grams }} g <span class="font-normal text-taupe">{{ $gold->purity->value }}</span></p>
                                        <p class="text-xs text-taupe">{{ $gold->transaction_date->format('d M Y, g:i A') }} · {{ $gold->payment?->reference }}</p>
                                    </div>
                                </div>
                                <div class="text-right text-sm">
                                    <p class="font-semibold tabular-nums">{{ Money::inr(Money::fromPaise($gold->amount_paise)) }}</p>
                                    <p class="text-xs text-taupe">at {{ Money::inr($gold->ratePerGram()) }}/g ({{ $gold->rateEffectiveLabel() }})</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <aside class="h-fit min-w-0 space-y-4">
            {{-- Scheme information --}}
            <section class="rounded-lg border border-line bg-white p-5" aria-labelledby="info-heading">
                <h2 id="info-heading" class="font-sans text-[0.7rem] font-semibold tracking-[0.22em] text-taupe uppercase">Scheme Information</h2>
                <dl class="mt-4 space-y-3 text-sm" data-scheme-details>
                    @foreach ([
                        ['Scheme Number', $scheme->scheme_number, 'font-mono text-xs'],
                        ['Plan', $scheme->plan?->name, ''],
                        ['Status', $statusLabel, ''],
                        ['Successful Payments', $progress['installments_paid'], 'tabular-nums'],
                        ['Remaining', Money::inr($remaining), 'tabular-nums'],
                        ['Enrolled', $scheme->created_at?->format('d M Y'), ''],
                        ['Start Date', $scheme->start_date?->format('d M Y'), ''],
                        ['Expected Maturity', $scheme->maturity_date?->format('d M Y'), ''],
                        ['Last Payment', $progress['last_payment_at']?->format('d M Y'), ''],
                    ] as [$label, $value, $class])
                        <div class="{{ $row }}">
                            <dt class="text-taupe">{{ $label }}</dt>
                            <dd class="text-right font-medium {{ $class }}">{{ filled($value) ? $value : '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
                <p class="mt-4 border-t border-line pt-3 text-xs leading-relaxed text-taupe" data-status-note>{{ $statusNote }}</p>
                <p class="mt-2 text-xs leading-relaxed text-taupe">The monthly amount, duration and total were fixed at enrolment, are not affected by later plan changes, and cannot be edited.</p>
            </section>

            {{-- Customer information --}}
            <section class="rounded-lg border border-line bg-white p-5" aria-labelledby="customer-heading">
                <h2 id="customer-heading" class="font-sans text-[0.7rem] font-semibold tracking-[0.22em] text-taupe uppercase">Customer Information</h2>
                <dl class="mt-4 space-y-3 text-sm" data-scheme-customer>
                    <div><dt class="text-xs text-taupe">Name</dt><dd class="font-semibold">{{ $customer->name }}</dd></div>
                    <div><dt class="text-xs text-taupe">Email</dt><dd class="font-medium break-all">{{ $customer->email }}</dd></div>
                    <div><dt class="text-xs text-taupe">Mobile</dt><dd class="font-medium">{{ $mobile ?? '—' }}</dd></div>
                    <div class="{{ $row }}"><dt class="text-taupe">Account</dt><dd>@include('admin.customers._status', ['status' => $customer->status])</dd></div>
                </dl>
                <a href="{{ route('admin.customers.show', $customer) }}" class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-gold-deep hover:text-ad-ink">View customer <x-icon name="arrow-right" class="size-4" /></a>
            </section>

            {{-- Activity --}}
            <section class="rounded-lg border border-line bg-white p-5" aria-labelledby="activity-heading">
                <h2 id="activity-heading" class="font-sans text-[0.7rem] font-semibold tracking-[0.22em] text-taupe uppercase">Activity</h2>
                @if ($activity->isEmpty())
                    <p class="mt-3 text-sm text-taupe">Joined by the customer online{{ $scheme->created_at ? ' on '.$scheme->created_at->format('d M Y') : '' }}.</p>
                @else
                    <ol class="mt-4 space-y-3 border-l border-line pl-4 text-sm" data-scheme-activity>
                        @foreach ($activity as $entry)
                            <li>
                                <p class="font-semibold">{{ $entry->action->label() }}</p>
                                <p class="text-xs text-taupe">{{ $entry->created_at?->format('d M Y, g:i A') }}@if ($entry->actor) · {{ $entry->actor->name }}@endif</p>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </aside>
    </div>
@endsection
