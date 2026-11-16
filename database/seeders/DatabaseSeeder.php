<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * DEVELOPMENT DATA ONLY.
 *
 * Everything seeded here is sample data for local development and testing.
 * None of it represents real Sri Sindhu Jewellery plans, rates or customers,
 * so the seeder refuses to run in production.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('DatabaseSeeder contains development sample data and will not run in production.');

            return;
        }

        $this->call([
            DevelopmentAdminSeeder::class,
            DevelopmentPlanSeeder::class,
            DevelopmentGoldRateSeeder::class,
            DevelopmentCustomerSeeder::class,
        ]);
    }
}
