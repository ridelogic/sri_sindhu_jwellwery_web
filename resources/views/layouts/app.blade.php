<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $pageTitle = trim($__env->yieldContent('title')) ?: config('site.name').' | Gold Saving Scheme';
        $pageDescription = trim($__env->yieldContent('meta_description')) ?: config('site.description');
        $pageImage = asset(trim($__env->yieldContent('og_image')) ?: 'images/home/og-image.jpg');
        $pageType = trim($__env->yieldContent('og_type')) ?: 'website';
    @endphp

    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta name="theme-color" content="#0b0a08">
    @hasSection('robots')
        <meta name="robots" content="@yield('robots')">
    @endif

    {{-- Open Graph / social sharing (placeholders: replace image with brand artwork) --}}
    <meta property="og:type" content="{{ $pageType }}">
    <meta property="og:site_name" content="{{ config('site.name') }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ $pageImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:locale" content="en_IN">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $pageDescription }}">
    <meta name="twitter:image" content="{{ $pageImage }}">

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500;1,600&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">

    {{-- Enables scroll-reveal styles only when JavaScript is available --}}
    <script>document.documentElement.classList.add('js');</script>

    @stack('head')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen overflow-x-clip">
    <a href="#main" class="sr-only z-[60] rounded-full bg-obsidian px-5 py-3 text-sm text-ivory focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-[70]">Skip to content</a>

    <x-header />

    <main id="main">
        @yield('content')
    </main>

    <x-footer />

    @stack('scripts')
</body>
</html>
