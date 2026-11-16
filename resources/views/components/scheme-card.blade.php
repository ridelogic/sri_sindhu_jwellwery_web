@props(['plan'])

{{--
    Gold saving plan card.
    Expects: ['number', 'name', 'tagline', 'amount_label', 'tenure_label', 'total_amount', 'featured', 'url']
--}}
@php
    $featured = (bool) ($plan['featured'] ?? false);
@endphp

<article {{ $attributes->class([
    'group relative flex h-full flex-col p-8 transition-colors duration-700 sm:p-10 xl:p-12',
    'bg-espresso text-ivory' => $featured,
    'bg-ivory text-obsidian hover:bg-white' => ! $featured,
]) }}>
    <div class="flex items-center justify-between gap-4">
        <p @class(['text-[0.68rem] font-semibold tracking-[0.32em] uppercase', 'text-champagne' => $featured, 'text-gold-deep' => ! $featured])>Plan {{ $plan['number'] }}</p>
        @if ($featured)
            <span class="absolute top-8 right-8 border border-champagne/50 px-3 py-1.5 sm:top-10 sm:right-10 xl:top-12 xl:right-12 text-[0.6rem] font-semibold tracking-[0.24em] text-gold-bright uppercase">Most Chosen</span>
        @endif
    </div>

    <h3 class="mt-8 text-[2.3rem] leading-none whitespace-nowrap sm:text-[2.6rem] lg:text-[2.15rem] xl:text-[2.6rem]">{{ $plan['name'] }}</h3>
    <p @class(['mt-3 text-sm', 'text-stone' => $featured, 'text-taupe' => ! $featured])>{{ $plan['tagline'] }}</p>

    <p class="mt-10 flex flex-wrap items-baseline gap-x-3 gap-y-1">
        <span class="font-serif text-[3.5rem] leading-none font-light sm:text-[3.75rem] lg:text-[3.1rem] xl:text-[3.75rem]">{{ $plan['amount_label'] }}</span>
        @if ($plan['per_period'] ?? 'month')<span @class(['text-[0.68rem] font-semibold tracking-[0.24em] uppercase', 'text-stone' => $featured, 'text-taupe' => ! $featured])>/ {{ ucfirst($plan['per_period'] ?? 'month') }}</span>@endif
    </p>

    <dl @class(['mt-10 divide-y border-y text-sm', 'divide-ivory/10 border-ivory/10' => $featured, 'divide-line border-line' => ! $featured])>
        <div class="flex items-center justify-between py-4">
            <dt @class(['text-stone' => $featured, 'text-taupe' => ! $featured])>Duration</dt>
            <dd class="font-semibold">{{ ucwords($plan['tenure_label']) }}</dd>
        </div>
        <div class="flex items-center justify-between py-4">
            <dt @class(['text-stone' => $featured, 'text-taupe' => ! $featured])>Total you save</dt>
            <dd class="font-semibold">{{ ($plan['total_amount'] !== null ? \App\Support\Money::inr($plan['total_amount']) : 'Open-ended') }}</dd>
        </div>
    </dl>

    {{-- Placeholder: will link to the plan detail page --}}
    <div class="mt-auto pt-10">
        <a href="{{ $plan['url'] }}" @class(['btn w-full', 'btn-gold' => $featured, 'btn-ghost-dark' => ! $featured]) aria-label="Explore plan: {{ $plan['name'] }}">
            Explore Plan
            <x-icon name="arrow-right" class="size-4" />
        </a>
    </div>
</article>
