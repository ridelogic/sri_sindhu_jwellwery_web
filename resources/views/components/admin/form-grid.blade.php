{{-- Two equal columns on desktop/tablet (one column on phones). Use md:col-span-2 for full-width fields. --}}
<div {{ $attributes->class('grid gap-x-4 gap-y-4 md:grid-cols-2') }} data-form-grid>
    {{ $slot }}
</div>
