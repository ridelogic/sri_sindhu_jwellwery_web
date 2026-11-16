@props(['tone' => 'neutral'])

{{-- Compact status badge. Tones: success, warning, danger, info, gold, neutral. --}}
@php
    $tones = [
        'success' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        'warning' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'danger' => 'bg-red-50 text-red-800 ring-red-200',
        'info' => 'bg-sky-50 text-sky-800 ring-sky-200',
        'gold' => 'bg-ad-gold-soft/15 text-ad-gold ring-ad-gold-soft/45',
        'neutral' => 'bg-stone-100 text-stone-700 ring-stone-300',
    ];
@endphp

<span {{ $attributes->class('inline-flex items-center gap-1 rounded px-2 py-0.5 text-[0.7rem] font-bold whitespace-nowrap ring-1 '.($tones[$tone] ?? $tones['neutral'])) }}>{{ $slot }}</span>
