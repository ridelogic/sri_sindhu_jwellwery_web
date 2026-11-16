@props(['goldRates'])

{{-- Compact live-rate strip shown along the bottom of the hero. --}}
@php
    $updatedAt = $goldRates['updated_at'] ?? null;
@endphp

<div {{ $attributes->merge(['class' => 'border-t border-ivory/10 bg-obsidian/55 backdrop-blur-md']) }} data-gold-rate-strip>
    <div class="container-site flex flex-wrap items-center gap-x-8 gap-y-3 py-4 text-[0.68rem] font-semibold tracking-[0.2em] uppercase sm:py-5 lg:gap-x-12">
        <p class="hidden text-stone lg:block">Today's Gold Rate</p>

        <dl class="flex flex-wrap items-center gap-x-6 gap-y-2 sm:gap-x-10">
            @foreach ($goldRates['rates'] as $rate)
                <div class="flex items-baseline gap-3" data-gold-rate="{{ $rate['purity'] }}">
                    <dt class="text-stone">{{ $rate['label'] }}</dt>
                    <dd class="font-sans text-[0.95rem] tracking-[0.04em] text-ivory normal-case">
                        <span data-gold-rate-value>{{ \App\Support\Money::inr($rate['price_per_gram']) }}</span><span class="text-stone">/g</span>
                    </dd>
                </div>
            @endforeach
        </dl>

        @if ($updatedAt)
            <p class="flex items-center gap-2.5 text-stone">
                <span class="relative flex size-1.5">
                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-gold-bright/70 motion-reduce:animate-none"></span>
                    <span class="relative inline-flex size-1.5 rounded-full bg-gold-bright"></span>
                </span>
                <time datetime="{{ $updatedAt->toDateString() }}">{{ $updatedAt->isToday() ? 'Updated Today' : 'Updated '.$updatedAt->format('d M Y') }}</time>
            </p>
        @endif

        <a href="{{ route('gold.rate') }}" class="ml-auto hidden items-center gap-2 text-ivory transition-colors hover:text-gold-bright md:inline-flex">
            View Gold Rate
            <x-icon name="arrow-right" class="size-3.5" />
        </a>
    </div>
</div>
