<?php

namespace App\Services;

use App\Enums\GoldPurity;
use App\Models\GoldRate;
use App\Support\Demo\DemoGoldRates;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Gold rate data for the public website.
 *
 * Source of truth:
 *   1. DATABASE DATA — active rows in the `gold_rates` table (append-only history).
 *   2. FALLBACK DEMO DATA — App\Support\Demo\DemoGoldRates, used ONLY when the
 *      table has no active rates or cannot be reached.
 *
 * Both sources are normalised into the same row shape, so the views never
 * know which one is in use. isSample() is true for fallback data AND for
 * database rows tagged as development seed data, so the website keeps
 * labelling non-production figures as "preview".
 */
class GoldRateService
{
    /** Number of days shown in the public rate history. */
    public const HISTORY_DAYS = 11;

    /** @var Collection<int, array{date: Carbon, time: string, purity: string, rate: float, rate_10g: float, source: ?string}>|null */
    private ?Collection $rows = null;

    private bool $usingFallback = false;

    /**
     * The latest active GoldRate record for a purity — DATABASE ONLY (no fallback).
     * "Latest" is the business-effective moment rate_date + rate_time, not created_at.
     */
    public function latestForPurity(GoldPurity|string $purity): ?GoldRate
    {
        try {
            return GoldRate::latestFor($purity);
        } catch (QueryException $e) {
            Log::warning('GoldRateService: gold_rates unavailable.', ['code' => $e->getCode()]);

            return null;
        }
    }

    public function latest22K(): ?GoldRate
    {
        return $this->latestForPurity(GoldPurity::K22);
    }

    public function latest24K(): ?GoldRate
    {
        return $this->latestForPurity(GoldPurity::K24);
    }

    /**
     * Today's current rate record per purity — DATABASE ONLY (no fallback).
     * Used by the admin area, which must never show demo data.
     *
     * @return array<string, GoldRate|null> keyed by purity ("22K", "24K")
     */
    public function today(): array
    {
        return collect(GoldPurity::cases())
            ->mapWithKeys(fn (GoldPurity $purity) => [$purity->value => $this->latestForPurity($purity)])
            ->all();
    }

    /**
     * Admin rate history: one row per rate update (date + time), newest first,
     * with the 22K and 24K records of that update side by side.
     *
     * @return LengthAwarePaginator<int, array{date: Carbon, time: string, rates: array<string, GoldRate|null>, source: ?string, active: bool}>
     */
    public function updates(?string $from = null, ?string $to = null, int $perPage = 20): LengthAwarePaginator
    {
        $page = GoldRate::query()
            ->select(['rate_date', 'rate_time'])
            ->selectRaw('MAX(id) as last_id')
            ->when($from, fn ($q) => $q->whereDate('rate_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('rate_date', '<=', $to))
            ->groupBy('rate_date', 'rate_time')
            ->orderByDesc('rate_date')->orderByDesc('rate_time')->orderByDesc('last_id')
            ->paginate($perPage)
            ->withQueryString();

        $keys = $page->getCollection();

        $records = $keys->isEmpty() ? collect() : GoldRate::query()
            ->where(function ($query) use ($keys) {
                foreach ($keys as $key) {
                    $query->orWhere(fn ($q) => $q->whereDate('rate_date', $key->rate_date->toDateString())
                        ->where(fn ($t) => $key->rate_time === null ? $t->whereNull('rate_time') : $t->where('rate_time', $key->rate_time)));
                }
            })
            ->orderBy('id')
            ->get();

        return $page->setCollection($keys->map(function (GoldRate $key) use ($records) {
            $group = $records->filter(fn (GoldRate $r) => $r->rate_date->isSameDay($key->rate_date) && $r->rate_time === $key->rate_time);
            $byPurity = $group->keyBy(fn (GoldRate $r) => $r->purity->value); // last record per purity wins

            return [
                'date' => $key->rate_date,
                'time' => $key->rate_time,
                'rates' => collect(GoldPurity::cases())->mapWithKeys(fn (GoldPurity $p) => [$p->value => $byPurity->get($p->value)])->all(),
                'source' => $group->last()?->source,
                'active' => $group->contains(fn (GoldRate $r) => $r->status),
            ];
        }));
    }

    public function isUsingFallback(): bool
    {
        $this->rows();

        return $this->usingFallback;
    }

    /**
     * True when the figures shown are not real published rates
     * (fallback demo data or development seed rows).
     */
    public function isSample(): bool
    {
        if ($this->isUsingFallback()) {
            return true;
        }

        return $this->latestPerPurity()->contains(fn (array $row) => $row['source'] === GoldRate::SOURCE_DEVELOPMENT);
    }

    public function updatedAt(): Carbon
    {
        $latest = $this->latestPerPurity()->max(fn (array $row) => $row['date']->copy()->setTimeFromTimeString($row['time']));

        return $latest ?? Carbon::now();
    }

    /**
     * Today's rates with comparison against the previous rate.
     *
     * @return list<array{purity: string, label: string, description: string, price_per_gram: float, price_per_10_gram: float, previous_price_per_gram: float, change: float, change_percent: float}>
     */
    public function current(): array
    {
        return $this->latestPerPurity()->map(function (array $latest) {
            $previous = $this->rows()->where('purity', $latest['purity'])->slice(-2, 1)->first() ?? $latest;
            $purity = GoldPurity::from($latest['purity']);

            return [
                'purity' => $purity->value,
                'label' => $purity->label(),
                'description' => $purity->description(),
                'price_per_gram' => $latest['rate'],
                'price_per_10_gram' => $latest['rate_10g'],
                'previous_price_per_gram' => $previous['rate'],
                'change' => round($latest['rate'] - $previous['rate'], 2),
                'change_percent' => $previous['rate'] > 0 ? round(($latest['rate'] - $previous['rate']) / $previous['rate'] * 100, 2) : 0.0,
            ];
        })->values()->all();
    }

    /**
     * Daily history, newest first. Uses the last rate of each day; only days
     * with a rate for every displayed purity are included.
     *
     * @return list<array{date: Carbon, rates: array<string, float>}>
     */
    public function history(int $days = self::HISTORY_DAYS): array
    {
        $purities = $this->latestPerPurity()->keys();

        return $this->rows()
            ->groupBy(fn (array $row) => $row['date']->toDateString())
            ->map(fn (Collection $dayRows, string $date) => [
                'date' => Carbon::parse($date),
                // Rows are chronological, so keyBy keeps the day's last rate per purity.
                'rates' => $dayRows->keyBy('purity')->map(fn (array $row) => $row['rate'])->all(),
            ])
            ->filter(fn (array $day) => $purities->every(fn (string $purity) => isset($day['rates'][$purity])))
            ->sortByDesc(fn (array $day) => $day['date']->timestamp)
            ->take($days)
            ->values()
            ->all();
    }

    /**
     * The compact structure used by the homepage (hero strip and rate section).
     */
    public function summary(): array
    {
        return [
            'updated_at' => $this->updatedAt(),
            'is_sample' => $this->isSample(),
            'history_url' => route('gold.rate'),
            'background' => 'images/home/rate-gold-bars.jpg',
            'rates' => $this->current(),
        ];
    }

    /**
     * Latest row per purity, in GoldPurity order.
     *
     * @return Collection<string, array>
     */
    private function latestPerPurity(): Collection
    {
        $latest = $this->rows()->groupBy('purity')->map(fn (Collection $rows) => $rows->last());

        return collect(GoldPurity::cases())
            ->map(fn (GoldPurity $purity) => $purity->value)
            ->filter(fn (string $purity) => $latest->has($purity))
            ->mapWithKeys(fn (string $purity) => [$purity => $latest[$purity]]);
    }

    /**
     * Chronological rows (oldest first) from the database, or the fallback.
     */
    private function rows(): Collection
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $rows = $this->databaseRows();

        if ($rows->isEmpty()) {
            $this->usingFallback = true;
            $rows = $this->fallbackRows();
        }

        return $this->rows = $rows;
    }

    /**
     * DATABASE DATA: active rates from the last few weeks (enough to cover the
     * displayed history and the previous rate).
     */
    private function databaseRows(): Collection
    {
        try {
            $latestDate = GoldRate::query()->active()->max('rate_date');

            if ($latestDate === null) {
                return collect();
            }

            return GoldRate::query()
                ->active()
                ->whereDate('rate_date', '>=', Carbon::parse($latestDate)->subDays(self::HISTORY_DAYS * 3))
                ->orderBy('rate_date')->orderBy('rate_time')->orderBy('id')
                ->get()
                ->map(fn (GoldRate $rate) => [
                    'date' => $rate->rate_date->copy()->startOfDay(),
                    'time' => $rate->rate_time ?? '00:00:00',
                    'purity' => $rate->purity->value,
                    'rate' => (float) $rate->rate_per_gram,
                    'rate_10g' => (float) $rate->rate_per_10_gram,
                    'source' => $rate->source,
                ]);
        } catch (QueryException $e) {
            // Table missing or database unreachable: fall back rather than break public pages.
            Log::warning('GoldRateService: gold_rates unavailable, using fallback demo data.', ['code' => $e->getCode()]);

            return collect();
        }
    }

    /**
     * FALLBACK DEMO DATA: clearly-marked sample rates (see DemoGoldRates).
     */
    private function fallbackRows(): Collection
    {
        return collect(DemoGoldRates::rows())->map(fn (array $row) => [
            'date' => $row['rate_date'],
            'time' => $row['rate_time'],
            'purity' => $row['purity'],
            'rate' => (float) $row['rate_per_gram'],
            'rate_10g' => (float) $row['rate_per_gram'] * 10,
            'source' => 'fallback-demo',
        ]);
    }
}
