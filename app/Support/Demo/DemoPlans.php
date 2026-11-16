<?php

namespace App\Support\Demo;

/**
 * FALLBACK DEMO DATA — not real Sri Sindhu Jewellery plans.
 *
 * Used only (1) by GoldSavingPlanService when the plans table is empty or
 * unavailable, and (2) by the development seeder. Real plans live in the
 * `plans` table and always take precedence.
 */
final class DemoPlans
{
    /**
     * @return list<array{slug: string, name: string, tagline: string, description: string, monthly_amount: int, duration_months: int, is_featured: bool, sort_order: int}>
     */
    public static function all(): array
    {
        return [
            [
                'slug' => 'gold-basic',
                'name' => 'Gold Basic',
                'tagline' => 'A graceful first step into gold.',
                'description' => 'An accessible monthly plan for anyone beginning to save towards gold jewellery.',
                'monthly_amount' => 1000,
                'duration_months' => 11,
                'is_featured' => false,
                'sort_order' => 1,
            ],
            [
                'slug' => 'gold-plus',
                'name' => 'Gold Plus',
                'tagline' => 'Our most chosen saving plan.',
                'description' => 'A balanced monthly plan for everyday gold and special-occasion pieces.',
                'monthly_amount' => 2000,
                'duration_months' => 11,
                'is_featured' => true,
                'sort_order' => 2,
            ],
            [
                'slug' => 'gold-premium',
                'name' => 'Gold Premium',
                'tagline' => 'For grand jewellery moments.',
                'description' => 'A higher monthly plan for bridal sets and larger jewellery purchases.',
                'monthly_amount' => 5000,
                'duration_months' => 11,
                'is_featured' => false,
                'sort_order' => 3,
            ],
        ];
    }
}
