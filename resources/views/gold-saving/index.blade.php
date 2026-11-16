@extends('layouts.app')

@section('title', 'Gold Saving Scheme | Sri Sindhu Jewellery')
@section('meta_description', 'Explore the Sri Sindhu Jewellery Gold Saving Scheme — simple monthly plans that help you save steadily towards the gold jewellery you love.')

@use('App\Support\Money')

@php
    $durations = collect($plans)->pluck('duration_months')->filter()->unique();
    $allMonthly = collect($plans)->every(fn ($p) => ! empty($p['duration_months']));
    $facts = [
        ['value' => $lowestMonthlyAmount ? Money::inr($lowestMonthlyAmount) : '—', 'label' => 'Plans from / month'],
        ['value' => ! $allMonthly ? 'Flexible' : ($durations->count() === 1 ? $durations->first().' Months' : $durations->min().'–'.$durations->max().' Months'), 'label' => 'Plan duration'],
        ['value' => count($plans), 'label' => 'Saving plans'],
    ];
@endphp

@section('content')
    <x-page-hero
        eyebrow="Gold Saving"
        title="Turn Every Contribution Into Gold."
        emphasis="Into Gold."
        text="A simple monthly saving plan that helps you build steadily towards the jewellery you have always wanted."
        image="images/home/hero-gold-necklace.jpg"
        mirror
        :breadcrumbs="[['label' => 'Home', 'href' => route('home')], ['label' => 'Gold Saving']]"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:gap-4">
            <a href="#plans" class="btn btn-gold">
                View Plans
                <x-icon name="arrow-right" class="size-4" />
            </a>
            <a href="{{ route('contact', ['enquiry' => 'gold-saving']) }}" class="btn btn-ghost-light">Talk to Our Team</a>
        </div>
    </x-page-hero>

    {{-- Introduction --}}
    <section class="section bg-ivory" aria-labelledby="intro-heading">
        <div class="container-site grid gap-14 lg:grid-cols-12 lg:gap-10">
            <div class="lg:col-span-6" data-reveal>
                <p class="eyebrow text-gold-deep">The Scheme</p>
                <h2 id="intro-heading" class="display mt-7 text-[2.7rem] sm:text-6xl">Small Steps, <em class="text-gold-deep">Beautiful Gold</em></h2>
            </div>
            <div class="lg:col-span-5 lg:col-start-8 lg:pt-14" data-reveal data-reveal-delay="120">
                <p class="text-lg leading-relaxed text-taupe">Instead of saving up for a large jewellery purchase all at once, the Sri Sindhu Gold Saving Scheme lets you contribute a fixed amount every month. When your plan is complete, you use your savings towards jewellery at our store, as per the scheme terms.</p>
            </div>
        </div>

        <div class="container-site mt-16 lg:mt-20">
        <dl class="grid gap-px border-y border-line bg-line sm:grid-cols-3">
            @foreach ($facts as $fact)
                <div class="bg-ivory py-8 sm:px-8 sm:first:pl-0" data-reveal data-reveal-delay="{{ $loop->index * 100 }}">
                    <dt class="text-[0.66rem] font-semibold tracking-[0.28em] text-taupe uppercase">{{ $fact['label'] }}</dt>
                    <dd class="mt-3 font-serif text-5xl font-light">{{ $fact['value'] }}</dd>
                </div>
            @endforeach
        </dl>
        </div>
    </section>

    {{-- How it works --}}
    <section class="grain section relative isolate overflow-hidden bg-obsidian text-ivory" aria-labelledby="how-heading">
        <div class="container-site">
            <div data-reveal>
                <p class="eyebrow text-champagne">{{ $savingSteps['eyebrow'] }}</p>
                <h2 id="how-heading" class="display mt-7 text-[2.7rem] sm:text-6xl lg:text-[4.5rem]">
                    <x-display-title :text="$savingSteps['title']" :emphasis="$savingSteps['title_emphasis']" />
                </h2>
            </div>
            <x-saving-timeline :steps="$savingSteps['items']" class="mt-20 lg:mt-24" />
        </div>
    </section>

    {{-- Plans --}}
    <section id="plans" class="section bg-ivory" aria-labelledby="plans-heading">
        <div class="container-site">
            <div class="flex flex-col justify-between gap-6 lg:flex-row lg:items-end">
                <div data-reveal>
                    <p class="eyebrow text-gold-deep">Available Plans</p>
                    <h2 id="plans-heading" class="display mt-7 text-[2.7rem] sm:text-6xl">Choose Your <em class="text-gold-deep">Plan</em></h2>
                </div>
                <p class="max-w-md leading-relaxed text-taupe" data-reveal>Every plan follows the same simple structure — choose the monthly amount that suits you best.</p>
            </div>

            <div class="mt-16 grid gap-px border border-line bg-line lg:grid-cols-3">
                @foreach ($plans as $plan)
                    <div class="h-full" data-reveal data-reveal-delay="{{ $loop->index * 120 }}">
                        <x-scheme-card :plan="$plan" />
                    </div>
                @endforeach
            </div>
            <p class="mt-6 text-sm text-taupe">{{ $notes[0] }}</p>
        </div>
    </section>

    {{-- Benefits --}}
    <section class="section bg-parchment" aria-labelledby="benefits-heading">
        <div class="container-site">
            <div class="max-w-2xl" data-reveal>
                <p class="eyebrow text-gold-deep">Benefits</p>
                <h2 id="benefits-heading" class="display mt-7 text-[2.7rem] sm:text-6xl">Saving Made <em class="text-gold-deep">Simple</em></h2>
            </div>
            <ul class="mt-16 grid gap-x-10 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($benefits as $benefit)
                    <li class="border-t border-obsidian/15 pt-7" data-reveal data-reveal-delay="{{ ($loop->index % 3) * 100 }}">
                        <x-icon :name="$benefit['icon']" class="size-6 text-gold-deep" />
                        <h3 class="mt-5 text-[1.7rem] leading-tight">{{ $benefit['title'] }}</h3>
                        <p class="mt-3 leading-relaxed text-taupe">{{ $benefit['text'] }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Why Sri Sindhu --}}
    <section class="section bg-ivory" aria-labelledby="why-heading">
        <div class="container-site">
            <div class="mx-auto max-w-3xl text-center" data-reveal>
                <p class="eyebrow justify-center text-gold-deep">{{ $pillars['eyebrow'] }}</p>
                <h2 id="why-heading" class="display mt-7 text-[2.7rem] sm:text-6xl">
                    <x-display-title :text="$pillars['title']" :emphasis="$pillars['title_emphasis']" emphasis-class="text-gold-deep" />
                </h2>
            </div>
            <x-brand-pillars :items="$pillars['items']" class="mt-16 lg:mt-20" />
        </div>
    </section>

    {{-- FAQ + notes --}}
    <section class="section border-t border-line bg-ivory" aria-labelledby="faq-heading">
        <div class="container-site grid gap-14 lg:grid-cols-12 lg:gap-10">
            <div class="lg:col-span-4">
                <p class="eyebrow text-gold-deep" data-reveal>Questions</p>
                <h2 id="faq-heading" class="display mt-7 text-[2.7rem] sm:text-6xl" data-reveal>Good to <em class="text-gold-deep">Know</em></h2>

                <div class="mt-10 border-l-2 border-champagne bg-parchment px-6 py-6" data-reveal>
                    <p class="text-[0.66rem] font-semibold tracking-[0.28em] text-gold-deep uppercase">Important</p>
                    <ul class="mt-4 space-y-3 text-sm leading-relaxed text-taupe">
                        @foreach (array_slice($notes, 1) as $note)
                            <li>{{ $note }}</li>
                        @endforeach
                    </ul>
                </div>

                <a href="{{ route('faq') }}" class="link-line mt-10 text-obsidian" data-reveal>
                    View All FAQs
                    <x-icon name="arrow-right" class="size-3.5" />
                </a>
            </div>

            <div class="space-y-14 lg:col-span-7 lg:col-start-6">
                @foreach ($faqs as $category)
                    <div>
                        <h3 class="mb-4 font-sans text-[0.68rem] font-semibold tracking-[0.3em] text-taupe uppercase">{{ $category['title'] }}</h3>
                        <x-faq-list :items="$category['items']" :open-first="$loop->first" />
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <x-cta-banner
        title="Begin Your Gold Saving Journey."
        emphasis="Gold Saving Journey."
        :text="$lowestMonthlyAmount ? 'Plans start from '.Money::inr($lowestMonthlyAmount).' a month.' : 'Speak to our team about upcoming gold saving plans.'"
        :primary="['label' => 'Join Gold Saving Scheme', 'href' => route('register')]"
        :secondary="['label' => 'Talk to Our Team', 'href' => route('contact', ['enquiry' => 'gold-saving'])]"
    />
@endsection
