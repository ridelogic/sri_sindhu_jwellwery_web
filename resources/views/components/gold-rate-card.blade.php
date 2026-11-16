@props(['goldRates'])

{{--
    Large editorial display of today's rates.
    Expects: ['updated_at' => Carbon, 'rates' => [['purity', 'label', 'price_per_gram'], ...]]
    data-* hooks allow the values to be refreshed client-side from the API later.
--}}
<dl {{ $attributes->merge(['class' => 'grid gap-10 sm:grid-cols-2 sm:gap-0']) }} data-gold-rate-card>
    @foreach ($goldRates['rates'] as $rate)
        <div @class(['border-t border-ivory/15 pt-8 sm:pt-10', 'sm:border-l sm:pl-10 lg:pl-14' => ! $loop->first, 'sm:pr-10' => $loop->first]) data-gold-rate="{{ $rate['purity'] }}">
            <dt class="flex items-center justify-between text-[0.68rem] font-semibold tracking-[0.3em] text-stone uppercase">
                <span><span class="font-serif text-2xl tracking-normal text-champagne normal-case">{{ $rate['purity'] }}</span></span>
                <span>{{ $rate['label'] }}</span>
            </dt>
            <dd class="mt-6">
                <span class="block font-serif text-[4.25rem] leading-none font-light text-ivory sm:text-[4.5rem] lg:text-[4.75rem] xl:text-[6rem]" data-gold-rate-value>{{ \App\Support\Money::inr($rate['price_per_gram']) }}</span>
                <span class="mt-4 block text-[0.68rem] font-semibold tracking-[0.34em] text-stone uppercase">Per Gram</span>
                <span class="mt-6 flex items-baseline justify-between gap-4 border-t border-ivory/10 pt-4">
                    <span class="text-[0.62rem] font-semibold tracking-[0.28em] text-stone uppercase">{{ $rate['purity'] }} / 10 Gram</span>
                    <span class="text-lg font-semibold text-ivory tabular-nums" data-gold-rate-10g>{{ \App\Support\Money::inr($rate['price_per_10_gram']) }}</span>
                </span>
            </dd>
        </div>
    @endforeach
</dl>
