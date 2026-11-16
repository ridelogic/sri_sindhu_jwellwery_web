<section id="how-it-works" class="grain section relative isolate overflow-hidden bg-obsidian text-ivory" aria-labelledby="how-heading">
    <div class="container-site">
        <div class="flex flex-col items-start justify-between gap-10 lg:flex-row lg:items-end">
            <div>
                <p class="eyebrow text-champagne" data-reveal>{{ $savingSteps['eyebrow'] }}</p>
                <h2 id="how-heading" class="display mt-7 text-[2.7rem] sm:text-6xl lg:text-[4.5rem]" data-reveal data-reveal-delay="100">
                    <x-display-title :text="$savingSteps['title']" :emphasis="$savingSteps['title_emphasis']" />
                </h2>
            </div>
            <a href="{{ $savingSteps['cta']['href'] }}" class="btn btn-gold w-full sm:w-auto" data-reveal data-reveal-delay="200">
                {{ $savingSteps['cta']['label'] }}
                <x-icon name="arrow-right" class="size-4" />
            </a>
        </div>

        <x-saving-timeline :steps="$savingSteps['items']" class="mt-20 lg:mt-24" />
    </div>
</section>
