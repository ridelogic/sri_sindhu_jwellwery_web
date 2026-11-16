@extends('layouts.app')

@section('title', 'Contact Sri Sindhu Jewellery')
@section('meta_description', 'Contact Sri Sindhu Jewellery about the Gold Saving Scheme, gold rates, bridal or everyday jewellery, or to plan a store visit.')

@use('App\Support\SiteContact')

@php
    $hero = $contact['hero'];
    $whatsapp = SiteContact::whatsappHref('Hello Sri Sindhu Jewellery, I would like to know more.');
    $details = [
        ['icon' => 'phone', 'label' => 'Phone', 'value' => SiteContact::phone(), 'href' => SiteContact::phoneHref()],
        ['icon' => 'mail', 'label' => 'Email', 'value' => SiteContact::email(), 'href' => SiteContact::emailHref()],
        ['icon' => 'map-pin', 'label' => 'Store Address', 'value' => SiteContact::address(), 'href' => null],
        ['icon' => 'clock', 'label' => 'Store Hours', 'value' => SiteContact::hours(), 'href' => null],
    ];
    $hasAnyDetail = collect($details)->contains(fn ($d) => filled($d['value'])) || $whatsapp;
@endphp

@section('content')
    <x-page-hero
        :eyebrow="$hero['eyebrow']"
        :title="$hero['title']"
        :emphasis="$hero['title_emphasis']"
        :text="$hero['text']"
        :image="$hero['image']"
        :image-position="$hero['image_position']"
        :breadcrumbs="[['label' => 'Home', 'href' => route('home')], ['label' => 'Contact']]"
    />

    <section class="section bg-ivory" aria-label="Contact details and enquiry form">
        <div class="container-site grid gap-16 lg:grid-cols-12 lg:gap-10">
            {{-- Details --}}
            <div class="lg:col-span-5">
                <p class="eyebrow text-gold-deep" data-reveal>Get in Touch</p>
                <h2 class="display mt-7 text-[2.7rem] sm:text-6xl" data-reveal>Visit, Call or <em class="text-gold-deep">Write</em></h2>

                @unless ($hasAnyDetail)
                    <p class="mt-6 max-w-md text-sm leading-relaxed text-taupe" data-reveal data-contact-placeholder>Our store contact details are being finalised and will be published here soon.</p>
                @endunless

                <dl class="mt-12 divide-y divide-line border-y border-line" data-reveal>
                    @foreach ($details as $detail)
                        <div class="flex items-start gap-5 py-6">
                            <x-icon :name="$detail['icon']" class="mt-1 size-5 text-gold-deep" />
                            <div>
                                <dt class="text-[0.66rem] font-semibold tracking-[0.28em] text-taupe uppercase">{{ $detail['label'] }}</dt>
                                <dd class="mt-2 text-lg">
                                    @if ($detail['value'] && $detail['href'])
                                        <a href="{{ $detail['href'] }}" class="break-all transition-colors hover:text-gold-deep">{{ $detail['value'] }}</a>
                                    @elseif ($detail['value'])
                                        <span class="whitespace-pre-line">{{ $detail['value'] }}</span>
                                    @else
                                        <span class="text-obsidian/40">{{ SiteContact::PLACEHOLDER }}</span>
                                    @endif
                                </dd>
                            </div>
                        </div>
                    @endforeach
                </dl>

                <div class="mt-10" data-reveal>
                    @if ($whatsapp)
                        <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="btn btn-dark w-full sm:w-auto">
                            <x-icon name="whatsapp" class="size-4" />
                            Chat on WhatsApp
                        </a>
                    @else
                        <span class="btn w-full cursor-not-allowed border border-obsidian/20 text-obsidian/50 sm:w-auto" aria-disabled="true" data-whatsapp-unavailable>
                            <x-icon name="whatsapp" class="size-4" />
                            WhatsApp — Coming Soon
                        </span>
                    @endif
                </div>
            </div>

            {{-- Enquiry form (UI only in this stage) --}}
            <div class="lg:col-span-6 lg:col-start-7" data-reveal data-reveal-delay="120">
                <div class="border border-line bg-white p-6 sm:p-10">
                    <h2 class="font-serif text-3xl">Send an Enquiry</h2>
                    <p class="mt-2 text-sm text-taupe">Fields marked <span class="text-champagne">*</span> are required.</p>

                    <x-notice class="mt-6" />

                    <form method="POST" action="{{ route('contact.store') }}" class="mt-8 grid gap-6 sm:grid-cols-2" novalidate>
                        @csrf
                        <x-form.input name="name" label="Full Name" autocomplete="name" required class="sm:col-span-2" />
                        <x-form.input name="phone" label="Phone" type="tel" autocomplete="tel" inputmode="tel" required />
                        <x-form.input name="email" label="Email" type="email" autocomplete="email" />
                        <x-form.select name="enquiry" label="Enquiry About" :options="$contact['enquiry_types']" :selected="$selectedEnquiry" required class="sm:col-span-2" />
                        <x-form.textarea name="message" label="Message" rows="5" required class="sm:col-span-2" placeholder="How can we help?" />

                        <div class="sm:col-span-2">
                            <button type="submit" class="btn btn-dark w-full sm:w-auto">
                                Send Enquiry
                                <x-icon name="arrow-right" class="size-4" />
                            </button>
                            <p class="mt-4 text-xs leading-relaxed text-taupe">Online enquiries are being set up and are not delivered yet. For now, please reach our team directly.</p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    {{-- Map --}}
    <section class="bg-ivory pb-24 sm:pb-28" aria-labelledby="map-heading">
        <div class="container-site">
            <h2 id="map-heading" class="sr-only">Store location</h2>
            @if ($mapUrl = SiteContact::mapEmbedUrl())
                <iframe src="{{ $mapUrl }}" title="Map showing the Sri Sindhu Jewellery store" class="aspect-[16/9] w-full border-0 grayscale-[30%] sm:aspect-[21/9]" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
            @else
                <div class="grain relative isolate flex aspect-[4/3] items-center justify-center overflow-hidden bg-espresso text-center text-ivory sm:aspect-[21/9]" data-map-placeholder>
                    <div class="absolute inset-0 -z-10 opacity-[0.12]" aria-hidden="true"
                         style="background-image: linear-gradient(rgb(245 240 231 / 0.5) 1px, transparent 1px), linear-gradient(90deg, rgb(245 240 231 / 0.5) 1px, transparent 1px); background-size: 48px 48px;"></div>
                    <div class="px-6">
                        <x-icon name="map-pin" class="mx-auto size-8 text-champagne" />
                        <p class="mt-5 font-serif text-3xl">Store Location</p>
                        <p class="mt-2 text-sm text-stone">The map will appear here once the store address is confirmed.</p>
                    </div>
                </div>
            @endif
        </div>
    </section>
@endsection
