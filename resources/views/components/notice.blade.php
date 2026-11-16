@props(['theme' => 'light'])

{{-- Flash notice shown after a form submission (session "notice"). --}}
@if (session('notice'))
    <div {{ $attributes->class([
        'flex items-start gap-4 border-l-2 border-champagne px-5 py-4 text-sm leading-relaxed',
        'bg-ivory/5 text-ivory/85' => $theme === 'dark',
        'bg-parchment text-obsidian' => $theme !== 'dark',
    ]) }} role="status">
        <x-icon name="sparkle" class="mt-0.5 size-4 text-champagne" />
        <p>{{ session('notice') }}</p>
    </div>
@endif
