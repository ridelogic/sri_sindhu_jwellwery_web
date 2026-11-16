@props(['title', 'back' => null, 'backLabel' => 'Back'])

{{--
    Business form pattern: compact "← Back" button top-left, then a wide card with a
    full-width header band (title left), a compact body and actions bottom-right.
    Wrap it in the page's own <form> so existing data-* hooks stay on the form.
--}}
@if ($back)
    <a href="{{ $back }}" class="admin-back mb-3" data-page-back>
        <x-icon name="arrow-right" class="size-3.5 rotate-180" /> {{ $backLabel }}
    </a>
@endif

<section {{ $attributes->class('admin-card overflow-hidden') }} aria-labelledby="form-card-title" data-form-card>
    <header class="flex min-h-[2.65rem] items-center border-b border-ad-line bg-ad-panel px-4">
        <h1 id="form-card-title" class="text-[0.98rem] leading-tight font-semibold text-ad-ink">{{ $title }}</h1>
    </header>

    <div class="p-4">
        {{ $slot }}
    </div>

    @isset($actions)
        <footer class="flex flex-col-reverse gap-2 px-4 pb-4 sm:flex-row sm:justify-end" data-form-actions>
            {{ $actions }}
        </footer>
    @endisset
</section>
