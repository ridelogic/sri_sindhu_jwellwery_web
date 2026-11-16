<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        $name = 'Gold '.Str::title(fake()->unique()->word()).' '.fake()->unique()->numberBetween(1, 99999);
        $monthly = fake()->randomElement([500, 1000, 2000, 2500, 5000, 10000]);
        $months = fake()->randomElement([11, 12]);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'tagline' => fake()->sentence(5),
            'description' => fake()->sentence(14),
            'monthly_amount' => $monthly,
            'duration_months' => $months,
            'total_amount' => $monthly * $months,
            'benefits' => [
                ['icon' => 'calendar', 'title' => 'Fixed Monthly Contribution', 'text' => 'Save the same amount every month.'],
            ],
            'terms' => null,
            'is_featured' => false,
            'status' => true,
            'sort_order' => fake()->numberBetween(1, 50),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => false]);
    }

    public function featured(): static
    {
        return $this->state(['is_featured' => true]);
    }
}
