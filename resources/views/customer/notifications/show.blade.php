@extends('layouts.app')

@section('title', 'Notification | Sri Sindhu Jewellery')
@section('meta_description', 'Your Sri Sindhu Jewellery notification.')
@section('robots', 'noindex, nofollow')

@php
    $type = \App\Enums\NotificationType::tryFrom($notification->type);
    $data = $notification->data;
    $tones = [
        'positive' => 'bg-emerald-50 text-emerald-700',
        'attention' => 'bg-red-50 text-red-700',
        'neutral' => 'bg-parchment text-gold-deep',
    ];
    $cta = match ($type?->preference()) {
        \App\Enums\NotificationPreferenceKey::GoldRateUpdates => 'View Gold Rates',
        \App\Enums\NotificationPreferenceKey::KycUpdates => 'View KYC',
        default => 'View Payment',
    };
@endphp

@section('content')
    <x-customer-shell title="Notifications" emphasis="Notifications">
        <div class="mx-auto max-w-2xl">
            <a href="{{ route('customer.notifications.index') }}" class="link-line text-obsidian">
                <x-icon name="arrow-right" class="size-3.5 rotate-180" />
                All Notifications
            </a>

            <article class="mt-6 border border-line bg-white" data-notification-detail="{{ $notification->type }}">
                <header class="flex gap-4 border-b border-line px-6 py-6">
                    <span class="inline-flex size-12 shrink-0 items-center justify-center rounded-full {{ $tones[$type?->tone() ?? 'neutral'] }}">
                        <x-icon :name="$type?->icon() ?? 'bell'" class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-[1.75rem] leading-tight">{{ $data['title'] ?? $type?->label() }}</h2>
                        <p class="mt-1 text-xs text-taupe"><time datetime="{{ $notification->created_at?->toIso8601String() }}">{{ $notification->created_at?->format('d M Y, g:i A') }}</time></p>
                    </div>
                </header>

                <div class="px-6 py-6">
                    <p class="leading-relaxed" data-notification-message>{{ $data['message'] ?? '' }}</p>

                    @if (! empty($data['details']))
                        <dl class="mt-5 divide-y divide-line border-y border-line text-sm" data-notification-details>
                            @foreach ($data['details'] as $label => $value)
                                <div class="flex justify-between gap-4 py-3">
                                    <dt class="text-taupe">{{ $label }}</dt>
                                    <dd class="text-right font-semibold break-words tabular-nums">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif

                    @if (! empty($data['url']))
                        <a href="{{ url($data['url']) }}" class="btn btn-dark mt-6 w-full sm:w-auto">{{ $cta }} <x-icon name="arrow-right" class="size-4" /></a>
                    @endif
                </div>
            </article>
        </div>
    </x-customer-shell>
@endsection
