@extends('layouts.app')

@section('title', config('site.name').' | Gold Saving Scheme')
@section('meta_description', config('site.description'))

@push('head')
    <link rel="preload" as="image" href="{{ asset($hero['image']['mobile']) }}" media="(max-width: 767px)" fetchpriority="high">
    <link rel="preload" as="image" href="{{ asset($hero['image']['desktop']) }}" media="(min-width: 768px)" fetchpriority="high">
@endpush

@section('content')
    @include('home.sections.hero')
    @include('home.sections.gold-rate')
    @include('home.sections.gold-saving')
    @include('home.sections.editorial-bridal')
    @include('home.sections.editorial-everyday')
    @include('home.sections.why-sri-sindhu')
    @include('home.sections.how-it-works')
    @include('home.sections.app')
    @include('home.sections.stats')
    @include('home.sections.faq')
    @include('home.sections.final-cta')
@endsection
