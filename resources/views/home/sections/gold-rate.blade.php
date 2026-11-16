@php
    $updatedAt = $goldRates['updated_at'] ?? null;
@endphp

<section id="gold-rate" class="grain section relative isolate overflow-hidden bg-obsidian text-ivory" aria-labelledby="gold-rate-heading">
    {{-- Faint gold texture --}}
    @if (! empty($goldRates['background']))
        <div class="absolute inset-y-0 right-0 -z-10 w-full opacity-[0.16] lg:w-2/3" aria-hidden="true">
            <img src="{{ asset($goldRates['background']) }}" alt="" class="size-full object-cover" loading="lazy" decoding="async" width="1800" height="1200">
            <div class="absolute inset-0 bg-gradient-to-r from-obsidian via-obsidian/60 to-transparent"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-obsidian via-transparent to-obsidian"></div>
        </div>
    @endif

    <div class="container-site grid gap-16 lg:grid-cols-12 lg:items-end lg:gap-10">
        <div class="lg:col-span-5" data-reveal>
            <p class="eyebrow text-champagne">Live Rates</p>
            <h2 id="gold-rate-heading" class="display mt-6 text-[2.9rem] sm:text-6xl lg:text-7xl">Today's<br><em class="text-gold-bright/95">Gold Rate</em></h2>
            <p class="mt-6 max-w-sm text-[0.95rem] leading-relaxed text-stone">Indicative rates per gram. Final jewellery prices vary with making charges and applicable taxes.</p>

            @if ($updatedAt)
                <p class="mt-8 flex items-center gap-3 text-[0.68rem] font-semibold tracking-[0.28em] text-ivory/80 uppercase">
                    <span class="size-1.5 rounded-full bg-gold-bright"></span>
                    <span>Updated <time datetime="{{ $updatedAt->toIso8601String() }}" data-gold-rate-updated>{{ $updatedAt->format('d M Y, g:i A') }}</time></span>
                </p>
            @endif

            @if (! empty($goldRates['is_sample']))
                <p class="mt-3 max-w-sm text-xs leading-relaxed text-stone" data-sample-notice>Preview rates shown for the website preview. Live daily rates will appear here once rate updates begin.</p>
            @endif

            {{-- Detailed rates and history live on /gold-rate --}}
            <a href="{{ $goldRates['history_url'] }}" class="btn btn-ghost-light mt-10">
                View Gold Rate History
                <x-icon name="arrow-right" class="size-4" />
            </a>
        </div>

        <div class="lg:col-span-7" data-reveal data-reveal-delay="150">
            <x-gold-rate-card :gold-rates="$goldRates" />
        </div>
    </div>
</section>
