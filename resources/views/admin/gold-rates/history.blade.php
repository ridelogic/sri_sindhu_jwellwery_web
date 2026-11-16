@extends('layouts.admin')

@section('title', 'Rate History')
@section('subtitle', 'Every published rate update, newest first.')
@section('back', route('admin.gold-rates.index'))
@section('back_label', 'Gold Rates')

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="max-w-xl text-sm text-taupe">Every gold rate update, newest first. Records are never edited or deleted.</p>
        <a href="{{ route('admin.gold-rates.create') }}" class="btn btn-dark w-full shrink-0 sm:w-auto">
            <x-icon name="plus" class="size-4" />
            Update Today's Rate
        </a>
    </div>

    {{-- Date filter --}}
    <form method="GET" action="{{ route('admin.gold-rates.history') }}" class="mt-6 grid gap-4 rounded-lg border border-line bg-white p-5 sm:grid-cols-[1fr_1fr_auto] sm:items-end" role="search" aria-label="Filter rate history">
        <x-form.input name="from" label="From Date" type="date" :value="$filters['from'] ?? null" max="{{ today()->toDateString() }}" />
        <x-form.input name="to" label="To Date" type="date" :value="$filters['to'] ?? null" max="{{ today()->toDateString() }}" />
        <div class="flex gap-3">
            <button type="submit" class="btn btn-dark min-h-[3.1rem] flex-1 sm:flex-none">Search</button>
            @if (! empty($filters))
                <a href="{{ route('admin.gold-rates.history') }}" class="btn btn-ghost-dark min-h-[3.1rem] flex-1 sm:flex-none">Reset</a>
            @endif
        </div>
    </form>

    <section class="mt-6 rounded-lg border border-line bg-white" aria-label="Rate history">
        <p class="border-b border-line px-5 py-3 text-xs text-taupe" data-history-count>
            {{ $updates->total() }} {{ \Illuminate\Support\Str::plural('update', $updates->total()) }}
        </p>
        @include('admin.gold-rates.partials.updates-table', ['updates' => $updates])

        @if ($updates->hasPages())
            <div class="border-t border-line px-5 py-4">{{ $updates->links('admin.partials.pagination') }}</div>
        @endif
    </section>
@endsection
