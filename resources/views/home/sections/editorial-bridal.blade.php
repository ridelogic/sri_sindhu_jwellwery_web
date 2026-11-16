@php
    $bridal = $editorials['bridal'];
@endphp

<section id="collections" class="grain relative isolate overflow-hidden bg-espresso text-ivory" aria-labelledby="bridal-heading">
    <div class="grid lg:grid-cols-12">
        {{-- Full-bleed editorial photograph --}}
        <div class="group relative overflow-hidden lg:col-span-7" data-reveal="fade">
            <div class="aspect-[4/5] sm:aspect-[4/3] lg:aspect-auto lg:h-full lg:min-h-[52rem]">
                <img src="{{ asset($bridal['image']) }}" alt="{{ $bridal['alt'] }}" class="img-zoom size-full object-cover object-[50%_18%]" width="1200" height="1800" loading="lazy" decoding="async">
            </div>
            <div class="absolute inset-0 bg-gradient-to-t from-espresso/70 via-transparent to-transparent lg:bg-gradient-to-r lg:from-transparent lg:via-transparent lg:to-espresso/40" aria-hidden="true"></div>
            <p class="absolute bottom-6 left-5 text-[0.62rem] font-semibold tracking-[0.34em] text-ivory/80 uppercase sm:left-8 lg:bottom-10 lg:left-12">Editorial — No. 01</p>
        </div>

        {{-- Story --}}
        <div class="relative flex items-center px-5 py-20 sm:px-8 sm:py-24 lg:col-span-5 lg:px-14 lg:py-28 xl:px-20">
            <div class="max-w-md">
                <p class="eyebrow text-champagne" data-reveal>{{ $bridal['eyebrow'] }}</p>
                <h2 id="bridal-heading" class="display mt-7 text-[2.9rem] sm:text-6xl xl:text-7xl" data-reveal data-reveal-delay="100">
                    <x-display-title :text="$bridal['title']" :emphasis="$bridal['title_emphasis']" />
                </h2>
                <p class="mt-7 text-[0.98rem] leading-relaxed text-ivory/70" data-reveal data-reveal-delay="200">{{ $bridal['text'] }}</p>

                <a href="{{ $bridal['cta']['href'] }}" class="btn btn-ghost-light mt-10 w-full sm:w-auto" data-reveal data-reveal-delay="300">
                    {{ $bridal['cta']['label'] }}
                    <x-icon name="arrow-right" class="size-4" />
                </a>

                <figure class="mt-16 flex items-end gap-6 border-t border-ivory/10 pt-10" data-reveal data-reveal-delay="400">
                    <img src="{{ asset($bridal['detail_image']) }}" alt="{{ $bridal['detail_alt'] }}" class="aspect-[3/4] w-28 shrink-0 object-cover sm:w-36" width="700" height="900" loading="lazy" decoding="async">
                    <figcaption class="pb-1">
                        <ul class="space-y-2.5 text-[0.65rem] font-semibold tracking-[0.26em] text-stone uppercase">
                            @foreach ($bridal['highlights'] as $highlight)
                                <li class="flex items-center gap-3"><span class="h-px w-4 bg-champagne" aria-hidden="true"></span>{{ $highlight }}</li>
                            @endforeach
                        </ul>
                    </figcaption>
                </figure>
            </div>
        </div>
    </div>
</section>
