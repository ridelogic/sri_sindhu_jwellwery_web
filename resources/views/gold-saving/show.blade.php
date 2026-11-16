@extends('layouts.app')

@section('title', $plan['name'].' Plan | Gold Saving Scheme | Sri Sindhu Jewellery')
@section('meta_description', 'The '.$plan['name'].' gold saving plan: '.$plan['amount_label'].' '.$plan['per_period_phrase'].' for '.$plan['tenure_label'].'. See how the plan works, how payments work and important notes.')

@use('App\Support\Money')

@php
    $rate = $referenceRate['price_per_gram'];
    $monthlyGrams = $plan['monthly_amount'] / $rate;
    $totalGrams = $plan['total_amount'] !== null ? $plan['total_amount'] / $rate : null;
    $figures = [
        ['label' => $plan['contribution_label'], 'value' => $plan['amount_label']],
        ['label' => 'Duration', 'value' => ucwords($plan['tenure_label'])],
        ['label' => $plan['is_fixed_amount'] ? 'Total Contribution' : 'Minimum Total', 'value' => ($plan['total_amount'] !== null ? Money::inr($plan['total_amount']) : 'Open-ended')],
    ];
@endphp

@unless ($plan['is_active'])
    {{-- Closed plans stay reachable (existing links/customers) but are not indexed or offered. --}}
    @section('robots', 'noindex, follow')
@endunless

@section('content')
    <x-page-hero
        :eyebrow="$plan['is_active'] ? 'Plan '.$plan['number'].' · Gold Saving' : 'Gold Saving · Closed Plan'"
        :title="$plan['name'].'.'"
        :text="$plan['summary']"
        image="images/home/hero-gold-necklace.jpg"
        mirror
        :breadcrumbs="[['label' => 'Home', 'href' => route('home')], ['label' => 'Gold Saving', 'href' => route('gold.saving')], ['label' => $plan['name']]]"
    >
        @if ($plan['is_active'])
            <div class="flex flex-col gap-3 sm:flex-row sm:gap-4">
                <a href="{{ route('register', ['plan' => $plan['slug']]) }}" class="btn btn-gold">
                    Join This Plan
                    <x-icon name="arrow-right" class="size-4" />
                </a>
                <a href="{{ route('contact', ['enquiry' => 'gold-saving']) }}" class="btn btn-ghost-light">Ask About This Plan</a>
            </div>
        @else
            <div class="max-w-xl border border-champagne/40 px-5 py-4 text-sm leading-relaxed text-ivory/85" data-plan-closed>
                This plan is no longer open to new customers. Existing members' plans continue unchanged.
            </div>
            <a href="{{ route('gold.saving') }}" class="btn btn-gold mt-6">
                View Available Plans
                <x-icon name="arrow-right" class="size-4" />
            </a>
        @endif
    </x-page-hero>

    {{-- Key figures --}}
    <section class="section bg-ivory" aria-labelledby="figures-heading">
        <div class="container-site">
            <h2 id="figures-heading" class="sr-only">Plan at a glance</h2>
            <dl class="grid gap-px border-y border-line bg-line md:grid-cols-3">
                @foreach ($figures as $figure)
                    <div class="bg-ivory py-10 md:px-10 md:first:pl-0" data-reveal data-reveal-delay="{{ $loop->index * 100 }}">
                        <dt class="text-[0.66rem] font-semibold tracking-[0.28em] text-taupe uppercase">{{ $figure['label'] }}</dt>
                        <dd class="mt-4 font-serif text-[3.25rem] leading-none font-light sm:text-6xl">{{ $figure['value'] }}</dd>
                    </div>
                @endforeach
            </dl>

            {{-- Installment schedule visual --}}
            @if ($plan['installments'] !== null && $plan['installments'] <= 24)
            <div class="mt-14" data-reveal>
                <p class="text-[0.66rem] font-semibold tracking-[0.28em] text-taupe uppercase">Your {{ $plan['installments'] }} {{ strtolower($plan['frequency']) }} installments</p>
                <ol class="mt-6 grid grid-cols-6 gap-2 sm:grid-cols-11" aria-label="{{ $plan['installments'] }} installments of {{ $plan['amount_label'] }}">
                    @for ($month = 1; $month <= $plan['installments']; $month++)
                        <li class="flex aspect-square items-center justify-center border border-obsidian/15 font-serif text-lg text-gold-deep sm:text-xl">{{ str_pad($month, 2, '0', STR_PAD_LEFT) }}</li>
                    @endfor
                </ol>
            </div>
            @endif
        </div>
    </section>

    {{-- Gold accumulation --}}
    <section class="grain section relative isolate overflow-hidden bg-obsidian text-ivory" aria-labelledby="accumulation-heading">
        <div class="container-site grid gap-14 lg:grid-cols-12 lg:gap-10">
            <div class="lg:col-span-5" data-reveal>
                <p class="eyebrow text-champagne">Gold Accumulation</p>
                <h2 id="accumulation-heading" class="display mt-7 text-[2.7rem] sm:text-6xl">How Your Savings <em class="text-gold-bright/95">Grow</em></h2>
                <p class="mt-7 max-w-md leading-relaxed text-ivory/70">Each month your contribution adds to your plan balance. When the plan is complete, your savings are used towards jewellery as per the scheme terms. The exact method of converting savings into gold will be set out in the final scheme terms.</p>
            </div>

            <div class="lg:col-span-6 lg:col-start-7" data-reveal data-reveal-delay="120">
                <div class="border border-ivory/10 bg-espresso p-7 sm:p-10">
                    <p class="text-[0.66rem] font-semibold tracking-[0.28em] text-champagne uppercase">Illustration only</p>
                    <p class="mt-3 text-sm leading-relaxed text-stone">
                        At today's {{ $referenceRate['purity'] }} rate of {{ Money::inr($rate) }}/g{{ $isSampleRate ? ' (sample rate)' : '' }}:
                    </p>
                    <dl class="mt-8 grid gap-px bg-ivory/10 sm:grid-cols-2">
                        <div class="bg-espresso py-5 sm:pr-6">
                            <dt class="text-sm text-stone">One month · {{ Money::inr($plan['monthly_amount']) }}</dt>
                            <dd class="mt-2 font-serif text-4xl">≈ {{ number_format($monthlyGrams, 3) }} g</dd>
                        </div>
                        @if ($totalGrams !== null)
                        <div class="bg-espresso py-5 sm:pl-6">
                            <dt class="text-sm text-stone">Full plan · {{ Money::inr($plan['total_amount']) }}</dt>
                            <dd class="mt-2 font-serif text-4xl text-gold-bright">≈ {{ number_format($totalGrams, 2) }} g</dd>
                        </div>
                        @endif
                    </dl>
                    <p class="mt-6 text-xs leading-relaxed text-stone">This simple division of your contribution by a single day's rate is for understanding only. It is not a quote, and gold rates change daily.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Benefits + payments --}}
    <section class="section bg-ivory" aria-labelledby="benefits-heading">
        <div class="container-site grid gap-20 lg:grid-cols-12 lg:gap-10">
            <div class="lg:col-span-6">
                <p class="eyebrow text-gold-deep" data-reveal>Scheme Benefits</p>
                <h2 id="benefits-heading" class="display mt-7 text-[2.5rem] sm:text-5xl" data-reveal>What the Plan <em class="text-gold-deep">Includes</em></h2>
                <ul class="mt-12 divide-y divide-line border-y border-line">
                    @foreach ($benefits as $benefit)
                        <li class="flex gap-5 py-5" data-reveal>
                            <x-icon :name="$benefit['icon']" class="mt-1 size-5 text-gold-deep" />
                            <div>
                                <h3 class="font-sans text-[0.95rem] font-semibold">{{ $benefit['title'] }}</h3>
                                <p class="mt-1 text-sm leading-relaxed text-taupe">{{ $benefit['text'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="lg:col-span-5 lg:col-start-8">
                <p class="eyebrow text-gold-deep" data-reveal>Payments</p>
                <h2 class="display mt-7 text-[2.5rem] sm:text-5xl" data-reveal>How Payment <em class="text-gold-deep">Works</em></h2>
                <ol class="mt-12 space-y-8">
                    @foreach ($paymentSteps as $step)
                        <li class="flex gap-6" data-reveal data-reveal-delay="{{ $loop->index * 80 }}">
                            <span class="font-serif text-3xl leading-none text-gold-deep">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <p class="pt-1 leading-relaxed text-taupe">{{ $step }}</p>
                        </li>
                    @endforeach
                </ol>

                <div class="mt-14 border-l-2 border-champagne bg-parchment px-6 py-6" data-reveal>
                    <h3 class="font-sans text-[0.66rem] font-semibold tracking-[0.28em] text-gold-deep uppercase">Important Notes</h3>
                    <ul class="mt-4 list-disc space-y-3 pl-4 text-sm leading-relaxed text-taupe marker:text-champagne">
                        @foreach ($notes as $note)
                            <li>{{ $note }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="section border-t border-line bg-ivory" aria-labelledby="faq-heading">
        <div class="container-site grid gap-14 lg:grid-cols-12 lg:gap-10">
            <div class="lg:col-span-4" data-reveal>
                <p class="eyebrow text-gold-deep">Questions</p>
                <h2 id="faq-heading" class="display mt-7 text-[2.5rem] sm:text-5xl">Plan <em class="text-gold-deep">FAQs</em></h2>
                <a href="{{ route('faq') }}" class="link-line mt-8 text-obsidian">
                    View All FAQs
                    <x-icon name="arrow-right" class="size-3.5" />
                </a>
            </div>
            <div class="lg:col-span-7 lg:col-start-6">
                @foreach ($faqs as $category)
                    <x-faq-list :items="$category['items']" />
                @endforeach
            </div>
        </div>
    </section>

    {{-- Other plans --}}
    <section class="section bg-parchment" aria-labelledby="other-heading">
        <div class="container-site">
            <div class="flex flex-col justify-between gap-6 sm:flex-row sm:items-end" data-reveal>
                <div>
                    <p class="eyebrow text-gold-deep">Compare</p>
                    <h2 id="other-heading" class="display mt-7 text-[2.5rem] sm:text-5xl">Other <em class="text-gold-deep">Plans</em></h2>
                </div>
                <a href="{{ route('gold.saving') }}" class="link-line text-obsidian">
                    All Plans
                    <x-icon name="arrow-right" class="size-3.5" />
                </a>
            </div>
            <div class="mt-14 grid gap-px border border-line bg-line md:grid-cols-2">
                @foreach ($otherPlans as $other)
                    <div class="h-full" data-reveal data-reveal-delay="{{ $loop->index * 120 }}">
                        <x-scheme-card :plan="$other" />
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    @if ($plan['is_active'])
        <x-cta-banner
            :title="'Start Your '.$plan['name'].' Plan.'"
            :emphasis="$plan['name'].' Plan.'"
            :text="$plan['amount_label'].' '.$plan['per_period_phrase'].' for '.$plan['tenure_label'].'.'"
            :primary="['label' => 'Join This Plan', 'href' => route('register', ['plan' => $plan['slug']])]"
            :secondary="['label' => 'Talk to Our Team', 'href' => route('contact', ['enquiry' => 'gold-saving'])]"
        />
    @else
        <x-cta-banner
            title="Explore Our Current Plans."
            emphasis="Current Plans."
            text="This plan is closed to new customers, but other gold saving plans are available."
            :primary="['label' => 'View Available Plans', 'href' => route('gold.saving')]"
            :secondary="['label' => 'Talk to Our Team', 'href' => route('contact', ['enquiry' => 'gold-saving'])]"
        />
    @endif
@endsection
