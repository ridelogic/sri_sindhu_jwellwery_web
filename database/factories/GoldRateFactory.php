<?php

namespace Database\Factories;

use App\Enums\GoldPurity;
use App\Models\GoldRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoldRate>
 */
class GoldRateFactory extends Factory
{
    protected $model = GoldRate::class;

    public function definition(): array
    {
        $rate = fake()->numberBetween(6500, 7500);

        return [
            'rate_date' => today()->toDateString(),
            'rate_time' => '09:30:00',
            'purity' => GoldPurity::K22,
            'rate_per_gram' => $rate,
            'rate_per_10_gram' => $rate * 10,
            'source' => 'manual',
            'status' => true,
        ];
    }

    public function purity(GoldPurity $purity): static
    {
        return $this->state(['purity' => $purity]);
    }

    public function rate(float $perGram): static
    {
        return $this->state(['rate_per_gram' => $perGram, 'rate_per_10_gram' => $perGram * 10]);
    }

    public function on(string $date, ?string $time = '09:30:00'): static
    {
        return $this->state(['rate_date' => $date, 'rate_time' => $time]);
    }

    public function inactive(): static
    {
        return $this->state(['status' => false]);
    }

    public function development(): static
    {
        return $this->state(['source' => GoldRate::SOURCE_DEVELOPMENT]);
    }
}
