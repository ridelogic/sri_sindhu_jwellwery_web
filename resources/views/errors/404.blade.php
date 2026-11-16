@extends('layouts.app')

@section('title', 'Page Not Found | Sri Sindhu Jewellery')
@section('meta_description', 'The page you are looking for could not be found.')
@section('robots', 'noindex')

@section('content')
    <x-page-hero
        eyebrow="Error 404"
        title="This Page Has Moved On."
        emphasis="Moved On."
        text="The page you are looking for doesn't exist or may have been moved."
        image="images/home/cta-gold-earrings.jpg"
        image-position="72% 55%"
        class="lg:min-h-[100svh]"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:gap-4">
            <a href="{{ route('home') }}" class="btn btn-gold">
                Back to Home
                <x-icon name="arrow-right" class="size-4" />
            </a>
            <a href="{{ route('gold.saving') }}" class="btn btn-ghost-light">Gold Saving Plans</a>
        </div>
    </x-page-hero>
@endsection
