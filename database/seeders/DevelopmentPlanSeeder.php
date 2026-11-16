<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Support\Demo\DemoPlans;
use Illuminate\Database\Seeder;

/**
 * DEVELOPMENT SAMPLE PLANS — Gold Basic, Gold Plus, Gold Premium.
 * Values come from App\Support\Demo\DemoPlans (the same sample values the
 * website falls back to). Replace with the real plans before launch.
 */
class DevelopmentPlanSeeder extends Seeder
{
    public const TERMS_MARKER = 'DEVELOPMENT SAMPLE PLAN — not a real Sri Sindhu Jewellery offer. Replace with the final scheme terms before launch.';

    public function run(): void
    {
        foreach (DemoPlans::all() as $plan) {
            // updateOrCreate keyed on slug keeps the seeder idempotent.
            Plan::query()->updateOrCreate(['slug' => $plan['slug']], [
                'name' => $plan['name'],
                'tagline' => $plan['tagline'],
                'description' => $plan['description'],
                'monthly_amount' => $plan['monthly_amount'],
                'duration_months' => $plan['duration_months'],
                'benefits' => null, // null = use the scheme-wide benefits on the website
                'terms' => self::TERMS_MARKER,
                'is_featured' => $plan['is_featured'],
                'status' => true,
                'sort_order' => $plan['sort_order'],
            ]);
        }
    }
}
