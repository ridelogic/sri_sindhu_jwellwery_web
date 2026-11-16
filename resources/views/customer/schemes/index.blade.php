@extends('layouts.app')

@section('title', 'My Schemes | Sri Sindhu Jewellery')
@section('meta_description', 'Your Sri Sindhu Jewellery gold saving schemes.')
@section('robots', 'noindex, nofollow')

@section('content')
    <x-customer-shell title="My Schemes" emphasis="Schemes" text="Every gold saving scheme you have joined, with the commitment agreed when you joined.">
        @if ($schemes->isEmpty())
            <div class="border border-dashed border-line px-6 py-12 text-center" data-empty-schemes>
                <p class="font-serif text-2xl">You have not joined a scheme yet.</p>
                <a href="{{ route('customer.plans.index') }}" class="btn btn-gold mt-6">Browse Plans <x-icon name="arrow-right" class="size-4" /></a>
            </div>
        @else
            <div class="grid gap-6 lg:grid-cols-2">
                @foreach ($schemes as $scheme)
                    <x-customer-scheme-card :scheme="$scheme" :progress="$progress[$scheme->id]" />
                @endforeach
            </div>
            <div class="mt-10 text-center">
                <a href="{{ route('customer.plans.index') }}" class="btn btn-dark"><x-icon name="plus" class="size-4" /> Join Another Scheme</a>
            </div>
        @endif
    </x-customer-shell>
@endsection
