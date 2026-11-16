<?php

namespace Database\Seeders;

use App\Models\GoldRate;
use App\Support\Demo\DemoGoldRates;
use Illuminate\Database\Seeder;

/**
 * DEVELOPMENT SAMPLE GOLD RATES — 22K and 24K for the last several days.
 *
 * Every row is tagged source = "development-seed", which the website uses to
 * keep labelling these figures as preview rates. They are NOT real Sri Sindhu
 * Jewellery rates. Existing rows are never modified (rate history is
 * append-only); re-running the seeder only adds missing sample days.
 */
class DevelopmentGoldRateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (DemoGoldRates::rows() as $row) {
            // whereDate keeps the check portable (SQLite stores dates with a time part).
            $exists = GoldRate::query()
                ->whereDate('rate_date', $row['rate_date']->toDateString())
                ->forPurity($row['purity'])
                ->where('source', GoldRate::SOURCE_DEVELOPMENT)
                ->exists();

            if ($exists) {
                continue;
            }

            GoldRate::query()->create([
                'rate_date' => $row['rate_date']->toDateString(),
                'rate_time' => $row['rate_time'],
                'purity' => $row['purity'],
                'rate_per_gram' => $row['rate_per_gram'],
                'rate_per_10_gram' => $row['rate_per_gram'] * 10,
                'source' => GoldRate::SOURCE_DEVELOPMENT,
                'status' => true,
            ]);
        }
    }
}
