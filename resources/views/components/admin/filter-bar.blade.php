@props(['action', 'reset' => null, 'active' => false])

{{-- GET filter row: fields side by side, then Filter / Reset. --}}
<form method="GET" action="{{ $action }}" {{ $attributes->class('admin-card mb-3 p-3') }} role="search" data-filter-bar>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[repeat(auto-fit,minmax(10.5rem,1fr))] lg:items-end">
        {{ $slot }}
        <div class="flex gap-2 sm:col-span-2 lg:col-span-1">
            <button type="submit" class="btn btn-primary flex-1 lg:flex-none">Filter</button>
            @if ($reset)
                <a href="{{ $reset }}" @class(['btn btn-secondary flex-1 lg:flex-none', 'opacity-60' => ! $active])>Reset</a>
            @endif
        </div>
    </div>
</form>
