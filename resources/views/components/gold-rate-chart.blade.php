@props(['title', 'points', 'id'])

{{--
    Single-series line chart (one chart per purity = small multiples).
    $points: list of ['date' => Carbon, 'value' => int], oldest first.
    The line is an SVG scaled to the box; labels are HTML so they stay legible
    at every width. Hover/focus behaviour is added by resources/js/site/rate-chart.js.
--}}
@php
    $values = array_column($points, 'value');
    $min = min($values);
    $max = max($values);
    // "Nice" round axis: 3 equal steps of 10/20/25/50/100… that contain the data with headroom.
    $rough = max(1, ($max - $min) * 1.15) / 3;
    $magnitude = 10 ** floor(log10($rough));
    $step = collect([1, 2, 2.5, 5, 10])->map(fn ($m) => $m * $magnitude)->first(fn ($s) => $s >= $rough);
    $lo = floor(($min - $step * 0.25) / $step) * $step;
    while ($lo + 3 * $step < $max) { $step *= 2; $lo = floor(($min - $step * 0.25) / $step) * $step; }
    $hi = $lo + 3 * $step;
    $range = $hi - $lo;
    $count = count($points);

    $x = fn (int $i) => $count > 1 ? $i / ($count - 1) * 100 : 50;
    $y = fn (int|float $v) => (1 - ($v - $lo) / $range) * 100;

    $path = collect($points)->map(fn ($p, $i) => ($i ? 'L' : 'M').round($x($i), 3).' '.round($y($p['value']), 3))->implode(' ');
    $area = $path.' L 100 100 L 0 100 Z';

    $ticks = collect(range(0, 3))->map(fn ($t) => $lo + $range * $t / 3);
    $last = $points[$count - 1];

    $data = collect($points)->map(fn ($p, $i) => [
        'x' => round($x($i), 3),
        'y' => round($y($p['value']), 3),
        'date' => $p['date']->format('d M Y'),
        'value' => \App\Support\Money::inr($p['value']),
    ])->values();
@endphp

<figure {{ $attributes->merge(['class' => 'border border-ivory/10 bg-obsidian/40 p-5 sm:p-7']) }}>
    <figcaption class="flex flex-wrap items-baseline justify-between gap-2">
        <span class="font-serif text-2xl text-ivory">{{ $title }} <span class="ml-2 font-sans text-base font-semibold text-gold-bright tabular-nums">{{ \App\Support\Money::inr($last['value']) }}</span></span>
        <span class="text-[0.62rem] font-semibold tracking-[0.24em] text-stone uppercase">Per gram · last {{ $count }} days</span>
    </figcaption>

    <div class="relative mt-6 h-56 pb-7 pl-16 sm:h-64" data-rate-chart='@json($data)' id="{{ $id }}">
        {{-- Y axis labels: a column matching the plot height --}}
        <div class="absolute top-0 bottom-7 left-0 w-14" aria-hidden="true">
            @foreach ($ticks as $tick)
                <span class="absolute left-0 -translate-y-1/2 text-[0.7rem] text-stone tabular-nums" style="top: {{ $y($tick) }}%">{{ \App\Support\Money::inr($tick) }}</span>
            @endforeach
        </div>

        <div class="relative h-full" data-plot>
            <svg class="absolute inset-0 size-full overflow-visible" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                @foreach ($ticks as $tick)
                    <line x1="0" x2="100" y1="{{ $y($tick) }}" y2="{{ $y($tick) }}" stroke="currentColor" class="text-ivory/10" vector-effect="non-scaling-stroke" stroke-width="1" />
                @endforeach
                <path d="{{ $area }}" class="fill-champagne/10" />
                <path d="{{ $path }}" fill="none" class="stroke-champagne" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
            </svg>

            {{-- Latest value marker (value is shown in the caption) --}}
            <span class="absolute size-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-champagne ring-2 ring-obsidian" style="left: 100%; top: {{ $y($last['value']) }}%" aria-hidden="true"></span>

            {{-- Hover layer (populated by JS) --}}
            <span class="pointer-events-none absolute inset-y-0 hidden w-px bg-ivory/30" data-crosshair></span>
            <span class="pointer-events-none absolute hidden size-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-gold-bright ring-2 ring-obsidian" data-dot></span>
            <span class="pointer-events-none absolute top-0 z-10 hidden -translate-x-1/2 border border-ivory/15 bg-obsidian px-3 py-2 text-xs whitespace-nowrap text-ivory shadow-lg" data-tooltip role="status"></span>

            {{-- X axis labels --}}
            <span class="absolute -bottom-7 left-0 text-[0.7rem] text-stone">{{ $points[0]['date']->format('d M') }}</span>
            <span class="absolute right-0 -bottom-7 text-[0.7rem] text-stone">{{ $last['date']->format('d M') }}</span>
        </div>
    </div>
</figure>
