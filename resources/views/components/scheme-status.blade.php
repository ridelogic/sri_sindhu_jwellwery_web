@props(['status'])

{{-- Customer-friendly scheme status label. --}}
@php
    $value = $status instanceof \App\Enums\CustomerPlanStatus ? $status->value : (string) $status;
    [$label, $classes] = match ($value) {
        'pending' => ['Awaiting First Payment', 'border-champagne/60 text-gold-deep bg-champagne/10'],
        'active' => ['Active', 'border-emerald-300 text-emerald-800 bg-emerald-50'],
        'completed' => ['Completed', 'border-sky-300 text-sky-800 bg-sky-50'],
        'matured' => ['Matured', 'border-violet-300 text-violet-800 bg-violet-50'],
        'cancelled' => ['Cancelled', 'border-stone/60 text-taupe bg-parchment'],
        default => [ucfirst($value), 'border-line text-taupe'],
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center border px-2.5 py-1 text-[0.62rem] font-semibold tracking-[0.16em] uppercase $classes"]) }} data-scheme-status="{{ $value }}">{{ $label }}</span>
