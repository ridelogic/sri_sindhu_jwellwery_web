@php
    $screen = $appPreview['screen'];
@endphp

<section id="app" class="section overflow-hidden bg-ivory" aria-labelledby="app-heading">
    <div class="container-site grid items-center gap-20 lg:grid-cols-12 lg:gap-10">
        {{-- Illustrative phone mockup (placeholder data) --}}
        <div class="relative order-2 flex justify-center lg:order-1 lg:col-span-6" data-reveal="fade">
            <div class="absolute top-1/2 left-1/2 aspect-[4/5] w-[19rem] -translate-x-1/2 -translate-y-1/2 rounded-t-full bg-parchment sm:w-[26rem]" aria-hidden="true"></div>
            <div class="absolute top-1/2 left-1/2 aspect-[4/5] w-[22rem] -translate-x-1/2 -translate-y-1/2 rounded-t-full border border-champagne/40 sm:w-[30rem]" aria-hidden="true"></div>

            <div class="relative w-[16.5rem] rounded-[2.9rem] bg-obsidian p-2.5 shadow-[0_60px_100px_-40px_rgb(11_10_8/0.8)] ring-1 ring-black sm:w-[18rem]"
                 role="img" aria-label="Preview of the {{ config('site.name') }} app showing today's gold rate, savings paid, gold accumulated and the next payment">
                <div class="overflow-hidden rounded-[2.4rem] bg-espresso text-ivory" aria-hidden="true">
                    <div class="flex items-center justify-between px-6 pt-3.5 text-[0.58rem] font-semibold text-ivory/80">
                        <span>9:41</span>
                        <span class="h-[1.3rem] w-[5.2rem] rounded-full bg-obsidian"></span>
                        <span>5G</span>
                    </div>

                    <div class="px-5 pt-5 pb-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-[0.6rem] text-stone">{{ $screen['customer'] }}</p>
                                <p class="font-serif text-lg leading-tight">Sri Sindhu</p>
                            </div>
                            <span class="inline-flex size-8 items-center justify-center rounded-full border border-champagne/40 text-champagne"><x-icon name="user" class="size-3.5" /></span>
                        </div>

                        {{-- Today's rate --}}
                        <div class="mt-5 border border-ivory/10 px-4 py-3">
                            <p class="text-[0.55rem] font-semibold tracking-[0.18em] text-stone uppercase">{{ $screen['rate_label'] }}</p>
                            <p class="mt-1 text-[0.8rem] font-semibold text-gold-bright">{{ $screen['rate'] }}</p>
                        </div>

                        {{-- Savings --}}
                        <div class="mt-3 bg-gradient-to-br from-[#2a2117] to-obsidian p-4 ring-1 ring-champagne/25">
                            <p class="text-[0.55rem] font-semibold tracking-[0.2em] text-champagne uppercase">My Gold Savings</p>
                            <p class="mt-3 font-serif text-[2rem] leading-none">{{ $screen['paid'] }}</p>
                            <p class="mt-1 text-[0.6rem] text-stone">Paid</p>
                            <div class="mt-4 flex items-end justify-between border-t border-ivory/10 pt-3">
                                <div>
                                    <p class="font-serif text-xl leading-none text-gold-bright">{{ $screen['grams'] }}</p>
                                    <p class="mt-1 text-[0.6rem] text-stone">Accumulated</p>
                                </div>
                                <p class="text-[0.6rem] text-stone">{{ $screen['plan'] }} · {{ $screen['installments'] }}</p>
                            </div>
                            <div class="mt-3 h-1 bg-ivory/10">
                                <div class="h-full bg-champagne" style="width: {{ (int) $screen['progress'] }}%"></div>
                            </div>
                        </div>

                        {{-- Next payment --}}
                        <div class="mt-3 flex items-center justify-between border border-ivory/10 px-4 py-3.5">
                            <div>
                                <p class="text-[0.55rem] font-semibold tracking-[0.18em] text-stone uppercase">Next Payment</p>
                                <p class="mt-1 font-serif text-xl leading-none">{{ $screen['next_payment'] }}</p>
                            </div>
                            <p class="text-[0.6rem] text-stone">Due {{ $screen['next_due'] }}</p>
                        </div>

                        <div class="mt-4 bg-champagne py-3 text-center text-[0.6rem] font-bold tracking-[0.2em] text-obsidian uppercase">Pay Now</div>

                        <div class="mt-5 flex justify-around border-t border-ivory/10 pt-3 text-ivory/50">
                            <x-icon name="gem" class="size-4 text-champagne" />
                            <x-icon name="chart" class="size-4" />
                            <x-icon name="receipt" class="size-4" />
                            <x-icon name="user" class="size-4" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Copy --}}
        <div class="order-1 lg:order-2 lg:col-span-6 lg:pl-10">
            <p class="eyebrow text-gold-deep" data-reveal>Sri Sindhu App</p>
            <h2 id="app-heading" class="display mt-7 text-[2.9rem] sm:text-6xl lg:text-[4.75rem]" data-reveal data-reveal-delay="100">
                <x-display-title :text="$appPreview['title']" :emphasis="$appPreview['title_emphasis']" emphasis-class="block text-gold-deep" />
            </h2>
            <p class="mt-7 max-w-md text-lg leading-relaxed text-taupe" data-reveal data-reveal-delay="200">{{ $appPreview['text'] }}</p>

            <ul class="mt-10 max-w-md divide-y divide-line border-y border-line" data-reveal data-reveal-delay="250">
                @foreach ($appPreview['features'] as $feature)
                    <li class="flex items-center justify-between py-4 text-[0.7rem] font-semibold tracking-[0.22em] uppercase">
                        {{ $feature }}
                        <x-icon name="check" class="size-4 text-gold-deep" stroke="1.8" />
                    </li>
                @endforeach
            </ul>

            {{-- App store links come from config/site.php; without one the button shows a disabled "Coming Soon" state --}}
            <div class="mt-10 flex flex-col gap-5 sm:flex-row sm:items-center sm:gap-8" data-reveal data-reveal-delay="300">
                @if ($appPreview['cta']['href'])
                    <a href="{{ $appPreview['cta']['href'] }}" class="btn btn-dark w-full sm:w-auto" rel="noopener" target="_blank">
                        <x-icon name="download" class="size-4" />
                        {{ $appPreview['cta']['label'] }}
                    </a>
                @else
                    <span class="btn w-full cursor-not-allowed border border-obsidian/20 text-obsidian/55 sm:w-auto" aria-disabled="true" data-app-coming-soon>
                        <x-icon name="smartphone" class="size-4" />
                        {{ $appPreview['cta']['unavailable_label'] }}
                    </span>
                @endif
                <a href="{{ $appPreview['secondary_cta']['href'] }}" class="link-line self-start text-obsidian sm:self-auto">{{ $appPreview['secondary_cta']['label'] }}</a>
            </div>
        </div>
    </div>
</section>
