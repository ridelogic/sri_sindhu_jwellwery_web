<?php

namespace Database\Factories;

use App\Enums\CustomerPlanStatus;
use App\Models\CustomerPlan;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Amounts, scheme number and maturity date are filled in by the model
 * (snapshotted from the plan), so the factory only needs the relationships.
 *
 * @extends Factory<CustomerPlan>
 */
class CustomerPlanFactory extends Factory
{
    protected $model = CustomerPlan::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plan_id' => Plan::factory(),
            'start_date' => fake()->dateTimeBetween('-10 months', 'now')->format('Y-m-d'),
            'status' => CustomerPlanStatus::Active,
        ];
    }

    public function status(CustomerPlanStatus $status): static
    {
        return $this->state(['status' => $status]);
    }

    public function pending(): static
    {
        return $this->status(CustomerPlanStatus::Pending);
    }
}
