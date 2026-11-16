<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeliveryStatus;
use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\NotificationDelivery;
use App\Push\PushNotificationProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Notification system health — counts only. Admins do not see the content of
 * customers' notifications, device tokens or push credentials.
 */
class NotificationController extends Controller
{
    public function index(\Illuminate\Http\Request $request): View
    {
        $filters = $request->validate([
            'type' => ['nullable', \Illuminate\Validation\Rule::enum(NotificationType::class)],
            'status' => ['nullable', 'in:read,unread'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        // One row per notification EVENT (a gold-rate update reaches many customers;
        // payment/KYC notifications reach one). Personal message content is not exposed.
        $events = DB::table('notifications')
            ->select([
                'type', 'dedupe_key',
                DB::raw('count(*) as recipients'),
                DB::raw('sum(case when read_at is not null then 1 else 0 end) as read_count'),
                DB::raw('max(created_at) as created_at'),
                DB::raw('min(id) as sample_id'),
                DB::raw('min(notifiable_id) as notifiable_id'),
            ])
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', $date))
            ->groupBy('type', 'dedupe_key')
            ->when(($filters['status'] ?? null) === 'unread', fn ($q) => $q->havingRaw('sum(case when read_at is null then 1 else 0 end) > 0'))
            ->when(($filters['status'] ?? null) === 'read', fn ($q) => $q->havingRaw('sum(case when read_at is null then 1 else 0 end) = 0'))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $samples = \Illuminate\Notifications\DatabaseNotification::query()->whereKey($events->pluck('sample_id'))->get(['id', 'data'])->keyBy('id');

        $notifications = DB::table('notifications');

        $byType = DB::table('notifications')
            ->select('type', DB::raw('count(*) as total'), DB::raw('sum(case when read_at is null then 1 else 0 end) as unread'), DB::raw('max(created_at) as latest'))
            ->groupBy('type')->get()->keyBy('type');

        $deliveries = NotificationDelivery::query()->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');

        $provider = app(PushNotificationProvider::class);

        return view('admin.notifications.index', [
            'events' => $events,
            'samples' => $samples,
            'filters' => $filters,
            'totals' => [
                'all' => (clone $notifications)->count(),
                'unread' => (clone $notifications)->whereNull('read_at')->count(),
                'today' => (clone $notifications)->where('created_at', '>=', today())->count(),
            ],
            'types' => collect(NotificationType::cases())->map(fn ($type) => [
                'type' => $type,
                'total' => (int) ($byType[$type->value]->total ?? 0),
                'unread' => (int) ($byType[$type->value]->unread ?? 0),
                'latest' => isset($byType[$type->value]) ? \Illuminate\Support\Carbon::parse($byType[$type->value]->latest) : null,
            ]),
            'deliveries' => collect(DeliveryStatus::cases())->mapWithKeys(fn ($status) => [$status->value => (int) ($deliveries[$status->value] ?? 0)]),
            'devices' => DeviceToken::query()->active()->select('platform', DB::raw('count(*) as total'))->groupBy('platform')->pluck('total', 'platform'),
            'provider' => ['name' => $provider->name(), 'configured' => $provider->isConfigured()],
            'pendingJobs' => DB::table(config('queue.connections.database.table', 'jobs'))->count(),
            'failedJobs' => DB::table(config('queue.failed.table', 'failed_jobs'))->count(),
        ]);
    }
}
