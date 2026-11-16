<section id="about" class="section bg-parchment" aria-labelledby="about-heading">
    <div class="container-site">
        <div class="mx-auto max-w-3xl text-center">
            <p class="eyebrow justify-center text-gold-deep" data-reveal>{{ $pillars['eyebrow'] }}</p>
            <h2 id="about-heading" class="display mt-7 text-[2.7rem] sm:text-6xl lg:text-[4.5rem]" data-reveal data-reveal-delay="100">
                <x-display-title :text="$pillars['title']" :emphasis="$pillars['title_emphasis']" emphasis-class="text-gold-deep" />
            </h2>
            <p class="mx-auto mt-7 max-w-xl text-base leading-relaxed text-taupe sm:text-lg" data-reveal data-reveal-delay="200">{{ $pillars['text'] }}</p>
        </div>

        <x-brand-pillars :items="$pillars['items']" class="mt-20 lg:mt-24" />

        <div class="mt-14 text-center" data-reveal>
            <a href="{{ $pillars['cta']['href'] }}" class="link-line text-obsidian">
                {{ $pillars['cta']['label'] }}
                <x-icon name="arrow-right" class="size-3.5" />
            </a>
        </div>
    </div>
</section>
