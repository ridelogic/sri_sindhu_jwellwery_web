@props([
    'segments' => [],        // list of ['key' => string, 'label' => string, 'value' => int, 'color' => '#hex', 'href' => ?string]
    'centerValue' => null,   // number shown in the middle (defaults to the total)
    'centerLabel' => 'Total',
    'label' => 'Breakdown',
])

{{-- Lightweight SVG donut chart with a legend (no chart library). Values are real counts passed in by the page. --}}
@php
    $total = array_sum(array_map(fn ($s) => (int) $s['value'], $segments));
    $offset = 25.0; // start at 12 o'clock (circumference = 100)
    $arcs = [];
    foreach ($segments as $segment) {
        $share = $total > 0 ? ((int) $segment['value'] / $total) * 100 : 0;
        if ($share > 0) {
            $arcs[] = ['color' => $segment['color'], 'dash' => round($share, 3), 'offset' => round($offset, 3)];
        }
        $offset -= $share;
    }
@endphp

<div {{ $attributes->class('admin-donut') }} data-donut-total="{{ $total }}">
    <figure class="admin-donut-chart" role="img" aria-label="{{ $label }}: {{ collect($segments)->map(fn ($s) => $s['label'].' '.number_format($s['value']))->implode(', ') }}">
        <svg viewBox="0 0 42 42" class="size-full" aria-hidden="true">
            <circle cx="21" cy="21" r="15.915" fill="none" stroke="#e6e6e6" stroke-width="6.5" />
            @foreach ($arcs as $arc)
                <circle cx="21" cy="21" r="15.915" fill="none" stroke="{{ $arc['color'] }}" stroke-width="6.5"
                    stroke-dasharray="{{ $arc['dash'] }} {{ 100 - $arc['dash'] }}" stroke-dashoffset="{{ $arc['offset'] }}" />
            @endforeach
        </svg>
        <figcaption class="absolute inset-0 flex flex-col items-center justify-center text-center">
            <span class="admin-donut-value" data-donut-center>{{ number_format($centerValue ?? $total) }}</span>
            <span class="admin-donut-caption">{{ $centerLabel }}</span>
        </figcaption>
    </figure>

    <ul class="admin-donut-legend">
        @foreach ($segments as $segment)
            <li data-donut-segment="{{ $segment['key'] }}">
                <span class="flex min-w-0 items-center gap-2.5">
                    <span class="size-3 shrink-0 rounded-full" style="background-color: {{ $segment['color'] }}" aria-hidden="true"></span>
                    @if (! empty($segment['href']))
                        <a href="{{ $segment['href'] }}" class="truncate hover:underline">{{ $segment['label'] }}</a>
                    @else
                        <span class="truncate">{{ $segment['label'] }}</span>
                    @endif
                </span>
                <span class="shrink-0 font-semibold tabular-nums" data-donut-value>{{ number_format($segment['value']) }}</span>
            </li>
        @endforeach
    </ul>
</div>
