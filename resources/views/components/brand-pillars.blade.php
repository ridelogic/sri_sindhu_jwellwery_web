@props(['items'])

{{-- Brand principles in hairline-divided columns (works for 3 or 4 items). --}}
@php
    $count = count($items);
    $four = $count === 4;
    // Literal class names so Tailwind's scanner can find them.
    $grid = $four ? 'sm:grid-cols-2 lg:grid-cols-4' : ([2 => 'md:grid-cols-2', 3 => 'md:grid-cols-3'][$count] ?? 'md:grid-cols-3');
@endphp

<ol {{ $attributes->merge(['class' => 'grid border-t border-obsidian/15 '.$grid]) }}>
    @foreach ($items as $pillar)
        <li @class([
            'group relative px-2 pt-10 pb-12 sm:px-8 lg:px-10',
            'border-b border-obsidian/15',
            'lg:border-b-0' => $four,
            'md:border-b-0' => ! $four,
            'sm:border-l' => $four && $loop->iteration % 2 === 0,
            'lg:border-l' => $four && ! $loop->first,
            'sm:border-b-0' => $four && $loop->iteration > 2,
            'md:border-l' => ! $four && ! $loop->first,
        ]) data-reveal data-reveal-delay="{{ $loop->index * 100 }}">
            <span class="absolute top-[-1px] left-0 h-px w-full origin-left scale-x-0 bg-gold-deep transition-transform duration-700 group-hover:scale-x-100" aria-hidden="true"></span>
            <p class="font-serif text-lg text-gold-deep italic">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</p>
            <h3 class="mt-6 font-serif text-[2rem] leading-none tracking-[0.14em] uppercase sm:text-[2.2rem]">{{ $pillar['title'] }}</h3>
            <p class="mt-5 max-w-[19rem] text-[0.95rem] leading-relaxed text-taupe">{{ $pillar['text'] }}</p>
        </li>
    @endforeach
</ol>
