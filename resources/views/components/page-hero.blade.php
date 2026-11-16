@props([
    'eyebrow',
    'title',
    'emphasis' => null,
    'text' => null,
    'image' => 'images/home/hero-gold-necklace.jpg',
    'imagePosition' => 'center',
    'mirror' => false,
    'breadcrumbs' => [],
    'headingId' => 'page-heading',
])

{{-- Dark editorial hero for inner pages. The header sits transparently on top of it. --}}
<section {{ $attributes->merge(['class' => 'grain relative isolate flex min-h-[32rem] flex-col justify-end overflow-hidden bg-obsidian text-ivory sm:min-h-[36rem] lg:min-h-[42rem]']) }} aria-labelledby="{{ $headingId }}">
    <div class="absolute inset-0 -z-10 overflow-hidden" aria-hidden="true">
        <img src="{{ asset($image) }}" alt="" class="hero-media size-full object-cover opacity-70 @if ($mirror) -scale-x-100 @endif" style="object-position: {{ $imagePosition }}" width="2200" height="1467" fetchpriority="high" decoding="async">
        <div class="absolute inset-0 bg-gradient-to-t from-obsidian via-obsidian/80 to-obsidian/45 md:bg-gradient-to-r md:from-obsidian md:via-obsidian/80 md:to-obsidian/20"></div>
        <div class="absolute inset-x-0 bottom-0 h-32 bg-gradient-to-t from-obsidian to-transparent"></div>
        <div class="absolute inset-x-0 top-0 h-36 bg-gradient-to-b from-obsidian/80 to-transparent"></div>
    </div>

    <div class="container-site pt-36 pb-16 sm:pb-20 lg:pb-24">
        @if ($breadcrumbs)
            <nav aria-label="Breadcrumb" data-reveal="fade">
                <ol class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[0.65rem] font-semibold tracking-[0.24em] text-stone uppercase">
                    @foreach ($breadcrumbs as $crumb)
                        <li class="flex items-center gap-3">
                            @if (! empty($crumb['href']) && ! $loop->last)
                                <a href="{{ $crumb['href'] }}" class="transition-colors hover:text-ivory">{{ $crumb['label'] }}</a>
                                <span class="h-px w-4 bg-stone/50" aria-hidden="true"></span>
                            @else
                                <span aria-current="page" class="text-ivory/85">{{ $crumb['label'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif

        <div class="max-w-4xl">
            <p @class(['eyebrow text-champagne', 'mt-10' => $breadcrumbs]) data-reveal="fade">{{ $eyebrow }}</p>
            <h1 id="{{ $headingId }}" class="display mt-6 text-[2.9rem] min-[400px]:text-[3.25rem] sm:text-[4.25rem] lg:text-[5.25rem]" data-reveal data-reveal-delay="100">
                <x-display-title :text="$title" :emphasis="$emphasis" emphasis-class="text-gold-bright/95" />
            </h1>
            @if ($text)
                <p class="mt-6 max-w-xl text-[0.98rem] leading-relaxed text-ivory/75 sm:text-lg" data-reveal data-reveal-delay="200">{{ $text }}</p>
            @endif

            @if (trim($slot))
                <div class="mt-10" data-reveal data-reveal-delay="300">{{ $slot }}</div>
            @endif
        </div>
    </div>
</section>
