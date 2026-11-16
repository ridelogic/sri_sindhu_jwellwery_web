@props(['icon' => 'layers', 'title', 'text' => null])

{{-- Empty state for admin lists and sections. --}}
<div {{ $attributes->class('flex flex-col items-center px-6 py-8 text-center') }}>
    <span class="inline-flex size-9 items-center justify-center rounded-full bg-ad-panel text-ad-gold ring-1 ring-ad-line">
        <x-icon :name="$icon" class="size-4" />
    </span>
    <p class="mt-2.5 text-sm font-semibold text-ad-ink">{{ $title }}</p>
    @if ($text)
        <p class="mt-1 max-w-sm text-sm text-ad-muted">{{ $text }}</p>
    @endif
    @if (trim($slot) !== '')
        <div class="mt-3">{{ $slot }}</div>
    @endif
</div>
