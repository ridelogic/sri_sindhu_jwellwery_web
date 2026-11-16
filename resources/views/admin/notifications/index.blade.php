@extends('layouts.admin')

@section('title', 'Notifications')
@section('subtitle', 'Notifications sent to customers and the health of push delivery. Personal message content is never shown here.')

@php
    $statusStyles = [
        'queued' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'sent' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        'failed' => 'bg-red-50 text-red-800 ring-red-200',
        'not_configured' => 'bg-stone-100 text-stone-700 ring-stone-300',
    ];
@endphp

@section('content')
    <section aria-label="Totals" class="grid gap-4 sm:grid-cols-3">
        @foreach ([
            ['In-app notifications', $totals['all'], 'all'],
            ['Unread', $totals['unread'], 'unread'],
            ['Created today', $totals['today'], 'today'],
        ] as [$label, $value, $key])
            <x-admin.kpi-card :label="$label" :value="number_format($value)" :key="$key" icon="bell" />
        @endforeach
    </section>

    {{-- Notification list (one row per event) --}}
    @php
        $typeTones = ['positive' => 'success', 'attention' => 'danger', 'neutral' => 'gold'];
        $activeFilters = collect($filters)->filter(fn ($v) => filled($v))->isNotEmpty();
    @endphp
    <div class="mt-6">
        <x-admin.filter-bar :action="route('admin.notifications.index')" :reset="route('admin.notifications.index')" :active="$activeFilters">
            <label class="block">
                <span data-form-label class="mb-1.5 block">Type</span>
                <select name="type" class="block w-full px-3 py-2 text-sm">
                    <option value="">All types</option>
                    @foreach (\App\Enums\NotificationType::cases() as $type)
                        <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span data-form-label class="mb-1.5 block">Status</span>
                <select name="status" class="block w-full px-3 py-2 text-sm">
                    <option value="">All</option>
                    <option value="unread" @selected(($filters['status'] ?? '') === 'unread')>Has unread</option>
                    <option value="read" @selected(($filters['status'] ?? '') === 'read')>All read</option>
                </select>
            </label>
            <label class="block">
                <span data-form-label class="mb-1.5 block">Date</span>
                <input type="date" name="date" value="{{ $filters['date'] ?? '' }}" max="{{ today()->toDateString() }}" class="block w-full px-3 py-2 text-sm">
            </label>
        </x-admin.filter-bar>

        <x-admin.card :padded="false">
            @if ($events->isEmpty())
                <x-admin.empty-state icon="bell" :title="$activeFilters ? 'No notifications match these filters.' : 'No notifications yet.'" data-notifications-empty />
            @else
                <div class="overflow-x-auto" data-scroll-table>
                    <table class="w-full min-w-[60rem] text-left" data-table="notification-events">
                        <thead class="text-[0.7rem] uppercase">
                            <tr>
                                <th scope="col" class="px-3 font-semibold">Type</th>
                                <th scope="col" class="px-3 font-semibold">Title</th>
                                <th scope="col" class="px-3 font-semibold">Message</th>
                                <th scope="col" class="px-3 text-right font-semibold">Recipients</th>
                                <th scope="col" class="px-3 font-semibold">Status</th>
                                <th scope="col" class="px-3 font-semibold">Created</th>
                                <th scope="col" class="px-3 text-right font-semibold">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($events as $event)
                                @php
                                    $type = \App\Enums\NotificationType::tryFrom($event->type);
                                    $data = $samples[$event->sample_id]?->data ?? [];
                                    $broadcast = $type === \App\Enums\NotificationType::GoldRateUpdated;
                                    $allRead = (int) $event->read_count === (int) $event->recipients;
                                @endphp
                                <tr data-notification-event="{{ $event->type }}">
                                    <td class="px-3"><x-admin.badge :tone="$typeTones[$type?->tone() ?? 'neutral']">{{ $type?->label() ?? $event->type }}</x-admin.badge></td>
                                    <td class="px-3 font-semibold text-ad-ink">{{ $data['title'] ?? $type?->label() }}</td>
                                    <td class="max-w-[22rem] px-3 text-ad-ink">
                                        @if ($broadcast)
                                            {{ $data['message'] ?? '' }}
                                        @else
                                            <span class="text-xs text-ad-muted italic">Personal — content hidden</span>
                                        @endif
                                    </td>
                                    <td class="px-3 text-right tabular-nums">{{ number_format($event->recipients) }}</td>
                                    <td class="px-3"><x-admin.badge :tone="$allRead ? 'success' : 'warning'">{{ $allRead ? 'All read' : number_format($event->read_count).' of '.number_format($event->recipients).' read' }}</x-admin.badge></td>
                                    <td class="px-3 whitespace-nowrap text-ad-ink">{{ \Illuminate\Support\Carbon::parse($event->created_at)->format('d M Y, g:i A') }}</td>
                                    <td class="px-3 text-right">
                                        @if ($broadcast)
                                            <a href="{{ route('admin.gold-rates.index') }}" class="admin-action">Rates</a>
                                        @elseif ((int) $event->recipients === 1)
                                            <a href="{{ route('admin.customers.show', $event->notifiable_id) }}" class="admin-action">Customer</a>
                                        @else
                                            <span class="text-xs text-ad-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($events->hasPages())
                    <div class="border-t border-line px-4 py-3">{{ $events->links('admin.partials.pagination') }}</div>
                @endif
            @endif
        </x-admin.card>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="min-w-0 rounded-lg border border-line bg-white" aria-labelledby="types-heading">
            <h2 id="types-heading" class="border-b border-line px-5 py-4 font-sans text-[0.7rem] font-semibold tracking-[0.22em] text-taupe uppercase">By Type</h2>
            <div class="overflow-x-auto" data-scroll-table>
                <table class="w-full min-w-[34rem] text-left text-sm" data-table="notification-types">
                    <thead class="text-[0.68rem] tracking-[0.14em] text-taupe uppercase">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-semibold">Type</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">Total</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">Unread</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">Last Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($types as $row)
                            <tr data-type-row="{{ $row['type']->value }}">
                                <td class="px-5 py-3 font-medium">{{ $row['type']->label() }}</td>
                                <td class="px-5 py-3 text-right tabular-nums" data-type-total>{{ number_format($row['total']) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums text-taupe">{{ number_format($row['unread']) }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap text-taupe">{{ $row['latest']?->format('d M Y, g:i A') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="h-fit min-w-0 space-y-4">
            <section class="rounded-lg border border-line bg-white p-5" aria-labelledby="push-heading">
                <h2 id="push-heading" class="font-sans text-[0.7rem] font-semibold tracking-[0.22em] text-taupe uppercase">Push Delivery</h2>
                <p class="mt-3 flex items-center justify-between gap-3 text-sm">
                    <span class="text-taupe">Provider</span>
                    <span @class(['inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1', 'bg-emerald-50 text-emerald-800 ring-emerald-200' => $provider['configured'], 'bg-stone-100 text-stone-700 ring-stone-300' => ! $provider['configured']]) data-push-provider>
                        {{ strtoupper($provider['name']) }} · {{ $provider['configured'] ? 'Configured' : 'Not configured' }}
                    </span>
                </p>
                <dl class="mt-4 space-y-2 border-t border-line pt-4 text-sm" data-delivery-counts>
                    @foreach ($deliveries as $status => $count)
                        <div class="flex items-center justify-between gap-3">
                            <dt><span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $statusStyles[$status] }}">{{ \App\Enums\DeliveryStatus::from($status)->label() }}</span></dt>
                            <dd class="font-semibold tabular-nums" data-delivery="{{ $status }}">{{ number_format($count) }}</dd>
                        </div>
                    @endforeach
                </dl>
                @unless ($provider['configured'])
                    <p class="mt-4 text-xs leading-relaxed text-taupe">Push is not configured, so nothing is sent to phones. In-app notifications work normally.</p>
                @endunless
            </section>

            <section class="rounded-lg border border-line bg-white p-5" aria-labelledby="devices-heading">
                <h2 id="devices-heading" class="font-sans text-[0.7rem] font-semibold tracking-[0.22em] text-taupe uppercase">Active Devices</h2>
                <dl class="mt-4 space-y-2 text-sm">
                    @foreach (\App\Enums\DevicePlatform::cases() as $platform)
                        <div class="flex justify-between gap-3"><dt class="text-taupe">{{ $platform->label() }}</dt><dd class="font-semibold tabular-nums">{{ number_format($devices[$platform->value] ?? 0) }}</dd></div>
                    @endforeach
                </dl>
            </section>

            <section class="rounded-lg border border-line bg-white p-5" aria-labelledby="queue-heading">
                <h2 id="queue-heading" class="font-sans text-[0.7rem] font-semibold tracking-[0.22em] text-taupe uppercase">Queue</h2>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-taupe">Waiting jobs</dt><dd class="font-semibold tabular-nums" data-queue-pending>{{ number_format($pendingJobs) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-taupe">Failed jobs</dt><dd class="font-semibold tabular-nums" data-queue-failed>{{ number_format($failedJobs) }}</dd></div>
                </dl>
                <p class="mt-3 text-xs leading-relaxed text-taupe">Gold-rate notifications and push delivery run on the queue worker (<code class="font-mono">php artisan queue:work</code>).</p>
            </section>
        </aside>
    </div>
@endsection
