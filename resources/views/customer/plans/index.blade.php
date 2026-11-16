@extends('layouts.app')

@section('title', 'Join a Scheme | Sri Sindhu Jewellery')
@section('meta_description', 'Choose a gold saving plan to join.')
@section('robots', 'noindex, nofollow')

@use('App\Support\Money')

@section('content')
    <x-customer-shell title="Join a Scheme" emphasis="Scheme" text="You can hold several gold saving schemes at the same time. Choose a plan to see its details.">
        @if (empty($plans))
            <div class="border border-dashed border-line px-6 py-12 text-center" data-no-plans>
                <p class="font-serif text-2xl">No plans are open for enrolment right now.</p>
                <a href="{{ route('contact', ['enquiry' => 'gold-saving']) }}" class="btn btn-dark mt-6">Contact Our Team</a>
            </div>
        @else
            <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($plans as $plan)
                    <article class="flex flex-col border border-line bg-white" data-plan-card="{{ $plan['slug'] }}">
                        <div class="border-b border-line px-6 py-6">
                            <div class="flex items-start justify-between gap-3">
                                <h2 class="text-[2rem] leading-none">{{ $plan['name'] }}</h2>
                                @if ($plan['featured'])
                                    <span class="border border-champagne/60 px-2 py-1 text-[0.58rem] font-semibold tracking-[0.2em] text-gold-deep uppercase">Most Chosen</span>
                                @endif
                            </div>
                            @if ($plan['tagline'])
                                <p class="mt-2 text-sm text-taupe">{{ $plan['tagline'] }}</p>
                            @endif
                            <p class="mt-6 font-serif text-4xl font-light">{{ $plan['amount_label'] }} @if ($plan['per_period'])<span class="font-sans text-xs tracking-[0.2em] text-taupe uppercase">/ {{ $plan['per_period'] }}</span>@endif</p>
                        </div>
                        <dl class="grid grid-cols-2 gap-px bg-line text-sm">
                            <div class="bg-white px-6 py-3"><dt class="text-xs text-taupe">Duration</dt><dd class="mt-0.5 font-semibold">{{ $plan['tenure_label'] }}</dd></div>
                            <div class="bg-white px-6 py-3"><dt class="text-xs text-taupe">Total Commitment</dt><dd class="mt-0.5 font-semibold">{{ ($plan['total_amount'] !== null ? Money::inr($plan['total_amount']) : 'Open-ended') }}</dd></div>
                        </dl>
                        <ul class="space-y-2 border-t border-line px-6 py-5 text-sm">
                            @foreach (array_slice($benefits[$plan['slug']], 0, 3) as $benefit)
                                <li class="flex gap-2"><x-icon name="check" class="mt-0.5 size-4 text-gold-deep" stroke="2" /> {{ $benefit['title'] }}</li>
                            @endforeach
                        </ul>
                        <div class="mt-auto px-6 pb-6">
                            <a href="{{ route('customer.plans.show', $plan['slug']) }}" class="btn btn-gold w-full">
                                Join This Scheme
                                <x-icon name="arrow-right" class="size-4" />
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </x-customer-shell>
@endsection
