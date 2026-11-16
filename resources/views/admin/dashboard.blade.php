@extends('layouts.admin')

@section('title', 'Dashboard')
@section('header_title', 'Dashboard')
@section('wide', '1')

@use('App\Support\Money')

@php
    $rate22 = $rates['22K'] ?? null;
    $rate24 = $rates['24K'] ?? null;
    $formatRate = fn ($rate) => $rate ? Money::inr((float) $rate->rate_per_gram) : null;

    $activeCustomers = $stats['customers'] - $stats['blockedCustomers'] - $stats['inactiveCustomers'];
    $isDevelopmentRate = collect([$rate22, $rate24])->filter()->contains(fn ($rate) => $rate->isDevelopmentData());

    // Every value comes from DashboardController (database only). Every arrow leads to an existing page.
    $cards = [
        // Row 1
        ['label' => 'Total Customers', 'value' => number_format($stats['customers']), 'icon' => 'user', 'key' => 'customers', 'tone' => 'blue',
            'hint' => number_format($activeCustomers).' active · '.($stats['inactiveCustomers'] > 0 ? number_format($stats['inactiveCustomers']).' inactive · ' : '').number_format($stats['blockedCustomers']).' blocked',
            'href' => route('admin.customers.index'), 'link' => 'View Customers'],
        ['label' => 'Active Gold Saving Schemes', 'value' => number_format($stats['activeSchemes']), 'icon' => 'layers', 'key' => 'active-schemes', 'tone' => 'green',
            'hint' => 'Enrolments currently active', 'href' => route('admin.schemes.index', ['status' => 'active']), 'link' => 'View Active Schemes'],
        ['label' => 'Pending KYC', 'value' => number_format($stats['pendingKyc']), 'icon' => 'shield', 'key' => 'pending-kyc', 'tone' => 'rose',
            'hint' => 'Submitted and awaiting review', 'href' => route('admin.kyc.index', ['status' => 'pending_review']), 'link' => 'Manage KYC'],
        // Row 2
        ["label" => "Today's Successful Payments", 'value' => number_format($stats['todayPayments']), 'icon' => 'receipt', 'key' => 'today-payments', 'tone' => 'mint',
            'hint' => 'Verified with the gateway today', 'href' => route('admin.payments.index', ['status' => 'success']), 'link' => 'View Payments'],
        ["label" => "Today's Payment Amount", 'value' => Money::inr(Money::fromPaise($stats['todayAmountPaise'])), 'icon' => 'wallet', 'key' => 'today-amount', 'tone' => 'gold',
            'hint' => 'Verified successful payments', 'href' => route('admin.payments.index', ['status' => 'success']), 'link' => 'View Successful Payments'],
        ['label' => 'Total Successful Payments', 'value' => number_format($stats['totalPayments']), 'icon' => 'chart', 'key' => 'total-payments', 'tone' => 'lavender',
            'hint' => 'All time', 'href' => route('admin.payments.index', ['status' => 'success']), 'link' => 'View All Successful Payments'],
        // Row 3
        ["label" => "Today's 22K Rate", 'value' => $formatRate($rate22), 'icon' => 'trend', 'key' => 'rate-22k', 'tone' => 'ivory',
            'hint' => 'Per gram', 'href' => route('admin.gold-rates.index'), 'link' => 'Manage Gold Rates'],
        ["label" => "Today's 24K Rate", 'value' => $formatRate($rate24), 'icon' => 'trend', 'key' => 'rate-24k', 'tone' => 'gold',
            'hint' => 'Per gram', 'href' => route('admin.gold-rates.history'), 'link' => 'Rate History'],
        ['label' => 'Active Plans', 'value' => number_format($stats['activePlans']), 'icon' => 'gem', 'key' => 'active-plans', 'tone' => 'lavender',
            'hint' => 'Plans offered to customers', 'href' => route('admin.plans.index'), 'link' => 'Manage Plans'],
    ];

    $green = '#3DBB6E'; $amber = '#F6B73C'; $red = '#E5484D'; $blue = '#3B82F6'; $purple = '#9B5DE5';
    $panels = [
        [
            'key' => 'customers', 'title' => 'Customers', 'icon' => 'user', 'href' => route('admin.customers.index'), 'link' => 'View Customers',
            'centerValue' => null, 'centerLabel' => 'Total',
            'segments' => [
                ['key' => 'active', 'label' => 'Active', 'value' => $breakdowns['customers']['active'], 'color' => $green, 'href' => route('admin.customers.index', ['status' => 'active'])],
                ['key' => 'inactive', 'label' => 'Inactive', 'value' => $breakdowns['customers']['inactive'], 'color' => $amber, 'href' => route('admin.customers.index', ['status' => 'inactive'])],
                ['key' => 'blocked', 'label' => 'Blocked', 'value' => $breakdowns['customers']['blocked'], 'color' => $red, 'href' => route('admin.customers.index', ['status' => 'blocked'])],
            ],
        ],
        [
            'key' => 'schemes', 'title' => 'Schemes', 'icon' => 'coins', 'href' => route('admin.plans.index'), 'link' => 'Manage Plans',
            'centerValue' => $breakdowns['schemes']['active'], 'centerLabel' => 'Active',
            'segments' => [
                ['key' => 'active', 'label' => 'Active', 'value' => $breakdowns['schemes']['active'], 'color' => $green, 'href' => route('admin.schemes.index', ['status' => 'active'])],
                ['key' => 'completed', 'label' => 'Completed', 'value' => $breakdowns['schemes']['completed'], 'color' => $blue, 'href' => route('admin.schemes.index', ['status' => 'completed'])],
                ['key' => 'matured', 'label' => 'Matured', 'value' => $breakdowns['schemes']['matured'], 'color' => $purple, 'href' => route('admin.schemes.index', ['status' => 'matured'])],
            ],
        ],
        [
            'key' => 'payments', 'title' => 'Payments', 'icon' => 'card', 'href' => route('admin.payments.index'), 'link' => 'View Payments',
            'centerValue' => $stats['todayPayments'], 'centerLabel' => 'Today', // today's successful payments
            'segments' => [
                ['key' => 'success', 'label' => 'Successful', 'value' => $breakdowns['payments']['success'], 'color' => $green, 'href' => route('admin.payments.index', ['status' => 'success'])],
                ['key' => 'pending', 'label' => 'Pending', 'value' => $breakdowns['payments']['pending'], 'color' => $amber, 'href' => route('admin.payments.index', ['status' => 'pending'])],
                ['key' => 'failed', 'label' => 'Failed', 'value' => $breakdowns['payments']['failed'], 'color' => $red, 'href' => route('admin.payments.index', ['status' => 'failed'])],
            ],
        ],
    ];

    $schemeTones = ['active' => 'success', 'pending' => 'warning', 'completed' => 'info', 'matured' => 'gold', 'cancelled' => 'neutral'];
@endphp

@section('page-header')
    {{-- Welcome banner: text and quick actions left; today's date top-right; gold earrings image filling the right side --}}
    <section class="admin-welcome mb-5" aria-labelledby="welcome-heading" data-welcome-banner>
        <img src="{{ asset('images/home/cta-gold-earrings.jpg') }}" alt="" class="admin-welcome-media" width="2200" height="1467" loading="lazy" decoding="async">

        <div class="admin-welcome-body">
            <div class="min-w-0">
                <p class="admin-welcome-eyebrow">Sri Sindhu Jewellery · Admin</p>
                <h1 id="welcome-heading" class="admin-welcome-title">Welcome Back, Administrator</h1>
                <p class="mt-1.5 text-sm text-[#5b4223]">Here's what's happening with your jewellery savings today.</p>

                <p class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-[#5b4223]" data-rates-updated>
                    <x-icon name="clock" class="size-3.5 text-[#8a5a00]" />
                    @if ($ratesUpdatedAt)
                        <span>Gold rates last updated <time class="font-semibold text-ad-ink" datetime="{{ $ratesUpdatedAt->toIso8601String() }}">{{ $ratesUpdatedAt->format('d M Y, g:i A') }}</time></span>
                    @else
                        <span class="font-semibold text-ad-ink">No rates recorded yet</span>
                    @endif
                    @if ($isDevelopmentRate)
                        <span class="rounded-full border border-[#d9b65c] bg-white/70 px-2 py-0.5 text-[0.6rem] font-bold tracking-[0.08em] text-[#8a5a00] uppercase" title="These rows were created by the development seeder">Development sample</span>
                    @endif
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('admin.customers.create') }}" class="btn btn-secondary"><x-icon name="user-plus" class="size-4" /> Add Customer</a>
                    <a href="{{ route('admin.gold-rates.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Update Today's Rate</a>
                </div>
            </div>

            {{-- Today's date. The dashboard always reports today (there is no date filtering), so this is a label, not a control. --}}
            <p class="admin-welcome-date" data-dashboard-date>
                <x-icon name="calendar" class="size-4 text-[#8a5a00]" />
                <span class="font-semibold text-[#4a2d08]">Today</span>
                <span class="h-4 w-px bg-[#ead7ae]" aria-hidden="true"></span>
                <time class="text-ad-ink" datetime="{{ now()->toDateString() }}">{{ now()->format('D, d M Y') }}</time>
            </p>
        </div>
    </section>
@endsection

@section('content')
    {{-- Metric cards: 3 columns on desktop, 2 on tablet, 1 on narrow phones --}}
    <section aria-label="Summary" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($cards as $card)
            <x-admin.kpi-card variant="pastel" :label="$card['label']" :value="$card['value']" :key="$card['key']" :icon="$card['icon']" :tone="$card['tone']"
                :hint="$card['hint'] ?? null" :href="$card['href'] ?? null" :link="$card['link'] ?? null" />
        @endforeach
    </section>

    {{-- Summary panels with donut charts (real counts) --}}
    <section aria-label="Status breakdown" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($panels as $panel)
            <article class="admin-panel admin-summary-panel" aria-labelledby="panel-{{ $panel['key'] }}" data-summary-panel="{{ $panel['key'] }}">
                <div class="mb-5 flex items-center justify-between gap-3">
                    <h2 id="panel-{{ $panel['key'] }}" class="admin-summary-title">
                        <x-icon :name="$panel['icon']" class="size-6 text-[#4a2d08]" />
                        {{ $panel['title'] }}
                    </h2>
                    <a href="{{ $panel['href'] }}" class="admin-summary-link">{{ $panel['link'] }} <x-icon name="arrow-right" class="size-3.5" /></a>
                </div>
                <x-admin.donut :segments="$panel['segments']" :center-value="$panel['centerValue']" :center-label="$panel['centerLabel']" :label="$panel['title'].' by status'" />
            </article>
        @endforeach
    </section>

    {{-- Recent customers and scheme enrolments --}}
    <div class="mt-5 grid gap-4 xl:grid-cols-5">
        <section class="admin-panel overflow-hidden xl:col-span-2" aria-labelledby="customers-heading">
            <div class="flex items-center justify-between gap-3 px-5 py-4">
                <h2 id="customers-heading" class="admin-panel-title">Recent Customers</h2>
                <a href="{{ route('admin.customers.index') }}" class="admin-stat-link">View all <span class="admin-stat-arrow admin-stat-arrow-sm" aria-hidden="true"><x-icon name="arrow-right" class="size-3.5" /></span></a>
            </div>
            @if ($recentCustomers->isEmpty())
                <x-admin.empty-state icon="user" title="No customers yet." />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm" data-table="recent-customers">
                        <thead class="text-[0.68rem] tracking-[0.1em] uppercase">
                            <tr>
                                <th scope="col" class="px-5 py-2.5 font-semibold">Name</th>
                                <th scope="col" class="px-5 py-2.5 text-right font-semibold">Joined</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($recentCustomers as $customer)
                                <tr>
                                    <td class="px-5 py-3">
                                        <span class="flex items-center gap-3">
                                            <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-ad-dark text-xs font-semibold text-white" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($customer->name, 0, 1)) }}</span>
                                            <span class="min-w-0 font-medium">{{ $customer->name }}<span class="mt-0.5 block text-xs font-normal break-all text-taupe">{{ $customer->email }}</span></span>
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-right whitespace-nowrap text-taupe">{{ $customer->created_at?->format('d M Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="admin-panel overflow-hidden xl:col-span-3" aria-labelledby="enrolments-heading">
            <div class="flex items-center justify-between gap-3 px-5 py-4">
                <h2 id="enrolments-heading" class="admin-panel-title">Recent Scheme Enrolments</h2>
                <a href="{{ route('admin.schemes.index') }}" class="admin-stat-link">View all <span class="admin-stat-arrow admin-stat-arrow-sm" aria-hidden="true"><x-icon name="arrow-right" class="size-3.5" /></span></a>
            </div>
            @if ($recentEnrolments->isEmpty())
                <x-admin.empty-state icon="layers" title="No scheme enrolments yet." />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[36rem] text-left text-sm" data-table="recent-enrolments">
                        <thead class="text-[0.68rem] tracking-[0.1em] uppercase">
                            <tr>
                                <th scope="col" class="px-5 py-2.5 font-semibold">Scheme Number</th>
                                <th scope="col" class="px-5 py-2.5 font-semibold">Customer</th>
                                <th scope="col" class="px-5 py-2.5 font-semibold">Plan</th>
                                <th scope="col" class="px-5 py-2.5 font-semibold">Status</th>
                                <th scope="col" class="px-5 py-2.5 text-right font-semibold">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($recentEnrolments as $enrolment)
                                <tr>
                                    <td class="px-5 py-3 font-mono text-[0.8rem] whitespace-nowrap">{{ $enrolment->scheme_number }}</td>
                                    <td class="px-5 py-3">{{ $enrolment->user?->name }}</td>
                                    <td class="px-5 py-3">{{ $enrolment->plan?->name }}</td>
                                    <td class="px-5 py-3"><x-admin.badge :tone="$schemeTones[$enrolment->status->value] ?? 'neutral'">{{ $enrolment->status->label() }}</x-admin.badge></td>
                                    <td class="px-5 py-3 text-right whitespace-nowrap text-taupe">{{ $enrolment->created_at?->format('d M Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
