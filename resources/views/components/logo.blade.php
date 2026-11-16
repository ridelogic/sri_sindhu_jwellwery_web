@props(['variant' => 'dark'])

@php
    $nameColor = $variant === 'light' ? 'text-ivory' : 'text-obsidian';
@endphp

<a href="{{ route('home') }}" {{ $attributes->merge(['class' => 'group inline-flex items-center gap-3']) }} aria-label="{{ config('site.name') }} — Home">
    {{-- Brand mark: a lotus-petal jewel inside a fine gold ring --}}
    <svg class="size-9 shrink-0 text-champagne transition-transform duration-700 group-hover:rotate-[10deg] sm:size-10" viewBox="0 0 40 40" fill="none" aria-hidden="true">
        <circle cx="20" cy="20" r="18.5" stroke="currentColor" stroke-width=".9" />
        <path d="M20 9c3.2 3.6 4.4 7.3 3.6 11-.5 2.4-1.7 4.4-3.6 6-1.9-1.6-3.1-3.6-3.6-6-.8-3.7.4-7.4 3.6-11z" fill="currentColor" />
        <path d="M11 17.5c3.6.4 6.3 2 8 4.6.9 1.4 1.3 2.8 1 4.4-2.9.4-5.3-.5-7-2.6-1.4-1.8-2-3.9-2-6.4zM29 17.5c-3.6.4-6.3 2-8 4.6-.9 1.4-1.3 2.8-1 4.4 2.9.4 5.3-.5 7-2.6 1.4-1.8 2-3.9 2-6.4z" fill="currentColor" opacity=".5" />
        <path d="M13 29.5h14" stroke="currentColor" stroke-width=".9" stroke-linecap="round" />
    </svg>
    <span class="flex flex-col leading-none">
        <span class="font-serif text-[1.35rem] font-semibold tracking-[0.01em] sm:text-[1.5rem] {{ $nameColor }}">Sri Sindhu</span>
        <span class="mt-1 text-[0.52rem] font-semibold tracking-[0.5em] text-champagne uppercase">Jewellery</span>
    </span>
</a>
