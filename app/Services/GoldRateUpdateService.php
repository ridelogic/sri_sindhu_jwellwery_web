<?php

namespace App\Services;

use App\Enums\GoldPurity;
use App\Jobs\SendGoldRateNotifications;
use App\Models\GoldRate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Records a gold rate update (22K + 24K together) as NEW rows.
 *
 * Rules:
 *  - Existing rate values are never edited or deleted — only their status
 *    changes, which the GoldRate model allows.
 *  - 10-gram rates are always calculated here (per gram × 10); the browser's
 *    value is never trusted.
 *  - Within one rate date, exactly one record per purity is active: the one
 *    with the latest effective time (rate_date + rate_time). A normal update
 *    therefore becomes the active rate; a back-dated entry is kept as history
 *    without replacing a later rate.
 *  - Everything happens in one database transaction: if any step fails,
 *    nothing is written.
 */
class GoldRateUpdateService
{
    public const DEFAULT_SOURCE = 'Manual Admin Update';

    /**
     * @param  array<string, string|float>  $ratesPerGram  purity value ("22K"/"24K") => rate per gram
     * @return Collection<int, GoldRate> the newly created records
     */
    public function record(Carbon $date, string $time, array $ratesPerGram, ?string $source = null): Collection
    {
        $time = strlen($time) === 5 ? $time.':00' : $time;
        $source = filled($source) ? trim($source) : self::DEFAULT_SOURCE;

        $created = DB::transaction(function () use ($date, $time, $ratesPerGram, $source) {
            $records = collect();

            foreach (GoldPurity::cases() as $purity) {
                $perGram = round((float) $ratesPerGram[$purity->value], 2);

                $records->push(GoldRate::query()->create([
                    'rate_date' => $date->toDateString(),
                    'rate_time' => $time,
                    'purity' => $purity,
                    'rate_per_gram' => $perGram,
                    'rate_per_10_gram' => round($perGram * 10, 2), // server-side, never from the request
                    'source' => $source,
                    'status' => true,
                ]));

                $this->keepOnlyLatestActive($date, $purity);
            }

            return $records;
        });

        // Rates are cached per request; make sure the next read sees this update.
        app()->forgetInstance(GoldRateService::class);

        $fresh = $created->map->fresh();
        $this->notifyCustomers($fresh);

        return $fresh;
    }

    /**
     * One notification per update (22K + 24K together), queued and sent only
     * after the rates are committed — and only when this update is now the
     * current rate (a back-dated history entry is not announced). A failure
     * here never affects the saved rates.
     *
     * @param  Collection<int, GoldRate>  $rates
     */
    private function notifyCustomers(Collection $rates): void
    {
        $isCurrent = $rates->contains(fn (GoldRate $rate) => $rate->status && GoldRate::latestFor($rate->purity)?->is($rate));

        if ($isCurrent) {
            // afterCommit() contains failures even when the queue runs the job synchronously.
            $ids = $rates->pluck('id')->all();
            // A statement closure (not an arrow fn): PendingDispatch dispatches on destruction,
            // which must happen INSIDE the protected callback.
            app(NotificationService::class)->afterCommit(function () use ($ids) {
                SendGoldRateNotifications::dispatch($ids);
            });
        }
    }

    /**
     * For one date + purity, keep only the latest-effective ACTIVE record active
     * (the new record is active, so it competes; records an admin previously
     * deactivated are never revived). Status is the only column changed.
     */
    private function keepOnlyLatestActive(Carbon $date, GoldPurity $purity): void
    {
        $activeSameDay = fn () => GoldRate::query()
            ->whereDate('rate_date', $date->toDateString())
            ->forPurity($purity)
            ->active();

        $winnerId = $activeSameDay()->latestFirst()->value('id');

        $activeSameDay()->whereKeyNot($winnerId)->update(['status' => false, 'updated_at' => now()]);
    }
}
