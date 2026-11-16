@props(['status' => null, 'variant' => 'portal'])

{{-- KYC status badge. A missing profile counts as "Not Submitted". Portal = square member style; admin = rounded pill. --}}
@php
    $status = $status instanceof \App\Enums\KycStatus ? $status : (\App\Enums\KycStatus::tryFrom((string) $status) ?? \App\Enums\KycStatus::Pending);
    $portal = [
        'not_submitted' => 'border-stone/60 text-taupe bg-parchment',
        'pending_review' => 'border-champagne/60 text-gold-deep bg-champagne/10',
        'approved' => 'border-emerald-300 text-emerald-800 bg-emerald-50',
        'rejected' => 'border-red-300 text-red-800 bg-red-50',
    ];
    // Admin: compact solid badges (colours in admin-theme.css).
    $classes = $variant === 'admin'
        ? 'admin-status admin-status-'.$status->key()
        : 'inline-flex items-center border px-2.5 py-1 text-[0.62rem] font-semibold tracking-[0.16em] whitespace-nowrap uppercase '.$portal[$status->key()];
@endphp

<span {{ $attributes->merge(['class' => $classes]) }} data-kyc-badge="{{ $status->key() }}">{{ $status->label() }}</span>
