@props(['title', 'subtitle' => null, 'back' => null, 'backLabel' => 'Back'])

{{-- Compact page title row (list/detail pages): optional back button, title, subtitle, actions. --}}
@if ($back)
    <a href="{{ $back }}" class="admin-back mb-3" data-page-back>
        <x-icon name="arrow-right" class="size-3.5 rotate-180" /> {{ $backLabel }}
    </a>
@endif
<div {{ $attributes->class('mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between') }}>
    <div class="min-w-0">
        <h1 class="admin-page-title">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-0.5 max-w-3xl text-sm text-ad-muted">{{ $subtitle }}</p>
        @endif
    </div>
    @if (trim($slot) !== '')
        <div class="flex shrink-0 flex-wrap gap-2">{{ $slot }}</div>
    @endif
</div>
