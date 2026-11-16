@props(['items', 'openFirst' => true, 'reveal' => true])

{{-- Accessible FAQ accordion (native <details>). Only one item per list stays open. --}}
<div {{ $attributes->merge(['class' => 'border-t border-line']) }} data-faq>
    @foreach ($items as $faq)
        <details class="group border-b border-line" @if ($openFirst && $loop->first) open @endif @if ($reveal) data-reveal data-reveal-delay="{{ min($loop->index, 6) * 60 }}" @endif>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-6 py-6 text-left font-serif text-[1.3rem] leading-snug font-medium transition-colors hover:text-gold-deep sm:py-7 sm:text-[1.55rem]">
                {{ $faq['question'] }}
                <span class="inline-flex size-9 shrink-0 items-center justify-center border border-line text-gold-deep transition duration-500 group-open:rotate-45 group-open:border-obsidian group-open:bg-obsidian group-open:text-ivory" aria-hidden="true">
                    <x-icon name="plus" class="size-4" />
                </span>
            </summary>
            <p class="max-w-2xl pr-4 pb-7 leading-relaxed text-taupe sm:pr-14">{{ $faq['answer'] }}</p>
        </details>
    @endforeach
</div>
