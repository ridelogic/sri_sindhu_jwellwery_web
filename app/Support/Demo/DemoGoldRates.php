<?php

namespace App\Support\Demo;

use Illuminate\Support\Carbon;

/**
 * FALLBACK DEMO DATA — sample gold rates, NOT real Sri Sindhu Jewellery rates.
 *
 * Used only (1) by GoldRateService when the gold_rates table has no active
 * rates or is unavailable, and (2) by the development seeder. Real rates live
 * in the `gold_rates` table and always take precedence.
 */
final class DemoGoldRates
{
    /**
     * Daily per-gram sample rates, oldest first; the last value is "today".
     *
     * @var array<string, list<int>>
     */
    public const SERIES = [
        '22K' => [6890, 6905, 6880, 6920, 6945, 6930, 6960, 6985, 6970, 6950, 7000],
        '24K' => [7520, 7535, 7510, 7550, 7580, 7565, 7595, 7620, 7605, 7595, 7650],
    ];

    public const UPDATE_TIME = '09:30:00';

    /**
     * Rows shaped like gold_rates records, oldest first, ending today.
     *
     * @return list<array{rate_date: Carbon, rate_time: string, purity: string, rate_per_gram: int}>
     */
    public static function rows(?Carbon $today = null): array
    {
        $today ??= Carbon::today();
        $days = count(self::SERIES['22K']);
        $rows = [];

        for ($i = 0; $i < $days; $i++) {
            foreach (self::SERIES as $purity => $series) {
                $rows[] = [
                    'rate_date' => $today->copy()->subDays($days - 1 - $i),
                    'rate_time' => self::UPDATE_TIME,
                    'purity' => $purity,
                    'rate_per_gram' => $series[$i],
                ];
            }
        }

        return $rows;
    }
}
