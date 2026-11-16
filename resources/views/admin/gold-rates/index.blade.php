@extends('layouts.admin')

@section('title', 'Gold Rate Management')
@section('subtitle', 'Today\'s published rates and the latest updates.')

@use('App\Support\Money')

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="max-w-xl text-sm text-taupe">The latest active rates below are shown on the website homepage. Every update is saved as a new record — previous rates are kept in the history.</p>
        <a href="{{ route('admin.gold-rates.create') }}" class="btn btn-dark w-full shrink-0 sm:w-auto">
            <x-icon name="plus" class="size-4" />
            Update Today's Rate
        </a>
    </div>

    {{-- Current rates --}}
    <section class="mt-6 grid gap-4 md:grid-cols-2" aria-label="Current gold rates">
        @foreach ($current as $purity => $rate)
            <article class="rounded-lg border border-line bg-white p-6" data-current-rate="{{ $purity }}">
                <header class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-serif text-4xl leading-none text-gold-deep">{{ $purity }}</p>
                        <h2 class="mt-2 font-sans text-[0.7rem] font-semibold tracking-[0.22em] text-taupe uppercase">{{ $purity }} Gold</h2>
                    </div>
                    @if ($rate)
                        <div class="flex flex-col items-end gap-1.5">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $rate->status ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : 'bg-stone-100 text-stone-700 ring-stone-300' }}" data-rate-status>{{ $rate->status ? 'Active' : 'Inactive' }}</span>
                            @if ($rate->isDevelopmentData())
                                <span class="rounded-sm bg-amber-50 px-2 py-0.5 text-[0.6rem] font-semibold tracking-[0.1em] text-amber-800 uppercase ring-1 ring-amber-200">Development sample</span>
                            @endif
                        </div>
                    @endif
                </header>

                @if ($rate)
                    <dl class="mt-6 grid grid-cols-2 gap-px overflow-hidden rounded-md bg-line">
                        <div class="bg-white py-3 pr-3">
                            <dt class="text-xs text-taupe">Per gram</dt>
                            <dd class="mt-1 text-[1.75rem] leading-tight font-semibold tabular-nums sm:text-3xl" data-rate-per-gram>{{ Money::inr($rate->rate_per_gram) }}</dd>
                        </div>
                        <div class="bg-white py-3 pl-4">
                            <dt class="text-xs text-taupe">Per 10 gram</dt>
                            <dd class="mt-1 text-[1.75rem] leading-tight font-semibold tabular-nums sm:text-3xl" data-rate-per-10g>{{ Money::inr($rate->rate_per_10_gram) }}</dd>
                        </div>
                    </dl>
                    <dl class="mt-5 space-y-2 border-t border-line pt-4 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-taupe">Last updated</dt>
                            <dd class="text-right font-medium" data-rate-updated>{{ $rate->effectiveAtMoment()->format('d M Y, h:i A') }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-taupe">Source</dt>
                            <dd class="text-right">{{ $rate->source ?? '—' }}</dd>
                        </div>
                    </dl>
                @else
                    <p class="mt-6 rounded-md bg-parchment px-4 py-5 text-sm text-taupe">No {{ $purity }} rate has been recorded yet. Use “Update Today's Rate” to add one.</p>
                @endif
            </article>
        @endforeach
    </section>

    {{-- Recent updates --}}
    <section class="mt-8 rounded-lg border border-line bg-white" aria-labelledby="recent-heading">
        <div class="flex items-center justify-between gap-4 border-b border-line px-5 py-4">
            <h2 id="recent-heading" class="font-sans text-[0.7rem] font-semibold tracking-[0.22em] text-taupe uppercase">Recent Updates</h2>
            <a href="{{ route('admin.gold-rates.history') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-gold-deep hover:text-ad-ink">
                Full history
                <x-icon name="arrow-right" class="size-4" />
            </a>
        </div>
        @include('admin.gold-rates.partials.updates-table', ['updates' => $recentUpdates])
    </section>
@endsection
