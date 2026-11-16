@extends('layouts.app')

@section('title', 'Frequently Asked Questions | Sri Sindhu Jewellery')
@section('meta_description', 'Answers to common questions about the Sri Sindhu Jewellery Gold Saving Scheme, payments, gold rates, jewellery and your account.')

@section('content')
    <x-page-hero
        eyebrow="FAQ"
        title="Questions, Answered."
        emphasis="Answered."
        text="Everything you need to know about our Gold Saving Scheme, payments, gold rates and more."
        image="images/home/cta-gold-earrings.jpg"
        image-position="72% 55%"
        :breadcrumbs="[['label' => 'Home', 'href' => route('home')], ['label' => 'FAQ']]"
    />

    <section class="section bg-ivory" aria-label="Frequently asked questions">
        <div class="container-site grid gap-12 lg:grid-cols-12 lg:gap-10">
            {{-- Category navigation --}}
            <nav class="min-w-0 lg:col-span-3" aria-label="FAQ categories">
                <div class="lg:sticky lg:top-28">
                    <p class="hidden text-[0.66rem] font-semibold tracking-[0.28em] text-taupe uppercase lg:block">Categories</p>
                    <ul class="-mx-5 flex gap-2 overflow-x-auto px-5 pb-2 sm:-mx-8 sm:px-8 lg:mx-0 lg:mt-6 lg:flex-col lg:gap-0 lg:overflow-visible lg:border-t lg:border-line lg:px-0 lg:pb-0">
                        @foreach ($categories as $key => $category)
                            <li class="shrink-0 lg:border-b lg:border-line">
                                <a href="#{{ $key }}" class="flex items-center justify-between gap-4 border border-obsidian/15 px-4 py-2.5 text-[0.68rem] font-semibold tracking-[0.2em] whitespace-nowrap uppercase transition-colors hover:border-obsidian hover:bg-obsidian hover:text-ivory lg:border-0 lg:px-0 lg:py-4 lg:hover:bg-transparent lg:hover:text-gold-deep">
                                    {{ $category['title'] }}
                                    <span class="hidden text-taupe lg:inline">{{ count($category['items']) }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </nav>

            <div class="min-w-0 space-y-20 lg:col-span-8 lg:col-start-5">
                @foreach ($categories as $key => $category)
                    <section id="{{ $key }}" class="scroll-mt-28" aria-labelledby="{{ $key }}-heading">
                        <div class="mb-6 flex items-baseline justify-between gap-4" data-reveal>
                            <h2 id="{{ $key }}-heading" class="display text-[2.4rem] sm:text-5xl">{{ $category['title'] }}</h2>
                            <span class="font-serif text-xl text-gold-deep italic">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        </div>
                        <x-faq-list :items="$category['items']" :open-first="$loop->first" />
                    </section>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Still have questions --}}
    <section class="border-t border-line bg-parchment py-20 sm:py-24" aria-labelledby="more-heading">
        <div class="container-site flex flex-col items-start justify-between gap-8 md:flex-row md:items-center">
            <div data-reveal>
                <h2 id="more-heading" class="display text-[2.4rem] sm:text-5xl">Still Have <em class="text-gold-deep">Questions?</em></h2>
                <p class="mt-4 max-w-md leading-relaxed text-taupe">Our team is happy to help with anything not covered here.</p>
            </div>
            <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:gap-4" data-reveal>
                <a href="{{ route('contact') }}" class="btn btn-dark">
                    Contact Us
                    <x-icon name="arrow-right" class="size-4" />
                </a>
                <a href="{{ route('gold.saving') }}" class="btn btn-ghost-dark">View Plans</a>
            </div>
        </div>
    </section>
@endsection
