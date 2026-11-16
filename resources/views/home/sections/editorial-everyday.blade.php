@php
    $everyday = $editorials['everyday'];
@endphp

<section class="section overflow-hidden bg-ivory" aria-labelledby="everyday-heading">
    <div class="container-site grid items-center gap-16 lg:grid-cols-12 lg:gap-10">
        {{-- Story --}}
        <div class="lg:col-span-5 lg:pr-8">
            <p class="eyebrow text-gold-deep" data-reveal>{{ $everyday['eyebrow'] }}</p>
            <h2 id="everyday-heading" class="display mt-7 text-[3rem] sm:text-6xl xl:text-[5.25rem]" data-reveal data-reveal-delay="100">
                <x-display-title :text="$everyday['title']" :emphasis="$everyday['title_emphasis']" emphasis-class="text-gold-deep" />
            </h2>
            <p class="mt-7 max-w-sm text-lg leading-relaxed text-taupe" data-reveal data-reveal-delay="200">{{ $everyday['text'] }}</p>

            <ul class="mt-10 flex flex-wrap gap-x-4 gap-y-3 border-y border-line py-5 text-[0.64rem] font-semibold tracking-[0.2em] text-obsidian/80 uppercase" data-reveal data-reveal-delay="250">
                @foreach ($everyday['categories'] as $category)
                    <li class="flex items-center gap-4">
                        {{ $category }}
                        @unless ($loop->last)<span class="size-1 rounded-full bg-champagne" aria-hidden="true"></span>@endunless
                    </li>
                @endforeach
            </ul>

            <a href="{{ $everyday['cta']['href'] }}" class="btn btn-dark mt-10 w-full sm:w-auto" data-reveal data-reveal-delay="300">
                {{ $everyday['cta']['label'] }}
                <x-icon name="arrow-right" class="size-4" />
            </a>
        </div>

        {{-- Layered photography --}}
        <div class="relative lg:col-span-7">
            <div class="group relative ml-auto w-full overflow-hidden sm:w-[85%]" data-reveal="fade">
                <div class="aspect-[4/5]">
                    <img src="{{ asset($everyday['image']) }}" alt="{{ $everyday['alt'] }}" class="img-zoom size-full object-cover" width="1200" height="1500" loading="lazy" decoding="async">
                </div>
            </div>

            <div class="relative -mt-24 w-[48%] border-[6px] border-ivory sm:absolute sm:bottom-14 sm:left-0 sm:mt-0 sm:w-[34%] sm:border-[10px]" data-reveal data-reveal-delay="200">
                <img src="{{ asset($everyday['detail_image']) }}" alt="{{ $everyday['detail_alt'] }}" class="aspect-[3/4] w-full object-cover" width="700" height="900" loading="lazy" decoding="async">
            </div>

        </div>
    </div>
</section>
