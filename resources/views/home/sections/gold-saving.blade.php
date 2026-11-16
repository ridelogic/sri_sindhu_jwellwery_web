<section id="gold-saving" class="section bg-ivory" aria-labelledby="gold-saving-heading">
    <div class="container-site">
        <div class="grid gap-8 lg:grid-cols-12 lg:items-end lg:gap-10">
            <div class="lg:col-span-7" data-reveal>
                <p class="eyebrow text-gold-deep">{{ $goldSaving['eyebrow'] }}</p>
                <h2 id="gold-saving-heading" class="display mt-6 max-w-3xl text-[2.7rem] sm:text-6xl lg:text-[4.5rem]"><x-display-title :text="$goldSaving['title']" :emphasis="$goldSaving['title_emphasis']" emphasis-class="text-gold-deep" /></h2>
            </div>
            <div class="lg:col-span-5 lg:pb-3" data-reveal data-reveal-delay="120">
                <p class="max-w-md text-base leading-relaxed text-taupe sm:text-lg">{{ $goldSaving['text'] }}</p>
                <a href="{{ $goldSaving['all_plans_url'] }}" class="link-line mt-6 text-obsidian">
                    View All Plans
                    <x-icon name="arrow-right" class="size-3.5" />
                </a>
            </div>
        </div>

        {{-- Plans share hairline borders for an editorial "price list" feel --}}
        <div class="mt-16 grid gap-px border border-line bg-line lg:mt-20 lg:grid-cols-3">
            @foreach ($goldSaving['plans'] as $plan)
                <div class="h-full" data-reveal data-reveal-delay="{{ $loop->index * 120 }}">
                    <x-scheme-card :plan="$plan" />
                </div>
            @endforeach
        </div>

        {{-- Section conversion band --}}
        <div class="mt-px flex flex-col items-start justify-between gap-8 bg-obsidian px-8 py-10 text-ivory sm:px-10 md:flex-row md:items-center xl:px-12" data-reveal>
            <div>
                <p class="font-serif text-[1.9rem] leading-tight sm:text-4xl">{{ $goldSaving['lowest_monthly_amount'] ? 'Begin with just '.\App\Support\Money::inr($goldSaving['lowest_monthly_amount']).' a month.' : 'New plans are coming soon.' }}</p>
                <p class="mt-2 text-sm text-stone">{{ $goldSaving['note'] }}</p>
            </div>
            <a href="{{ $goldSaving['cta']['href'] }}" class="btn btn-gold w-full shrink-0 md:w-auto">
                {{ $goldSaving['cta']['label'] }}
                <x-icon name="arrow-right" class="size-4" />
            </a>
        </div>
    </div>
</section>
