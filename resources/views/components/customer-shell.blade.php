@props(['title', 'emphasis' => null, 'eyebrow' => 'Member Portal', 'text' => null])

{{-- Customer portal frame: dark welcome band + section tabs, then ivory content. --}}
@php
    $tabs = [
        ['label' => 'Dashboard', 'route' => 'customer.dashboard', 'active' => 'customer.dashboard'],
        ['label' => 'My Schemes', 'route' => 'customer.schemes.index', 'active' => 'customer.schemes.*'],
        ['label' => 'KYC', 'route' => 'customer.kyc', 'active' => 'customer.kyc*'],
        ['label' => 'Payments', 'route' => 'customer.payments.index', 'active' => 'customer.payments.*'],
        ['label' => 'Join a Scheme', 'route' => 'customer.plans.index', 'active' => 'customer.plans.*'],
        ['label' => 'Profile', 'route' => 'customer.profile', 'active' => 'customer.profile'],
    ];
@endphp

<section class="grain relative isolate overflow-hidden bg-obsidian text-ivory" aria-labelledby="portal-heading">
    <div class="pointer-events-none absolute inset-0 -z-10 opacity-[0.18]" aria-hidden="true">
        <img src="{{ asset('images/home/hero-gold-necklace.jpg') }}" alt="" class="size-full -scale-x-100 object-cover object-right" decoding="async">
        <div class="absolute inset-0 bg-gradient-to-r from-obsidian via-obsidian/90 to-obsidian/40"></div>
    </div>

    <div class="container-site pt-28 sm:pt-32 lg:pt-36">
        <p class="eyebrow text-champagne">{{ $eyebrow }}</p>
        <h1 id="portal-heading" class="display mt-5 text-[2.6rem] sm:text-5xl lg:text-6xl">
            <x-display-title :text="$title" :emphasis="$emphasis" emphasis-class="text-gold-bright/95" />
        </h1>
        @if ($text)
            <p class="mt-4 max-w-xl text-[0.98rem] leading-relaxed text-ivory/70">{{ $text }}</p>
        @endif

        <nav class="mt-10 -mb-px border-b border-ivory/10" aria-label="Member portal">
            <ul class="-mx-5 flex gap-1 overflow-x-auto px-5 sm:mx-0 sm:px-0">
                @foreach ($tabs as $tab)
                    @php $current = request()->routeIs($tab['active']); @endphp
                    <li class="shrink-0">
                        <a href="{{ route($tab['route']) }}" @if ($current) aria-current="page" @endif
                           @class([
                               'inline-flex border-b-2 px-3 py-3.5 text-[0.7rem] font-semibold tracking-[0.18em] whitespace-nowrap uppercase transition-colors sm:px-4',
                               'border-champagne text-ivory' => $current,
                               'border-transparent text-ivory/60 hover:text-ivory' => ! $current,
                           ])>{{ $tab['label'] }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </div>
</section>

<div class="bg-ivory">
    <div class="container-site py-10 sm:py-14">
        @if (session('status'))
            <div class="mb-8 flex items-start gap-3 border-l-2 border-champagne bg-parchment px-5 py-4 text-sm leading-relaxed" role="status" data-flash-status>
                <x-icon name="check" class="mt-0.5 size-4 text-gold-deep" stroke="2" />
                <p>{{ session('status') }}</p>
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-8 border-l-2 border-red-400 bg-red-50 px-5 py-4 text-sm text-red-800" role="alert">{{ $errors->first() }}</div>
        @endif

        {{ $slot }}
    </div>
</div>
