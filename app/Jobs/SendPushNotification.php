<?php

namespace App\Jobs;

use App\Models\NotificationDelivery;
use App\Push\PushMessage;
use App\Services\PushNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends one notification to a user's devices. Each delivery is processed at
 * most once (only "queued" rows are sent), so retries never double-send.
 */
class SendPushNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @param  list<int>  $deliveryIds */
    public function __construct(public array $deliveryIds, public PushMessage $message) {}

    public function handle(PushNotificationService $push): void
    {
        NotificationDelivery::query()->with('deviceToken')->whereKey($this->deliveryIds)->get()
            ->each(fn (NotificationDelivery $delivery) => $push->deliver($delivery, $this->message));
    }
}
