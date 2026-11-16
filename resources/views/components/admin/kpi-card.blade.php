@props([
    'label',
    'value' => null,
    'key' => null,
    'icon' => 'chart',
    'hint' => null,
    'href' => null,
    'link' => null,
    'tone' => 'gold',      // pastel colour (variant="pastel"); ignored by the brown card
    'variant' => 'card',   // card = brown information card · pastel = light dashboard statistic card
    'empty' => 'Not recorded',
])

{{-- Compact KPI card. Keeps data-stat / data-stat-value hooks. --}}
@php
    $pastel = $variant === 'pastel';
    // [card background, icon background, icon colour]
    $tones = [
        'ivory' => ['#FBF5E9', '#F3E3BF', '#8A5A00'],
        'green' => ['#EEF7EA', '#D5ECCB', '#2F6B2A'],
        'rose' => ['#FCEEEE', '#F6D3D3', '#9B2C2C'],
        'blue' => ['#EAF3FC', '#CFE3F7', '#1F5C99'],
        'lavender' => ['#F1EDFC', '#DDD3F7', '#5B3FA6'],
        'mint' => ['#E9F8F3', '#C9EEDF', '#1E6E55'],
        'gold' => ['#FFF4D8', '#F8E1A4', '#8A5A00'],
    ];
    [$bg, $iconBg, $iconFg] = $tones[$tone] ?? $tones['ivory'];
@endphp

@if ($pastel)
    {{-- Icon + title/value top · description bottom-left · small circular arrow bottom-right --}}
    <article {{ $attributes->class('admin-stat-card flex flex-col') }} style="--stat-bg: {{ $bg }}; --stat-icon-bg: {{ $iconBg }}; --stat-icon: {{ $iconFg }}" @if ($key) data-stat="{{ $key }}" @endif>
        <div class="flex items-start gap-3">
            <span class="admin-stat-icon" aria-hidden="true"><x-icon :name="$icon" class="size-5" /></span>
            <div class="min-w-0">
                <h3 class="admin-stat-title">{{ $label }}</h3>
                @if ($value !== null)
                    <p class="admin-stat-value" data-stat-value>{{ $value }}</p>
                @else
                    <p class="admin-stat-value admin-stat-value-empty" data-stat-value>{{ $empty }}</p>
                @endif
            </div>
        </div>

        {{ $slot }}

        @if ($hint || $href)
            <div class="mt-auto flex items-end justify-between gap-3 pt-3">
                <p class="admin-stat-hint">{{ $hint }}</p>
                @if ($href)
                    {{-- Small circular arrow control; the label stays available to screen readers --}}
                    <a href="{{ $href }}" class="admin-stat-arrow" title="{{ $link }}">
                        <span class="sr-only">{{ $link }}</span>
                        <x-icon name="arrow-right" class="size-4" />
                    </a>
                @endif
            </div>
        @endif
    </article>
@else
    <article {{ $attributes->class('admin-card flex flex-col p-4') }} @if ($key) data-stat="{{ $key }}" @endif>
        <div class="flex items-start justify-between gap-3">
            <h3 class="text-[0.78rem] leading-snug font-semibold text-ad-muted">{{ $label }}</h3>
            <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-md bg-ad-dark text-ad-gold-soft">
                <x-icon :name="$icon" class="size-4" />
            </span>
        </div>

        @if ($value !== null)
            <p class="mt-1.5 text-[1.5rem] leading-tight font-bold tracking-tight text-ad-ink tabular-nums" data-stat-value>{{ $value }}</p>
        @else
            <p class="mt-1.5 text-sm font-medium text-ad-muted" data-stat-value>{{ $empty }}</p>
        @endif

        @if ($hint)
            <p class="mt-0.5 text-xs text-ad-muted">{{ $hint }}</p>
        @endif

        {{ $slot }}

        @if ($href)
            <a href="{{ $href }}" class="mt-auto inline-flex items-center gap-1 pt-2 text-xs font-bold text-ad-gold hover:text-ad-ink">
                {{ $link }}
                <x-icon name="arrow-right" class="size-3.5" />
            </a>
        @endif
    </article>
@endif
