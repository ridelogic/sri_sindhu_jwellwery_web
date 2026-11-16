<?php

namespace Database\Factories;

use App\Enums\KycStatus;
use App\Models\KycProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Fake, syntactically valid KYC data for tests and local development only.
 *
 * @extends Factory<KycProfile>
 */
class KycProfileFactory extends Factory
{
    protected $model = KycProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date_of_birth' => fake()->dateTimeBetween('-70 years', '-18 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['female', 'male', 'other']),
            'address_line1' => fake()->buildingNumber().' '.fake()->streetName(),
            'address_line2' => null,
            'city' => fake()->randomElement(['Chennai', 'Coimbatore', 'Madurai', 'Tiruchirappalli', 'Salem']),
            'state' => 'Tamil Nadu',
            'pincode' => (string) fake()->numberBetween(600001, 643253),
            'country' => 'India',
            // Format: 5 letters, 4 digits, 1 letter (fake value).
            'pan_number' => Str::upper(fake()->lexify('?????')).fake()->numerify('####').Str::upper(fake()->lexify('?')),
            'aadhaar_last4' => fake()->numerify('####'),
            'kyc_status' => KycStatus::Pending,
            'verified_at' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(['kyc_status' => KycStatus::Submitted]);
    }

    public function verified(): static
    {
        return $this->state(['kyc_status' => KycStatus::Verified, 'verified_at' => now()]);
    }
}
