@props(['notification', 'compact' => false])

{{-- One notification row. Unread rows are white with a gold edge and dot; read rows are muted. --}}
@php
    $type = \App\Enums\NotificationType::tryFrom($notification->type);
    $data = $notification->data;
    $unread = $notification->read_at === null;
    $tones = [
        'positive' => 'bg-emerald-50 text-emerald-700',
        'attention' => 'bg-red-50 text-red-700',
        'neutral' => 'bg-parchment text-gold-deep',
    ];
@endphp

<a href="{{ route('customer.notifications.show', $notification->id) }}"
   {{ $attributes->class([
       'group relative flex gap-4 border-l-2 px-4 py-4 transition-colors sm:px-5',
       'border-champagne bg-white hover:bg-parchment/40' => $unread,
       'border-transparent hover:bg-white/60' => ! $unread,
   ]) }}
   data-notification="{{ $notification->id }}" data-notification-state="{{ $unread ? 'unread' : 'read' }}">
    <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-full {{ $tones[$type?->tone() ?? 'neutral'] }}">
        <x-icon :name="$type?->icon() ?? 'bell'" class="size-[1.1rem]" />
    </span>
    <span class="min-w-0 flex-1">
        <span class="flex items-start justify-between gap-3">
            <span @class(['block text-sm leading-snug', 'font-bold text-obsidian' => $unread, 'font-semibold text-obsidian/80' => ! $unread])>{{ $data['title'] ?? $type?->label() }}</span>
            <time class="shrink-0 text-[0.7rem] text-taupe" datetime="{{ $notification->created_at?->toIso8601String() }}" title="{{ $notification->created_at?->format('d M Y, g:i A') }}">
                {{ $notification->created_at?->isToday() ? $notification->created_at->format('g:i A') : $notification->created_at?->format('d M') }}
            </time>
        </span>
        <span @class(['mt-1 block text-sm leading-relaxed', 'line-clamp-1' => $compact, 'line-clamp-2' => ! $compact, 'text-obsidian/80' => $unread, 'text-taupe' => ! $unread])>{{ $data['message'] ?? '' }}</span>
    </span>
    @if ($unread)
        <span class="absolute top-4 right-2 size-2 rounded-full bg-champagne sm:hidden" aria-hidden="true"></span>
        <span class="sr-only">Unread</span>
    @endif
</a>
