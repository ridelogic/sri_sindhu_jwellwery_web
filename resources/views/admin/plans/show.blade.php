@extends('layouts.admin')

@section('title', $plan->name)
@section('subtitle', 'Plan details and enrolments.')
@section('back', route('admin.plans.index'))
@section('back_label', 'All Plans')

@use('App\Support\Money')

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-3">
            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $plan->status ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : 'bg-stone-100 text-stone-700 ring-stone-300' }}" data-plan-status>{{ $plan->status ? 'Active' : 'Inactive' }}</span>
            <span class="font-mono text-sm text-taupe">/gold-saving/{{ $plan->slug }}</span>
            @if ($plan->status)
                <a href="{{ route('gold.saving.show', $plan->slug) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-sm font-semibold text-gold-deep hover:text-ad-ink">
                    View on website <x-icon name="arrow-up-right" class="size-3.5" />
                </a>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.plans.edit', $plan) }}" class="btn btn-dark min-h-11 flex-1 px-5 sm:flex-none">Edit Plan</a>
            @include('admin.plans._status-actions', ['plan' => $plan, 'class' => 'min-h-11 px-5 text-sm'])
        </div>
    </div>

    @if ($plan->enrolments_count > 0)
        <div class="mt-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" data-enrolment-notice>
            This plan has existing customer enrolments. Financial values for existing enrolments are preserved.
        </div>
    @endif

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="space-y-6">
            <section class="rounded-lg border border-line bg-white p-5 sm:p-7" aria-labelledby="overview-heading">
                <h2 id="overview-heading" class="font-serif text-2xl font-semibold">{{ $plan->name }}</h2>
                @if ($plan->tagline)
                    <p class="mt-1 text-sm text-gold-deep">{{ $plan->tagline }}</p>
                @endif
                @if (filled($plan->description))
                    <p class="mt-4 leading-relaxed text-taupe">{{ $plan->description }}</p>
                @endif

                <dl class="mt-6 grid gap-px overflow-hidden rounded-md bg-line sm:grid-cols-3">
                    <div class="bg-parchment/60 p-4">
                        <dt class="text-xs text-taupe">{{ $plan->contributionLabel() }}</dt>
                        <dd class="mt-1 text-2xl font-semibold tabular-nums" data-plan-monthly>{{ $plan->amountRangeLabel() }}</dd>
                    </div>
                    <div class="bg-parchment/60 p-4">
                        <dt class="text-xs text-taupe">Tenure</dt>
                        <dd class="mt-1 text-2xl font-semibold" data-plan-tenure>{{ $plan->tenureLabel() }}</dd>
                    </div>
                    <div class="bg-parchment/60 p-4">
                        <dt class="text-xs text-taupe">{{ $plan->isFixedAmount() ? 'Total Contribution' : 'Minimum Total' }}</dt>
                        <dd class="mt-1 text-2xl font-semibold tabular-nums" data-plan-total>{{ $plan->total_amount !== null ? Money::inr($plan->total_amount) : 'Open-ended' }}</dd>
                    </div>
                </dl>

                <dl class="mt-4 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2" data-plan-configuration>
                    @foreach ([
                        ['Plan Name', $plan->name],
                        ['Scheme Type', $plan->schemeType()->label()],
                        ['Contribution Frequency', $plan->contributionFrequency()->label()],
                        ['Plan Tenure Unit', $plan->tenureUnit()->label()],
                        ['Maximum Tenure Allowed', $plan->hasUnlimitedTenure() ? 'Not applicable (no maturity date)' : $plan->tenureLabel()],
                        ['Minimum Amount', Money::inr($plan->minimumAmount())],
                        ['Maximum Amount', $plan->maximumAmountLabel()],
                        ['Output', $plan->outputType()->label().($plan->outputType()->creditsGold() ? ' — gold credited on each payment' : ' — no gold credited on payment')],
                        ['Bonus Applicable', $plan->bonus_applicable ? 'Yes' : 'No'],
                        ['Bonus Type', $plan->bonus_type?->label() ?? '—'],
                        ['Bonus Value', $plan->bonus_type && $plan->bonus_value !== null ? $plan->bonus_type->format((string) $plan->bonus_value) : '—'],
                        ['Early Exit Allowed', $plan->early_exit_allowed ? 'Yes' : 'No'],
                        ['Plan Status', $plan->status ? 'Active' : 'Inactive'],
                    ] as [$label, $value])
                        <div class="flex justify-between gap-4 border-b border-line pb-2">
                            <dt class="text-taupe">{{ $label }}</dt>
                            <dd class="text-right font-medium">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        </div>

        <aside class="h-fit space-y-4">
            <section class="rounded-lg bg-obsidian p-5 text-ivory" aria-labelledby="enrolled-heading">
                <h2 id="enrolled-heading" class="font-sans text-[0.7rem] font-semibold tracking-[0.22em] text-champagne uppercase">Customers Enrolled</h2>
                <p class="mt-3 text-4xl font-semibold tabular-nums" data-plan-customers>{{ $customersCount }}</p>
                <p class="mt-1 text-xs text-stone">{{ $plan->enrolments_count }} {{ \Illuminate\Support\Str::plural('enrolment', $plan->enrolments_count) }} in total</p>
            </section>

            <dl class="space-y-3 rounded-lg border border-line bg-white p-5 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-taupe">Status</dt><dd class="font-medium">{{ $plan->status ? 'Active' : 'Inactive' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-taupe">Featured</dt><dd class="font-medium">{{ $plan->is_featured ? 'Yes' : 'No' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-taupe">Sort order</dt><dd class="font-medium">{{ $plan->sort_order }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-taupe">Created</dt><dd class="text-right font-medium">{{ $plan->created_at?->format('d M Y, h:i A') }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-taupe">Updated</dt><dd class="text-right font-medium">{{ $plan->updated_at?->format('d M Y, h:i A') }}</dd></div>
            </dl>
        </aside>
    </div>
@endsection
