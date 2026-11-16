@extends('layouts.admin')

@section('title', "Update Today's Rate")
@section('form_page', '1')

@use('App\Support\Money')

@section('content')
    <form method="POST" action="{{ route('admin.gold-rates.store') }}" data-rate-form novalidate>
        @csrf

        <x-admin.form-card title="Update Today's Gold Rate" :back="route('admin.gold-rates.index')" back-label="Back to Gold Rates">
            @if ($errors->any())
                <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-800" role="alert">
                    Please correct the highlighted fields. No rates have been saved.
                </div>
            @endif

            {{-- Current rates for reference --}}
            <dl class="mb-5 grid gap-2 rounded-md border border-ad-line bg-ad-panel p-3 text-sm sm:grid-cols-2" aria-label="Current rates">
                @foreach ($current as $purity => $rate)
                    <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                        <dt class="font-semibold text-ad-muted">Current {{ $purity }}</dt>
                        <dd>
                            @if ($rate)
                                <span class="font-bold text-ad-ink tabular-nums">{{ Money::inr($rate->rate_per_gram) }}</span><span class="text-xs text-ad-muted"> / g · {{ $rate->effectiveAtMoment()->format('d M Y, h:i A') }}</span>
                            @else
                                <span class="text-ad-muted">Not recorded</span>
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>

            <x-admin.form-grid>
                <x-form.input name="rate_date" label="Rate Date" type="date" :value="$defaults['rate_date']" max="{{ today()->toDateString() }}" required />
                <x-form.input name="rate_time" label="Rate Time" type="time" :value="$defaults['rate_time']" step="60" required />

                @foreach (['22K' => 'rate_22k', '24K' => 'rate_24k'] as $purity => $field)
                    <div>
                        <x-form.input :name="$field" :label="$purity.' Gold Rate / Gram (₹)'" type="number" step="0.01" min="0.01" inputmode="decimal" placeholder="e.g. {{ $purity === '22K' ? '7000.00' : '7650.00' }}" data-rate-input="{{ $purity }}" required />
                        <p class="mt-1.5 flex items-baseline justify-between gap-3 text-xs">
                            <span class="text-ad-muted">{{ $purity }} / 10 gram (calculated)</span>
                            <output class="font-bold text-ad-ink tabular-nums" data-rate-10g="{{ $purity }}" for="field-{{ $field }}" aria-live="polite">—</output>
                        </p>
                    </div>
                @endforeach

                <x-form.input name="source" label="Source" :value="$defaults['source']" maxlength="50" hint="Where this rate came from, e.g. Manual Admin Update." />
            </x-admin.form-grid>

            <p class="mt-4 text-xs text-ad-muted">Saving creates new records for 22K and 24K together. Existing rates are never changed; the latest effective time is shown on the website.</p>

            <x-slot:actions>
                <a href="{{ route('admin.gold-rates.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Rate</button>
            </x-slot:actions>
        </x-admin.form-card>
    </form>
@endsection
