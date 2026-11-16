<section class="grain relative isolate bg-espresso text-ivory" aria-label="{{ config('site.name') }} in numbers">
    <div class="container-site">
        <dl class="grid grid-cols-2 lg:grid-cols-4">
            @foreach ($stats as $stat)
                <div @class([
                    'flex flex-col items-center px-3 py-14 text-center sm:py-20',
                    'border-ivory/10',
                    'border-l' => $loop->iteration % 2 === 0,
                    'lg:border-l' => ! $loop->first,
                    'border-t lg:border-t-0' => $loop->iteration > 2,
                ]) data-reveal data-reveal-delay="{{ $loop->index * 100 }}">
                    <dt class="order-2 mt-5 text-[0.62rem] font-semibold tracking-[0.3em] text-stone uppercase sm:text-[0.68rem]">{{ $stat['label'] }}</dt>
                    <dd class="order-1 font-serif text-[3.25rem] leading-none font-light text-ivory sm:text-7xl">{{ $stat['value'] }}</dd>
                    <span class="order-3 mt-6 h-px w-8 bg-champagne/70" aria-hidden="true"></span>
                </div>
            @endforeach
        </dl>
    </div>
</section>
