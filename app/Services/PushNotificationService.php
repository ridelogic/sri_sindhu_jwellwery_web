<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Jobs\SendPushNotification;
use App\Models\DeviceToken;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Push\PushMessage;
use App\Push\PushNotificationProvider;
use App\Push\PushResult;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Push delivery, independent of any one provider.
 *
 * sendToUser() records one delivery per active device and QUEUES the actual
 * sending; with no provider configured the deliveries are recorded as
 * "not_configured" and nothing is queued. Tokens and credentials are never
 * written to delivery records or logs.
 */
class PushNotificationService
{
    public function provider(): PushNotificationProvider
    {
        return app(PushNotificationProvider::class);
    }

    /** @return Collection<int, NotificationDelivery> */
    public function sendToUser(User $user, PushMessage $message, ?string $notificationId = null): Collection
    {
        $tokens = $user->deviceTokens()->active()->get(['id', 'user_id']);
        if ($tokens->isEmpty()) {
            return collect();
        }

        $provider = $this->provider();
        $configured = $provider->isConfigured();

        $deliveries = $tokens->map(fn (DeviceToken $token) => NotificationDelivery::query()->firstOrCreate(
            ['notification_id' => $notificationId, 'device_token_id' => $token->id],
            [
                'user_id' => $user->id,
                'channel' => 'push',
                'provider' => $provider->name(),
                'status' => $configured ? DeliveryStatus::Queued : DeliveryStatus::NotConfigured,
                'error' => $configured ? null : 'Push provider is not configured.',
            ],
        ));

        $queued = $deliveries->filter(fn ($d) => $d->wasRecentlyCreated && $d->status === DeliveryStatus::Queued);
        if ($queued->isNotEmpty()) {
            SendPushNotification::dispatch($queued->pluck('id')->all(), $message)->afterCommit();
        }

        return $deliveries;
    }

    /**
     * @param  iterable<User>  $users
     * @return int deliveries recorded
     */
    public function sendToUsers(iterable $users, PushMessage $message): int
    {
        $count = 0;
        foreach ($users as $user) {
            $count += $this->sendToUser($user, $message)->count();
        }

        return $count;
    }

    /** Send immediately to one device. Never throws; invalid tokens are deactivated. */
    public function sendToToken(DeviceToken $token, PushMessage $message): PushResult
    {
        if (! $token->is_active) {
            return PushResult::failed('Device is no longer active.');
        }

        try {
            $result = $this->provider()->send($token->token, $message);
        } catch (Throwable) {
            $result = PushResult::failed('Delivery failed.');
        }

        if ($result->invalidToken) {
            $token->forceFill(['is_active' => false])->save();
        }

        return $result;
    }

    /** Process one queued delivery (called by the job). */
    public function deliver(NotificationDelivery $delivery, PushMessage $message): void
    {
        if ($delivery->status !== DeliveryStatus::Queued) {
            return; // already processed — a retried job never sends twice
        }

        $token = $delivery->deviceToken;
        $result = $token ? $this->sendToToken($token, $message) : PushResult::failed('Device was removed.');

        $delivery->forceFill([
            'status' => $result->status,
            'error' => $result->error,
            'attempted_at' => now(),
        ])->save();
    }
}
