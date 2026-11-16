@props(['name', 'stroke' => 1.5])

{{-- Inline line-icon set (24×24, stroke based) used across the public site. --}}
<svg {{ $attributes->merge(['class' => 'size-5 shrink-0']) }} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $stroke }}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('arrow-right')
            <path d="M5 12h14M13 6l6 6-6 6" />
            @break
        @case('arrow-up-right')
            <path d="M7 17 17 7M8 7h9v9" />
            @break
        @case('menu')
            <path d="M4 7h16M4 12h16M4 17h10" />
            @break
        @case('close')
            <path d="M6 6l12 12M18 6 6 18" />
            @break
        @case('user')
            <circle cx="12" cy="8" r="4" /><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6" />
            @break
        @case('user-plus')
            <circle cx="10" cy="8" r="4" /><path d="M2.5 21c1.4-3.8 4.2-6 7.5-6 1.6 0 3 .5 4.3 1.4M19 14v6M16 17h6" />
            @break
        @case('gem')
            <path d="M6 3h12l4 6-10 12L2 9z" /><path d="M2 9h20M10 3 8 9l4 12 4-12-2-6" />
            @break
        @case('calendar')
            <rect x="3" y="5" width="18" height="16" rx="2" /><path d="M16 3v4M8 3v4M3 11h18M8 15h2M14 15h2" />
            @break
        @case('wallet')
            <path d="M19 7V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3" /><path d="M21 8h-6a4 4 0 0 0 0 8h6z" /><circle cx="15.5" cy="12" r=".6" fill="currentColor" />
            @break
        @case('gift')
            <rect x="3" y="8" width="18" height="4" rx="1" /><path d="M12 8v13M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7M7.5 8a2.5 2.5 0 0 1 0-5C10 3 12 8 12 8s2-5 4.5-5a2.5 2.5 0 0 1 0 5" />
            @break
        @case('shield')
            <path d="M12 22s8-3.5 8-10V5l-8-3-8 3v7c0 6.5 8 10 8 10z" /><path d="m9 12 2 2 4-4" />
            @break
        @case('lock')
            <rect x="4" y="11" width="16" height="10" rx="2" /><path d="M8 11V7a4 4 0 0 1 8 0v4M12 15v2" />
            @break
        @case('award')
            <circle cx="12" cy="9" r="6" /><path d="m8.5 14-1.5 8 5-3 5 3-1.5-8" />
            @break
        @case('sparkle')
            <path d="M12 3c.6 4.2 2.8 6.4 7 7-4.2.6-6.4 2.8-7 7-.6-4.2-2.8-6.4-7-7 4.2-.6 6.4-2.8 7-7z" /><path d="M19 3v4M17 5h4" />
            @break
        @case('eye')
            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" /><circle cx="12" cy="12" r="3" />
            @break
        @case('layers')
            <path d="m12 3 9 5-9 5-9-5z" /><path d="m3 13 9 5 9-5" />
            @break
        @case('receipt')
            <path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2z" /><path d="M9 8h6M9 12h6M9 16h3" />
            @break
        @case('chart')
            <path d="M3 3v18h18" /><path d="m7 14 4-4 3 3 5-6" />
            @break
        @case('trend')
            <path d="M3 17 9 11l4 4 8-8" /><path d="M15 7h6v6" />
            @break
        @case('card')
            <rect x="2" y="5" width="20" height="14" rx="2" /><path d="M2 10h20M6 15h4" />
            @break
        @case('phone')
            <path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z" />
            @break
        @case('mail')
            <rect x="2" y="4" width="20" height="16" rx="2" /><path d="m22 6-10 7L2 6" />
            @break
        @case('map-pin')
            <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z" /><circle cx="12" cy="10" r="3" />
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />
            @break
        @case('smartphone')
            <rect x="6" y="2" width="12" height="20" rx="2.5" /><path d="M11 18h2" />
            @break
        @case('download')
            <path d="M12 3v12M7 10l5 5 5-5M5 21h14" />
            @break
        @case('chevron-down')
            <path d="m6 9 6 6 6-6" />
            @break
        @case('chevron-right')
            <path d="m9 6 6 6-6 6" />
            @break
        @case('bell')
            <path d="M6 9a6 6 0 1 1 12 0c0 6 2.5 7.5 2.5 7.5h-17S6 15 6 9z" /><path d="M10 20a2.2 2.2 0 0 0 4 0" />
            @break
        @case('file')
            <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" /><path d="M14 3v5h5M9 13h6M9 17h4" />
            @break
        @case('upload')
            <path d="M12 16V4M7 9l5-5 5 5M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3" />
            @break
        @case('alert')
            <circle cx="12" cy="12" r="9" /><path d="M12 7.5v5.5M12 16.5h.01" />
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14" />
            @break
        @case('check')
            <path d="m5 12 5 5L20 7" />
            @break
        @case('instagram')
            <rect x="3" y="3" width="18" height="18" rx="5" /><circle cx="12" cy="12" r="4" /><circle cx="17.5" cy="6.5" r=".6" fill="currentColor" />
            @break
        @case('facebook')
            <path d="M15 3h-2.5A4.5 4.5 0 0 0 8 7.5V10H5.5v4H8v7h4v-7h3l.5-4H12V7.8c0-.5.4-.8.8-.8H15z" />
            @break
        @case('youtube')
            <path d="M22 8.2a3 3 0 0 0-2.1-2.1C18 5.5 12 5.5 12 5.5s-6 0-7.9.6A3 3 0 0 0 2 8.2 31 31 0 0 0 1.5 12a31 31 0 0 0 .5 3.8 3 3 0 0 0 2.1 2.1c1.9.6 7.9.6 7.9.6s6 0 7.9-.6a3 3 0 0 0 2.1-2.1 31 31 0 0 0 .5-3.8 31 31 0 0 0-.5-3.8z" /><path d="m10 15 5-3-5-3z" />
            @break
        @case('whatsapp')
            <path d="M3 21l1.6-4.7A8.5 8.5 0 1 1 8 19.6z" /><path d="M9 9.5c0 3 2.5 5.5 5.5 5.5l1-1.5-2-1-1 .8a4 4 0 0 1-2.3-2.3l.8-1-1-2z" />
            @break
        {{-- Admin dashboard / navigation icons --}}
        @case('home')
            <path d="m3 11 9-7 9 7" /><path d="M5 10v10h5v-6h4v6h5V10" />
            @break
        @case('users')
            <circle cx="9" cy="8" r="3.5" /><path d="M2.5 20c.9-3.6 3.4-5.5 6.5-5.5s5.6 1.9 6.5 5.5" /><path d="M15.5 4.8a3.5 3.5 0 0 1 0 6.4M18 14.8c1.8.8 3 2.6 3.5 5.2" />
            @break
        @case('bars')
            <path d="M4 20V13M9 20V9M14 20V5M19 20v-9" /><path d="M2.5 20.5h19" />
            @break
        @case('coins')
            <ellipse cx="12" cy="6" rx="7" ry="2.5" /><path d="M5 6v4c0 1.4 3.1 2.5 7 2.5s7-1.1 7-2.5V6" /><path d="M5 10v4c0 1.4 3.1 2.5 7 2.5s7-1.1 7-2.5v-4" /><path d="M5 14v4c0 1.4 3.1 2.5 7 2.5s7-1.1 7-2.5v-4" />
            @break
        @case('cog')
            <circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z" />
            @break
        @case('logout')
            <path d="M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4" /><path d="M9 16l-4-4 4-4M5 12h11" />
            @break
        @case('external')
            <path d="M14 4h6v6M20 4l-9 9" /><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5" />
            @break
        @case('rupee')
            <path d="M7 4h11M7 9h11M7 4h3a5 5 0 0 1 0 10H7l8 7" />
            @break
    @endswitch
</svg>
