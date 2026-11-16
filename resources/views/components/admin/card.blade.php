@props(['title' => null, 'icon' => null, 'padded' => true])

{{-- White card with an optional full-width header bar (detail sections, table wrappers). --}}
<section {{ $attributes->class('admin-card overflow-hidden') }} @if ($title) aria-label="{{ $title }}" @endif>
    @if ($title || isset($actions))
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-ad-line bg-ad-panel px-4 py-2.5 sm:px-5">
            @if ($title)
                <h2 class="flex items-center gap-2 text-sm font-bold text-ad-ink">
                    @if ($icon)<x-icon :name="$icon" class="size-4 text-ad-gold" />@endif
                    {{ $title }}
                </h2>
            @endif
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif
    <div @class(['p-4 sm:p-5' => $padded])>{{ $slot }}</div>
</section>
