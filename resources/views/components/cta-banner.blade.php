@props([
    'title',
    'emphasis' => null,
    'text' => null,
    'eyebrow' => 'Gold Saving Scheme',
    'primary' => null,
    'secondary' => null,
    'image' => 'images/home/cta-gold-earrings.jpg',
    'headingId' => 'cta-heading',
])

{{-- Full-width cinematic closing call to action. --}}
<section {{ $attributes->merge(['class' => 'grain relative isolate flex min-h-[36rem] items-center overflow-hidden bg-obsidian text-ivory sm:min-h-[42rem] lg:min-h-[80svh]']) }} aria-labelledby="{{ $headingId }}">
    <div class="absolute inset-0 -z-10" aria-hidden="true">
        <img src="{{ asset($image) }}" alt="" class="size-full object-cover object-[72%_center]" width="2200" height="1467" loading="lazy" decoding="async">
        <div class="absolute inset-0 bg-gradient-to-t from-obsidian via-obsidian/75 to-obsidian/40 md:bg-gradient-to-r md:from-obsidian md:via-obsidian/70 md:to-transparent"></div>
    </div>

    <div class="container-site py-24">
        <div class="max-w-2xl">
            @if ($eyebrow)
                <p class="eyebrow text-champagne" data-reveal>{{ $eyebrow }}</p>
            @endif
            <h2 id="{{ $headingId }}" class="display mt-7 text-[3rem] min-[400px]:text-[3.4rem] sm:text-7xl lg:text-[5.75rem]" data-reveal data-reveal-delay="100">
                <x-display-title :text="$title" :emphasis="$emphasis" emphasis-class="text-gold-bright/95" />
            </h2>
            @if ($text)
                <p class="mt-7 text-lg text-ivory/75 sm:text-xl" data-reveal data-reveal-delay="200">{{ $text }}</p>
            @endif

            @if ($primary || $secondary)
                <div class="mt-11 flex flex-col gap-3 sm:flex-row sm:gap-4" data-reveal data-reveal-delay="300">
                    @if ($primary)
                        <a href="{{ $primary['href'] }}" class="btn btn-gold">
                            {{ $primary['label'] }}
                            <x-icon name="arrow-right" class="size-4" />
                        </a>
                    @endif
                    @if ($secondary)
                        <a href="{{ $secondary['href'] }}" class="btn btn-ghost-light">{{ $secondary['label'] }}</a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</section>
