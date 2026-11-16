<?php

namespace App\Jobs;

use App\Models\GoldRate;
use App\Models\User;
use App\Notifications\NotificationMessages;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Fans a gold-rate update out to every customer — in the queue, in chunks,
 * never inside the admin's HTTP request. One notification per update (22K and
 * 24K together). Unique per update and deduplicated per customer, so a
 * repeated dispatch or retry never notifies anyone twice.
 */
class SendGoldRateNotifications implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    /** @param  list<int>  $rateIds  the rows created by one rate update */
    public function __construct(public array $rateIds) {}

    public function uniqueId(): string
    {
        return collect($this->rateIds)->sort()->implode('-');
    }

    public function handle(NotificationService $notifications): void
    {
        $rates = GoldRate::query()->whereKey($this->rateIds)->get();

        if ($rates->isEmpty()) {
            return;
        }

        $notifications->notifyMany(User::query()->customers(), NotificationMessages::goldRateUpdated($rates));
    }
}
