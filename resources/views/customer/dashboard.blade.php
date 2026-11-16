@extends('layouts.app')

@section('title', 'My Dashboard | Sri Sindhu Jewellery')
@section('meta_description', 'Your Sri Sindhu Jewellery gold saving dashboard.')
@section('robots', 'noindex, nofollow')

@use('App\Support\Money')

@php
    $cards = [
        [
            'key' => 'active-schemes', 'label' => 'Active Schemes', 'value' => (string) $summary['active_schemes'],
            'hint' => $summary['awaiting_first_payment'] > 0 ? $summary['awaiting_first_payment'].' awaiting first payment' : 'Schemes in progress',
        ],
        [
            'key' => 'total-contributed', 'label' => 'Total Contributed', 'value' => Money::inr($summary['total_contributed']),
            'hint' => $summary['has_payments'] ? 'Across all schemes' : 'Awaiting first payment',
        ],
        [
            'key' => 'gold-accumulated', 'label' => 'Total Gold Accumulated', 'value' => $summary['gold_grams'].' g',
            'hint' => $summary['has_payments'] ? 'Across all schemes' : 'Gold is credited as payments are made',
        ],
        [
            'key' => 'current-value', 'label' => 'Current Scheme Value', 'value' => $summary['current_value'] === null ? null : Money::inr($summary['current_value']),
            'hint' => $summary['has_payments'] ? 'Your gold at today\x27s gold rate' : 'Based on payments received',
        ],
    ];
@endphp

@section('content')
    <x-customer-shell
        :title="'Welcome, '.$customer->name"
        :emphasis="$customer->name"
        text="Your gold saving schemes, all in one place."
    >
        {{-- Summary --}}
        <section aria-label="Summary" class="grid gap-px border border-line bg-line sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($cards as $card)
                <article class="bg-white p-6" data-stat="{{ $card['key'] }}">
                    <h2 class="font-sans text-[0.66rem] font-semibold tracking-[0.24em] text-taupe uppercase">{{ $card['label'] }}</h2>
                    @if ($card['value'] !== null)
                        <p class="mt-3 font-serif text-4xl font-semibold" data-stat-value>{{ $card['value'] }}</p>
                    @else
                        <p class="mt-3 font-serif text-2xl font-semibold text-gold-deep" data-stat-value>{{ $summary['has_payments'] ? 'Rate unavailable' : 'Awaiting first payment' }}</p>
                    @endif
                    <p class="mt-1 text-xs text-taupe">{{ $card['hint'] }}</p>
                </article>
            @endforeach
        </section>

        <div class="mt-6 flex flex-col gap-4 border border-line bg-parchment/60 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-sm" data-kyc-card="{{ $summary['kyc']['key'] }}">
                <p>
                    <span class="text-taupe">KYC Status:</span>
                    <strong class="ml-1 font-semibold" data-kyc-status>{{ $summary['kyc_status'] }}</strong>
                </p>
                <p class="mt-1 text-xs text-taupe">{{ $summary['kyc']['message'] }}</p>
                <a href="{{ route('customer.kyc') }}" class="link-line mt-3 text-obsidian" data-kyc-cta>{{ $summary['kyc']['cta'] }} <x-icon name="arrow-right" class="size-3.5" /></a>
            </div>
            <a href="{{ route('customer.plans.index') }}" class="btn btn-dark w-full sm:w-auto">
                <x-icon name="plus" class="size-4" />
                Join Another Scheme
            </a>
        </div>

        {{-- Latest notifications --}}
        <section class="mt-14" aria-labelledby="notifications-heading" data-latest-notifications>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <h2 id="notifications-heading" class="display text-[2.2rem] sm:text-5xl">Latest <em class="text-gold-deep">Notifications</em></h2>
                <a href="{{ route('customer.notifications.index') }}" class="link-line text-obsidian">View All Notifications <x-icon name="arrow-right" class="size-3.5" /></a>
            </div>
            @if ($latestNotifications->isEmpty())
                <p class="mt-6 border border-dashed border-line px-6 py-8 text-center text-sm text-taupe">No notifications yet. Gold rate updates, payment results and KYC decisions will appear here.</p>
            @else
                <ul class="mt-6 divide-y divide-line border border-line bg-ivory">
                    @foreach ($latestNotifications as $notification)
                        <li><x-notification-item :notification="$notification" compact /></li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Schemes --}}
        <section class="mt-14" aria-labelledby="schemes-heading">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <h2 id="schemes-heading" class="display text-[2.2rem] sm:text-5xl">My <em class="text-gold-deep">Schemes</em></h2>
                @if ($schemes->isNotEmpty())
                    <a href="{{ route('customer.schemes.index') }}" class="link-line text-obsidian">All Schemes <x-icon name="arrow-right" class="size-3.5" /></a>
                @endif
            </div>

            @if ($schemes->isEmpty())
                <div class="mt-8 border border-dashed border-line px-6 py-12 text-center" data-empty-schemes>
                    <p class="font-serif text-2xl">You have not joined a scheme yet.</p>
                    <p class="mt-2 text-sm text-taupe">Choose a gold saving plan to begin.</p>
                    <a href="{{ route('customer.plans.index') }}" class="btn btn-gold mt-6">Browse Plans <x-icon name="arrow-right" class="size-4" /></a>
                </div>
            @else
                <div class="mt-8 grid gap-6 lg:grid-cols-2">
                    @foreach ($schemes as $scheme)
                        <x-customer-scheme-card :scheme="$scheme" :progress="$progress[$scheme->id]" />
                    @endforeach
                </div>
            @endif
        </section>
    </x-customer-shell>
@endsection
