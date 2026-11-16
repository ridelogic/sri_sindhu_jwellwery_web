@extends('layouts.app')

@section('title', 'Gold Rate Today | Sri Sindhu Jewellery')
@section('meta_description', "Today's 22K and 24K gold rate per gram and per 10 grams at Sri Sindhu Jewellery, with recent rate history and what moves the price of gold.")

@use('App\Support\Money')

@php
    $chartSeries = collect($rates)->mapWithKeys(fn ($rate) => [
        $rate['purity'] => collect($history)->reverse()->values()->map(fn ($row) => [
            'date' => $row['date'],
            'value' => $row['rates'][$rate['purity']],
        ])->all(),
    ]);

    $factors = [
        ['icon' => 'trend', 'title' => 'International Prices', 'text' => 'Indian gold rates closely follow global gold prices, which move with world markets every day.'],
        ['icon' => 'wallet', 'title' => 'Currency Movement', 'text' => 'Gold is traded internationally in US dollars, so the rupee exchange rate affects the local price.'],
        ['icon' => 'calendar', 'title' => 'Seasonal Demand', 'text' => 'Weddings and festivals increase demand for gold, which can influence local rates.'],
        ['icon' => 'receipt', 'title' => 'Duties & Taxes', 'text' => 'Import duties and applicable taxes form part of the final price of gold in India.'],
    ];
@endphp

@section('content')
    <x-page-hero
        eyebrow="Gold Rate"
        title="Gold Rate Today"
        emphasis="Today"
        text="Today's indicative 22K and 24K gold rates at Sri Sindhu Jewellery — per gram and per 10 grams."
        image="images/home/rate-gold-bars.jpg"
        :breadcrumbs="[['label' => 'Home', 'href' => route('home')], ['label' => 'Gold Rate']]"
    >
        <p class="flex flex-wrap items-center gap-x-6 gap-y-2 text-[0.68rem] font-semibold tracking-[0.24em] text-ivory/80 uppercase">
            <span class="flex items-center gap-2.5">
                <span class="size-1.5 rounded-full bg-gold-bright" aria-hidden="true"></span>
                Last updated <time datetime="{{ $updatedAt->toIso8601String() }}">{{ $updatedAt->format('d M Y, g:i A') }}</time>
            </span>
        </p>
    </x-page-hero>

    {{-- Rate dashboard --}}
    <section class="grain relative isolate bg-obsidian pb-24 text-ivory sm:pb-28" aria-labelledby="today-heading">
        <div class="container-site">
            <h2 id="today-heading" class="sr-only">Today's rates</h2>

            @if ($isSample)
                <p class="mb-8 flex items-start gap-3 border border-champagne/30 px-5 py-4 text-sm leading-relaxed text-ivory/80" data-sample-notice>
                    <x-icon name="sparkle" class="mt-0.5 size-4 text-champagne" />
                    <span><strong class="font-semibold text-ivory">Preview rates.</strong> The figures on this page are sample values for the website preview. Live daily rates will be published here once rate updates begin.</span>
                </p>
            @endif

            <div class="grid gap-px bg-ivory/10 md:grid-cols-2">
                @foreach ($rates as $rate)
                    <article class="bg-espresso p-7 sm:p-10 xl:p-12" data-reveal data-reveal-delay="{{ $loop->index * 120 }}" data-gold-rate="{{ $rate['purity'] }}">
                        <header class="flex items-start justify-between gap-6">
                            <div>
                                <p class="font-serif text-5xl leading-none text-champagne">{{ $rate['purity'] }}</p>
                                <h3 class="mt-3 font-sans text-[0.7rem] font-semibold tracking-[0.26em] text-ivory uppercase">{{ $rate['label'] }}</h3>
                                <p class="mt-2 text-sm text-stone">{{ $rate['description'] }}</p>
                            </div>
                            @php $up = $rate['change'] >= 0; @endphp
                            <p class="inline-flex shrink-0 items-center gap-1.5 border border-ivory/15 px-3 py-1.5 text-xs font-semibold text-ivory tabular-nums">
                                <x-icon name="arrow-up-right" @class(['size-3.5', 'text-gold-bright' => $up, 'rotate-90 text-stone' => ! $up]) />
                                <span class="sr-only">{{ $up ? 'Up' : 'Down' }}</span>
                                {{ $up ? '+' : '−' }}{{ Money::inr(abs($rate['change'])) }}
                                <span class="text-stone">({{ $up ? '+' : '−' }}{{ number_format(abs($rate['change_percent']), 2) }}%)</span>
                            </p>
                        </header>

                        <dl class="mt-10">
                            <div>
                                <dt class="text-[0.66rem] font-semibold tracking-[0.3em] text-stone uppercase">Today's Rate · Per Gram</dt>
                                <dd class="mt-3 font-serif text-[3.75rem] leading-none font-light sm:text-7xl xl:text-[5.5rem]" data-gold-rate-value>{{ Money::inr($rate['price_per_gram']) }}</dd>
                            </div>

                            <div class="mt-10 grid grid-cols-2 gap-px border-y border-ivory/10 bg-ivory/10">
                                <div class="bg-espresso py-5 pr-4">
                                    <dt class="text-[0.62rem] font-semibold tracking-[0.24em] text-stone uppercase">Per 10 Gram</dt>
                                    <dd class="mt-2 text-xl font-semibold tabular-nums sm:text-2xl">{{ Money::inr($rate['price_per_10_gram']) }}</dd>
                                </div>
                                <div class="bg-espresso py-5 pl-5">
                                    <dt class="text-[0.62rem] font-semibold tracking-[0.24em] text-stone uppercase">Previous Rate</dt>
                                    <dd class="mt-2 text-xl font-semibold text-ivory/80 tabular-nums sm:text-2xl">{{ Money::inr($rate['previous_price_per_gram']) }}<span class="text-sm font-normal text-stone">/g</span></dd>
                                </div>
                            </div>

                            <div class="mt-5 flex items-center justify-between text-sm">
                                <dt class="text-stone">Last updated</dt>
                                <dd class="text-ivory/80">{{ $updatedAt->format('d M Y, g:i A') }}</dd>
                            </div>
                        </dl>
                    </article>
                @endforeach
            </div>

            <p class="mt-6 text-xs leading-relaxed text-stone">Rates are indicative and per gram of gold. Final jewellery prices include making charges and applicable taxes.</p>
        </div>
    </section>

    {{-- History --}}
    <section id="history" class="grain section relative isolate bg-espresso text-ivory" aria-labelledby="history-heading">
        <div class="container-site">
            <div class="flex flex-col justify-between gap-6 lg:flex-row lg:items-end">
                <div data-reveal>
                    <p class="eyebrow text-champagne">Rate History</p>
                    <h2 id="history-heading" class="display mt-7 text-[2.7rem] sm:text-6xl">Gold Rate <em class="text-gold-bright/95">History</em></h2>
                </div>
                @if ($isSample)
                    <p class="max-w-sm text-sm leading-relaxed text-stone" data-reveal>Sample history shown for the website preview. Recorded daily rates will replace it once rate updates begin.</p>
                @endif
            </div>

            <div class="mt-14 grid gap-6 lg:grid-cols-2">
                @foreach ($rates as $rate)
                    <x-gold-rate-chart :title="$rate['label']" :points="$chartSeries[$rate['purity']]" :id="'chart-'.strtolower($rate['purity'])" data-reveal />
                @endforeach
            </div>

            {{-- Table view of the same data --}}
            <div class="mt-14 overflow-x-auto" data-reveal>
                <table class="w-full text-left text-sm sm:min-w-[34rem]">
                    <caption class="sr-only">Daily gold rate per gram, newest first</caption>
                    <thead>
                        <tr class="border-b border-ivory/15 text-[0.64rem] font-semibold tracking-[0.24em] text-stone uppercase">
                            <th scope="col" class="py-4 pr-4 font-semibold">Date</th>
                            @foreach ($rates as $rate)
                                <th scope="col" class="py-4 pr-4 text-right font-semibold">{{ $rate['purity'] }} / gram</th>
                                <th scope="col" class="hidden py-4 pr-4 text-right font-semibold sm:table-cell">{{ $rate['purity'] }} change</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ivory/10 tabular-nums">
                        @foreach ($history as $index => $row)
                            <tr>
                                <th scope="row" class="py-4 pr-4 font-normal text-ivory/80">{{ $row['date']->format('d M Y') }}</th>
                                @foreach ($rates as $rate)
                                    @php
                                        $previous = $history[$index + 1]['rates'][$rate['purity']] ?? null;
                                        $delta = $previous === null ? null : $row['rates'][$rate['purity']] - $previous;
                                    @endphp
                                    <td class="py-4 pr-4 text-right text-ivory">{{ Money::inr($row['rates'][$rate['purity']]) }}</td>
                                    <td class="hidden py-4 pr-4 text-right text-stone sm:table-cell">
                                        @if ($delta === null)
                                            —
                                        @else
                                            {{ $delta > 0 ? '+' : ($delta < 0 ? '−' : '') }}{{ Money::inr(abs($delta)) }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- Understanding gold rates --}}
    <section class="section bg-ivory" aria-labelledby="explain-heading">
        <div class="container-site">
            <div class="grid gap-12 lg:grid-cols-12 lg:gap-10">
                <div class="lg:col-span-5" data-reveal>
                    <p class="eyebrow text-gold-deep">Understanding Gold Rates</p>
                    <h2 id="explain-heading" class="display mt-7 text-[2.7rem] sm:text-6xl">22K or <em class="text-gold-deep">24K?</em></h2>
                </div>
                <div class="grid gap-10 sm:grid-cols-2 lg:col-span-7" data-reveal data-reveal-delay="120">
                    <div class="border-t border-obsidian/15 pt-6">
                        <p class="font-serif text-4xl text-gold-deep">22K</p>
                        <p class="mt-4 leading-relaxed text-taupe">About 91.6% pure gold, blended with other metals for the strength and durability needed in jewellery. Most gold jewellery is made in 22K.</p>
                    </div>
                    <div class="border-t border-obsidian/15 pt-6">
                        <p class="font-serif text-4xl text-gold-deep">24K</p>
                        <p class="mt-4 leading-relaxed text-taupe">99.9% pure gold. It is softer and is mainly used for gold coins and bars rather than intricate jewellery.</p>
                    </div>
                </div>
            </div>

            <div class="mt-24 lg:mt-28" data-reveal>
                <p class="eyebrow text-gold-deep">Why Gold Rates Change</p>
                <h2 class="display mt-7 max-w-2xl text-[2.4rem] sm:text-5xl">What Moves the Price of Gold</h2>
            </div>
            <ul class="mt-14 grid gap-px border border-line bg-line sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($factors as $factor)
                    <li class="bg-ivory p-7 sm:p-9" data-reveal data-reveal-delay="{{ $loop->index * 100 }}">
                        <x-icon :name="$factor['icon']" class="size-6 text-gold-deep" />
                        <h3 class="mt-6 text-2xl">{{ $factor['title'] }}</h3>
                        <p class="mt-3 text-[0.95rem] leading-relaxed text-taupe">{{ $factor['text'] }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    <x-cta-banner
        title="Save Steadily, Whatever the Rate."
        emphasis="Whatever the Rate."
        :text="$lowestMonthlyAmount ? 'Build towards your jewellery with monthly plans from '.Money::inr($lowestMonthlyAmount).' a month.' : 'Build towards your jewellery with a simple monthly gold saving plan.'"
        :primary="['label' => 'View Gold Saving Plans', 'href' => route('gold.saving')]"
        :secondary="['label' => 'Ask Our Team', 'href' => route('contact', ['enquiry' => 'gold-rate'])]"
    />
@endsection
