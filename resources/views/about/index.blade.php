@extends('layouts.app')

@section('title', 'About Sri Sindhu Jewellery')
@section('meta_description', 'Discover Sri Sindhu Jewellery — fine Indian gold jewellery chosen with care, honest guidance and a simple monthly Gold Saving Scheme.')

@php
    $hero = $about['hero'];
    $intro = $about['introduction'];
    $story = $about['story'];
    $philosophy = $about['philosophy'];
    $saving = $about['saving'];
@endphp

@section('content')
    <x-page-hero
        :eyebrow="$hero['eyebrow']"
        :title="$hero['title']"
        :emphasis="$hero['title_emphasis']"
        :text="$hero['text']"
        :image="$hero['image']"
        :image-position="$hero['image_position']"
        :breadcrumbs="[['label' => 'Home', 'href' => route('home')], ['label' => 'About']]"
    />

    {{-- Introduction --}}
    <section class="section bg-ivory" aria-labelledby="intro-heading">
        <div class="container-site grid gap-12 lg:grid-cols-12 lg:gap-10">
            <div class="lg:col-span-6" data-reveal>
                <p class="eyebrow text-gold-deep">{{ $intro['eyebrow'] }}</p>
                <h2 id="intro-heading" class="display mt-7 text-[2.7rem] sm:text-6xl lg:text-[4.25rem]">
                    <x-display-title :text="$intro['title']" :emphasis="$intro['title_emphasis']" emphasis-class="text-gold-deep" />
                </h2>
            </div>
            <div class="space-y-6 text-lg leading-relaxed text-taupe lg:col-span-5 lg:col-start-8 lg:pt-16" data-reveal data-reveal-delay="150">
                @foreach ($intro['paragraphs'] as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Our Story --}}
    <section class="grain relative isolate overflow-hidden bg-espresso text-ivory" aria-labelledby="story-heading">
        <div class="grid lg:grid-cols-12">
            <div class="group relative overflow-hidden lg:col-span-6" data-reveal="fade">
                <div class="aspect-[4/5] sm:aspect-[4/3] lg:aspect-auto lg:h-full lg:min-h-[44rem]">
                    <img src="{{ asset($story['image']) }}" alt="{{ $story['image_alt'] }}" class="img-zoom size-full object-cover object-top" width="1200" height="1500" loading="lazy" decoding="async">
                </div>
            </div>
            <div class="flex items-center px-5 py-20 sm:px-8 sm:py-24 lg:col-span-6 lg:px-16 xl:px-24">
                <div class="max-w-lg">
                    <p class="eyebrow text-champagne" data-reveal>{{ $story['eyebrow'] }}</p>
                    <h2 id="story-heading" class="display mt-7 text-[2.7rem] sm:text-6xl" data-reveal data-reveal-delay="100">
                        <x-display-title :text="$story['title']" :emphasis="$story['title_emphasis']" />
                    </h2>
                    <p class="mt-7 text-[1.02rem] leading-relaxed text-ivory/70" data-reveal data-reveal-delay="200">{{ $story['text'] }}</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Philosophy --}}
    <section class="section bg-parchment" aria-labelledby="philosophy-heading">
        <div class="container-site">
            <figure class="mx-auto max-w-4xl text-center" data-reveal>
                <p id="philosophy-heading" class="eyebrow justify-center text-gold-deep">{{ $philosophy['eyebrow'] }}</p>
                <blockquote class="mt-10">
                    <p class="font-serif text-[2.1rem] leading-[1.15] italic sm:text-5xl lg:text-[3.6rem]">&ldquo;{{ $philosophy['quote'] }}&rdquo;</p>
                </blockquote>
                <figcaption class="mx-auto mt-10 max-w-xl text-base leading-relaxed text-taupe sm:text-lg">{{ $philosophy['text'] }}</figcaption>
            </figure>
        </div>
    </section>

    {{-- Purity, Trust, Customer Service --}}
    <section class="section bg-ivory" aria-labelledby="values-heading">
        <div class="container-site">
            <div class="max-w-2xl" data-reveal>
                <p class="eyebrow text-gold-deep">What We Stand For</p>
                <h2 id="values-heading" class="display mt-7 text-[2.7rem] sm:text-6xl">Purity. Trust. <em class="text-gold-deep">Service.</em></h2>
            </div>
            <x-brand-pillars :items="$about['values']" class="mt-16 lg:mt-20" />
        </div>
    </section>

    {{-- Gold saving philosophy --}}
    <section class="grain section relative isolate overflow-hidden bg-obsidian text-ivory" aria-labelledby="saving-heading">
        <div class="container-site grid items-center gap-16 lg:grid-cols-12 lg:gap-10">
            <div class="lg:col-span-6" data-reveal>
                <p class="eyebrow text-champagne">{{ $saving['eyebrow'] }}</p>
                <h2 id="saving-heading" class="display mt-7 text-[2.7rem] sm:text-6xl lg:text-[4.25rem]">
                    <x-display-title :text="$saving['title']" :emphasis="$saving['title_emphasis']" />
                </h2>
                <p class="mt-7 max-w-lg text-[1.02rem] leading-relaxed text-ivory/70">{{ $saving['text'] }}</p>

                <ul class="mt-10 max-w-lg divide-y divide-ivory/10 border-y border-ivory/10">
                    @foreach ($saving['points'] as $point)
                        <li class="flex items-center justify-between py-4 text-[0.7rem] font-semibold tracking-[0.22em] uppercase">
                            {{ $point }}
                            <x-icon name="check" class="size-4 text-champagne" stroke="1.8" />
                        </li>
                    @endforeach
                </ul>

                <div class="mt-10 flex flex-col gap-3 sm:flex-row sm:gap-4">
                    <a href="{{ route('gold.saving') }}" class="btn btn-gold">
                        Explore Gold Saving
                        <x-icon name="arrow-right" class="size-4" />
                    </a>
                    <a href="{{ route('gold.rate') }}" class="btn btn-ghost-light">Today's Gold Rate</a>
                </div>
            </div>
            <div class="lg:col-span-5 lg:col-start-8" data-reveal="fade">
                <div class="relative">
                    <img src="{{ asset($saving['image']) }}" alt="{{ $saving['image_alt'] }}" class="aspect-[4/5] w-full object-cover" width="1800" height="1200" loading="lazy" decoding="async">
                    @if ($lowestMonthlyAmount)
                    <div class="absolute -bottom-6 -left-6 hidden border border-champagne/40 bg-obsidian/90 px-7 py-6 backdrop-blur sm:block">
                        <p class="text-[0.62rem] font-semibold tracking-[0.3em] text-stone uppercase">Plans from</p>
                        <p class="mt-2 font-serif text-4xl">{{ \App\Support\Money::inr($lowestMonthlyAmount) }}<span class="font-sans text-xs tracking-[0.2em] text-stone uppercase"> / month</span></p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <x-cta-banner
        title="Visit Us. Begin Your Gold Story."
        emphasis="Begin Your Gold Story."
        text="Speak with our team about a piece you love or a plan that suits you."
        eyebrow="Sri Sindhu Jewellery"
        :primary="['label' => 'Contact Us', 'href' => route('contact')]"
        :secondary="['label' => 'View Gold Saving Plans', 'href' => route('gold.saving')]"
        image="images/home/hero-gold-necklace.jpg"
    />
@endsection
