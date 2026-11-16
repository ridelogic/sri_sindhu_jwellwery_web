@props(['status', 'variant' => 'portal'])

{{-- Payment status badge. Portal = square member style; admin = rounded pill. --}}
@php
    $status = $status instanceof \App\Enums\PaymentStatus ? $status : \App\Enums\PaymentStatus::from((string) $status);
    $portal = [
        'created' => 'border-stone/60 text-taupe bg-parchment',
        'pending' => 'border-champagne/60 text-gold-deep bg-champagne/10',
        'success' => 'border-emerald-300 text-emerald-800 bg-emerald-50',
        'failed' => 'border-red-300 text-red-800 bg-red-50',
        'cancelled' => 'border-stone/60 text-taupe bg-parchment',
    ];
    $admin = [
        'created' => 'bg-stone-100 text-stone-700 ring-stone-300',
        'pending' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'success' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        'failed' => 'bg-red-50 text-red-800 ring-red-200',
        'cancelled' => 'bg-stone-100 text-stone-600 ring-stone-300',
    ];
    $classes = $variant === 'admin'
        ? 'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap ring-1 '.$admin[$status->value]
        : 'inline-flex items-center border px-2.5 py-1 text-[0.62rem] font-semibold tracking-[0.16em] whitespace-nowrap uppercase '.$portal[$status->value];
@endphp

<span {{ $attributes->merge(['class' => $classes]) }} data-payment-status="{{ $status->value }}">{{ $status->label() }}</span>
