<section id="top" class="grain relative isolate flex min-h-[100svh] flex-col overflow-hidden bg-obsidian text-ivory" aria-labelledby="hero-heading">
    {{-- Cinematic photography --}}
    <div class="absolute inset-0 -z-10 overflow-hidden">
        <div class="hero-media absolute inset-0">
            <picture>
                <source media="(min-width: 768px)" srcset="{{ asset($hero['image']['desktop']) }}">
                {{-- Desktop image is mirrored so the jewellery sits opposite the headline --}}
                <img src="{{ asset($hero['image']['mobile']) }}"
                     alt="{{ $hero['image']['alt'] }}"
                     class="size-full object-cover object-[35%_center] md:-scale-x-100 md:object-center"
                     width="2200" height="1467" fetchpriority="high" decoding="async">
            </picture>
        </div>
        {{-- Overlays: keep text legible while letting the gold glow through --}}
        <div class="absolute inset-0 bg-gradient-to-t from-obsidian via-obsidian/70 to-obsidian/30 md:bg-gradient-to-r md:from-obsidian md:via-obsidian/75 md:to-obsidian/5"></div>
        <div class="absolute inset-x-0 bottom-0 h-48 bg-gradient-to-t from-obsidian to-transparent"></div>
        <div class="absolute inset-x-0 top-0 h-40 bg-gradient-to-b from-obsidian/80 to-transparent"></div>
    </div>

    <div class="container-site flex flex-1 items-end pt-32 pb-14 sm:pb-20 md:items-center md:pt-36 md:pb-24">
        <div class="max-w-[56rem]">
            <p class="eyebrow text-champagne" data-reveal="fade">{{ $hero['eyebrow'] }}</p>

            <h1 id="hero-heading" class="display mt-7 text-[3rem] text-ivory min-[400px]:text-[3.4rem] sm:text-[4.5rem] lg:text-[5.25rem] xl:text-[5.75rem]" data-reveal data-reveal-delay="120">
                @foreach ($hero['title_lines'] as $line)
                    <span @class(['block', 'italic text-gold-bright/95' => ! $loop->first])>{{ $line }}</span>
                @endforeach
            </h1>

            <p class="mt-7 max-w-md text-[0.98rem] leading-relaxed text-ivory/75 sm:text-lg" data-reveal data-reveal-delay="240">{{ $hero['text'] }}</p>

            <div class="mt-10 flex flex-col gap-3 sm:flex-row sm:gap-4" data-reveal data-reveal-delay="360">
                <a href="{{ $hero['primary_cta']['href'] }}" class="btn btn-gold">
                    {{ $hero['primary_cta']['label'] }}
                    <x-icon name="arrow-right" class="size-4" />
                </a>
                <a href="{{ $hero['secondary_cta']['href'] }}" class="btn btn-ghost-light">{{ $hero['secondary_cta']['label'] }}</a>
            </div>
        </div>
    </div>

    <x-gold-rate-strip :gold-rates="$goldRates" />
</section>
