@props(['steps'])

{{-- Gold saving journey on a dark background: vertical on mobile, horizontal from lg. --}}
@php
    $count = count($steps);
    $columns = [4 => 'lg:grid-cols-4', 5 => 'lg:grid-cols-5', 6 => 'lg:grid-cols-6'][$count] ?? 'lg:grid-cols-6';
@endphp

<ol {{ $attributes->merge(['class' => "grid $columns"]) }}>
    @foreach ($steps as $step)
        <li class="relative pb-12 pl-12 last:pb-0 lg:pt-14 lg:pr-6 lg:pb-0 lg:pl-0" data-reveal data-reveal-delay="{{ $loop->index * 110 }}">
            @unless ($loop->last)
                <span class="absolute top-3 bottom-0 left-[5px] w-px bg-ivory/15 lg:top-[5px] lg:right-0 lg:bottom-auto lg:left-[11px] lg:h-px lg:w-auto" aria-hidden="true"></span>
            @endunless
            <span @class([
                'absolute top-1.5 left-0 size-[11px] rounded-full border lg:top-0',
                'border-gold-bright bg-gold-bright' => $loop->last,
                'border-champagne bg-obsidian' => ! $loop->last,
            ]) aria-hidden="true"></span>

            <p class="font-serif text-[2.5rem] leading-none text-champagne lg:text-5xl">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</p>
            <h3 class="mt-4 font-sans text-[0.75rem] font-semibold tracking-[0.24em] text-ivory uppercase">{{ $step['title'] }}</h3>
            <p class="mt-3 max-w-[15rem] text-sm leading-relaxed text-stone">{{ $step['text'] }}</p>
        </li>
    @endforeach
</ol>
