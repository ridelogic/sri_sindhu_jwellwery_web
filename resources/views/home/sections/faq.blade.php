<section id="faq" class="section bg-ivory" aria-labelledby="faq-heading">
    <div class="container-site grid gap-14 lg:grid-cols-12 lg:gap-10">
        <div class="lg:col-span-4">
            <p class="eyebrow text-gold-deep" data-reveal>Questions</p>
            <h2 id="faq-heading" class="display mt-7 text-[2.7rem] sm:text-6xl" data-reveal data-reveal-delay="100">Before You <em class="text-gold-deep">Begin</em></h2>
            <p class="mt-6 max-w-sm leading-relaxed text-taupe" data-reveal data-reveal-delay="200">Our team is happy to help you choose the right plan.</p>
            <div class="mt-8 flex flex-col items-start gap-5" data-reveal data-reveal-delay="250">
                <a href="{{ route('faq') }}" class="link-line text-obsidian">
                    View All FAQs
                    <x-icon name="arrow-right" class="size-3.5" />
                </a>
                <a href="{{ route('contact') }}" class="link-line text-obsidian">
                    <x-icon name="phone" class="size-3.5" />
                    Speak to Our Team
                </a>
            </div>
        </div>

        <x-faq-list :items="$faqs" class="lg:col-span-7 lg:col-start-6" />
    </div>
</section>
