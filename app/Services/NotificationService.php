<?php

namespace App\Services;

use App\Enums\NotificationPreferenceKey;
use App\Enums\NotificationType;
use App\Models\DeviceToken;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\NotificationMessage;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * In-app notifications (Laravel's notifications table) + push hand-off.
 *
 *  - Preferences are respected per notification type.
 *  - Rows are inserted with INSERT … IGNORE against a unique
 *    (notifiable, dedupe_key) index: a repeated event never notifies twice.
 *  - Business code calls afterCommit(): the notification is created only
 *    once the KYC / payment / rate transaction has committed, and any
 *    notification or push failure is caught and reported — it can never roll
 *    back or break the operation that triggered it.
 */
class NotificationService
{
    public const BULK_CHUNK = 500;

    public function __construct(private PushNotificationService $push) {}

    /** Run $callback after the current transaction commits; failures never propagate. */
    public function afterCommit(Closure $callback): void
    {
        DB::afterCommit(fn () => rescue($callback, null, true));
    }

    public function notify(User $user, NotificationMessage $message): ?DatabaseNotification
    {
        if ($user->isAdmin() || ! $this->allows($user, $message->type)) {
            return null;
        }

        $id = (string) Str::uuid();
        $inserted = DB::table('notifications')->insertOrIgnore($this->row($id, $user->id, $message));

        if ($inserted === 0) {
            return null; // this event was already notified to this user
        }

        rescue(fn () => $this->push->sendToUser($user, $message->toPush($id), $id), null, true);

        return DatabaseNotification::query()->find($id);
    }

    /**
     * Fan-out to many customers in chunks (for queued jobs, never an HTTP request).
     *
     * @return int notifications created
     */
    public function notifyMany(Builder $users, NotificationMessage $message, int $chunk = self::BULK_CHUNK): int
    {
        $created = 0;
        $column = $message->type->preference()->value;

        $users->select(['users.id', 'users.is_admin'])->where('users.is_admin', false)
            ->chunkById($chunk, function (Collection $batch) use ($message, $column, &$created) {
                $optedOut = NotificationPreference::query()->whereIn('user_id', $batch->pluck('id'))
                    ->where($column, false)->pluck('user_id')->flip();

                $rows = $batch->reject(fn (User $user) => $optedOut->has($user->id))
                    ->mapWithKeys(fn (User $user) => [(string) Str::uuid() => $user->id]);

                if ($rows->isEmpty()) {
                    return;
                }

                DB::table('notifications')->insertOrIgnore($rows->map(fn ($userId, $id) => $this->row($id, $userId, $message))->values()->all());

                // Which of OUR ids were inserted (the rest were duplicates of an earlier run).
                $new = DB::table('notifications')->whereIn('id', $rows->keys())->pluck('notifiable_id', 'id');
                $created += $new->count();

                $withDevices = DeviceToken::query()->active()->whereIn('user_id', $new->values())->distinct()->pluck('user_id')->flip();
                foreach ($new as $id => $userId) {
                    if ($withDevices->has($userId)) {
                        rescue(fn () => $this->push->sendToUser(User::query()->find($userId), $message->toPush($id), $id), null, true);
                    }
                }
            }, 'users.id', 'id');

        return $created;
    }

    public function allows(User $user, NotificationType $type): bool
    {
        return $this->preferencesFor($user)[$type->preference()->value];
    }

    /** @return array<string, bool> */
    public function preferencesFor(User $user): array
    {
        $row = NotificationPreference::query()->where('user_id', $user->id)->first();

        return array_merge(NotificationPreference::defaults(), $row ? collect(NotificationPreferenceKey::cases())
            ->mapWithKeys(fn ($key) => [$key->value => (bool) $row->{$key->value}])->all() : []);
    }

    /** @param  array<string, bool>  $values */
    public function updatePreferences(User $user, array $values): NotificationPreference
    {
        return NotificationPreference::query()->updateOrCreate(
            ['user_id' => $user->id],
            collect(NotificationPreferenceKey::cases())->mapWithKeys(fn ($key) => [$key->value => (bool) ($values[$key->value] ?? false)])->all(),
        );
    }

    private function row(string $id, int $userId, NotificationMessage $message): array
    {
        $now = now();

        return [
            'id' => $id,
            'type' => $message->type->value,
            'notifiable_type' => (new User)->getMorphClass(),
            'notifiable_id' => $userId,
            'data' => json_encode($message->payload(), JSON_UNESCAPED_UNICODE),
            'dedupe_key' => $message->key,
            'read_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
