@props(['eyebrow', 'title', 'emphasis' => null, 'text' => null, 'image' => 'images/home/editorial-bridal.jpg', 'imageAlt' => '', 'quote' => null])

{{-- Split-screen layout for login / register: editorial image + dark form panel. --}}
<section class="grain relative isolate bg-obsidian text-ivory" aria-labelledby="auth-heading">
    <div class="grid min-h-[100svh] lg:grid-cols-12">
        <div class="relative hidden overflow-hidden lg:col-span-5 lg:block xl:col-span-6">
            <img src="{{ asset($image) }}" alt="{{ $imageAlt }}" class="hero-media absolute inset-0 size-full object-cover object-top" width="1200" height="1800" decoding="async">
            <div class="absolute inset-0 bg-gradient-to-t from-obsidian via-obsidian/30 to-obsidian/60" aria-hidden="true"></div>
            @if ($quote)
                <p class="absolute right-12 bottom-14 left-12 max-w-md font-serif text-3xl leading-snug text-ivory italic">{{ $quote }}</p>
            @endif
        </div>

        <div class="flex items-center px-5 pt-32 pb-20 sm:px-8 lg:col-span-7 lg:px-16 xl:col-span-6 xl:px-24">
            <div class="mx-auto w-full max-w-md lg:mx-0">
                <p class="eyebrow text-champagne" data-reveal="fade">{{ $eyebrow }}</p>
                <h1 id="auth-heading" class="display mt-6 text-[2.75rem] sm:text-6xl" data-reveal data-reveal-delay="100">
                    <x-display-title :text="$title" :emphasis="$emphasis" />
                </h1>
                @if ($text)
                    <p class="mt-5 leading-relaxed text-ivory/70" data-reveal data-reveal-delay="150">{{ $text }}</p>
                @endif

                <div class="mt-10" data-reveal data-reveal-delay="200">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>
</section>
