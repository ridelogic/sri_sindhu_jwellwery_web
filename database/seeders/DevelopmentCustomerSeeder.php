<?php

namespace Database\Seeders;

use App\Enums\CustomerPlanStatus;
use App\Enums\KycStatus;
use App\Models\CustomerPlan;
use App\Models\KycProfile;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * DEVELOPMENT SAMPLE CUSTOMER — a fictional customer holding all three sample
 * plans at once, with a pending KYC profile. For local development only.
 *
 * Login (local only): mobile 9000000001 + OTP (SMS_DRIVER=development shows the OTP on screen).
 */
class DevelopmentCustomerSeeder extends Seeder
{
    public const EMAIL = 'demo.customer@example.test';

    public const MOBILE = '9000000001';

    public function run(): void
    {
        $customer = User::query()->firstOrCreate(
            ['email' => self::EMAIL],
            ['name' => 'Demo Customer (Development)', 'mobile_number' => self::MOBILE, 'password' => Str::password(64)], // customers sign in by OTP
        );

        if ($customer->customerPlans()->doesntExist()) {
            Plan::query()->active()->ordered()->get()->each(function (Plan $plan, int $index) use ($customer) {
                CustomerPlan::query()->create([
                    'user_id' => $customer->id,
                    'plan_id' => $plan->id,
                    'start_date' => today()->subMonths(3 - $index)->startOfMonth(),
                    'status' => $index === 0 ? CustomerPlanStatus::Active : CustomerPlanStatus::Pending,
                ]);
            });
        }

        KycProfile::query()->firstOrCreate(
            ['user_id' => $customer->id],
            [
                'city' => 'Chennai',
                'state' => 'Tamil Nadu',
                'country' => 'India',
                'kyc_status' => KycStatus::Pending,
                // No PAN / Aadhaar for the sample customer: never seed real-looking identity numbers.
            ],
        );
    }
}
