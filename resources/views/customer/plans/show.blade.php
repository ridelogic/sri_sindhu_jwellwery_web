@extends('layouts.app')

@section('title', 'Join '.$plan['name'].' | Sri Sindhu Jewellery')
@section('meta_description', 'Confirm your gold saving plan enrolment.')
@section('robots', 'noindex, nofollow')

@use('App\Support\Money')

@section('content')
    <x-customer-shell :title="$plan['name']" eyebrow="Join a Scheme" :text="$plan['summary']">
        <a href="{{ route('customer.plans.index') }}" class="link-line text-obsidian">
            <x-icon name="arrow-right" class="size-3.5 rotate-180" />
            All Plans
        </a>

        <div class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]">
            <div class="space-y-6">
                <section class="border border-line bg-white" aria-labelledby="summary-heading">
                    <h2 id="summary-heading" class="border-b border-line px-6 py-5 text-2xl">Plan Summary</h2>
                    <dl class="grid gap-px bg-line sm:grid-cols-3">
                        <div class="bg-white px-6 py-5"><dt class="text-xs text-taupe">{{ $plan['contribution_label'] }}</dt><dd class="mt-1 font-serif text-3xl" data-plan-monthly>{{ $plan['amount_label'] }}</dd></div>
                        <div class="bg-white px-6 py-5"><dt class="text-xs text-taupe">Duration</dt><dd class="mt-1 font-serif text-3xl">{{ $plan['tenure_label'] }}</dd></div>
                        <div class="bg-white px-6 py-5"><dt class="text-xs text-taupe">Total Commitment</dt><dd class="mt-1 font-serif text-3xl" data-plan-total>{{ ($plan['total_amount'] !== null ? Money::inr($plan['total_amount']) : 'Open-ended') }}</dd></div>
                    </dl>
                </section>

                <section class="border border-line bg-white px-6 py-6" aria-labelledby="benefits-heading">
                    <h2 id="benefits-heading" class="text-2xl">Benefits</h2>
                    <ul class="mt-4 divide-y divide-line">
                        @foreach ($benefits as $benefit)
                            <li class="flex gap-3 py-3">
                                <x-icon :name="$benefit['icon'] ?? 'check'" class="mt-0.5 size-5 text-gold-deep" />
                                <div>
                                    <p class="font-semibold">{{ $benefit['title'] }}</p>
                                    @if (! empty($benefit['text']))<p class="text-sm text-taupe">{{ $benefit['text'] }}</p>@endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            </div>

            <aside class="h-fit bg-obsidian p-6 text-ivory" aria-labelledby="confirm-heading">
                <h2 id="confirm-heading" class="font-sans text-[0.7rem] font-semibold tracking-[0.22em] text-champagne uppercase">Confirm Enrolment</h2>
                <p class="mt-4 text-sm leading-relaxed text-ivory/75">
                    You are joining <strong class="text-ivory">{{ $plan['name'] }}</strong>:
                    {{ $plan['amount_label'] }} {{ $plan['per_period_phrase'] }} for {{ $plan['tenure_label'] }}
                    @if ($plan['total_amount'] !== null)({{ Money::inr($plan['total_amount']) }} {{ $plan['is_fixed_amount'] ? 'in total' : 'minimum in total' }}).@else(open-ended).@endif
                </p>
                @if ($existingCount > 0)
                    <p class="mt-4 border border-champagne/40 px-4 py-3 text-xs leading-relaxed text-ivory/80" data-existing-enrolment>
                        You already have {{ $existingCount }} {{ \Illuminate\Support\Str::plural('enrolment', $existingCount) }} in this plan. Joining again creates a separate scheme.
                    </p>
                @endif

                {{-- Only the plan (in the URL) and a one-time token are sent. All amounts come from our records. --}}
                <form method="POST" action="{{ route('customer.plans.join', $plan['slug']) }}" class="mt-6" data-join-form>
                    @csrf
                    <input type="hidden" name="enrolment_token" value="{{ $enrolmentToken }}">
                    <button type="submit" class="btn btn-gold w-full" data-submit-once>
                        Join This Scheme
                        <x-icon name="arrow-right" class="size-4" />
                    </button>
                </form>

                <ul class="mt-6 space-y-2 border-t border-ivory/10 pt-4 text-xs leading-relaxed text-stone">
                    @foreach ($notes as $note)
                        <li>{{ $note }}</li>
                    @endforeach
                </ul>
            </aside>
        </div>
    </x-customer-shell>
@endsection
