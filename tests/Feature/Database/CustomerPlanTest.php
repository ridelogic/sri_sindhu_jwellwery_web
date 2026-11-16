<?php

namespace Tests\Feature\Database;

use App\Enums\CustomerPlanStatus;
use App\Models\CustomerPlan;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_single_user_can_hold_three_plans_at_the_same_time(): void
    {
        $user = User::factory()->create();
        $plans = collect([
            Plan::factory()->create(['slug' => 'gold-basic', 'monthly_amount' => 1000]),
            Plan::factory()->create(['slug' => 'gold-plus', 'monthly_amount' => 2000]),
            Plan::factory()->create(['slug' => 'gold-premium', 'monthly_amount' => 5000]),
        ]);

        foreach ($plans as $plan) {
            CustomerPlan::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'status' => CustomerPlanStatus::Active]);
        }

        $user->refresh();

        $this->assertCount(3, $user->customerPlans);
        $this->assertCount(3, $user->plans);
        $this->assertEqualsCanonicalizing(['gold-basic', 'gold-plus', 'gold-premium'], $user->plans->pluck('slug')->all());
        $this->assertSame(3, $user->customerPlans()->open()->count());
    }

    public function test_a_user_can_join_the_same_plan_more_than_once(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create();

        CustomerPlan::factory()->count(2)->for($user)->for($plan)->create();

        $this->assertSame(2, $user->customerPlans()->where('plan_id', $plan->id)->count());
    }

    public function test_users_table_has_no_plan_id_column(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'plan_id'));
    }

    public function test_customer_plans_belong_to_the_correct_user(): void
    {
        [$asha, $ravi] = User::factory()->count(2)->create();

        CustomerPlan::factory()->count(2)->for($asha)->create();
        CustomerPlan::factory()->count(1)->for($ravi)->create();

        $this->assertCount(2, $asha->customerPlans);
        $this->assertCount(1, $ravi->customerPlans);
        $this->assertTrue($asha->customerPlans->every(fn (CustomerPlan $cp) => $cp->user->is($asha)));
        $this->assertTrue($ravi->customerPlans->first()->user->is($ravi));
    }

    public function test_enrolment_snapshots_plan_terms_and_computes_dates(): void
    {
        $plan = Plan::factory()->create(['monthly_amount' => 2000, 'duration_months' => 11]);

        $customerPlan = CustomerPlan::create([
            'user_id' => User::factory()->create()->id,
            'plan_id' => $plan->id,
            'start_date' => '2026-01-15',
        ])->fresh();

        $this->assertSame('2000.00', $customerPlan->monthly_amount);
        $this->assertSame(11, $customerPlan->duration_months);
        $this->assertSame('22000.00', $customerPlan->total_commitment);
        $this->assertSame('2026-12-15', $customerPlan->maturity_date->toDateString());
        $this->assertSame(CustomerPlanStatus::Pending, $customerPlan->status);
        $this->assertTrue($customerPlan->plan->is($plan));

        // Later plan changes must not alter an existing commitment.
        $plan->update(['monthly_amount' => 3000]);
        $this->assertSame('2000.00', $customerPlan->fresh()->monthly_amount);
    }

    public function test_scheme_numbers_are_generated_and_unique(): void
    {
        $plans = CustomerPlan::factory()->count(5)->create();

        $numbers = $plans->pluck('scheme_number');
        $this->assertCount(5, $numbers->unique());
        $numbers->each(fn ($n) => $this->assertMatchesRegularExpression('/^SSJ-\d{4}-[A-Z0-9]{6}$/', $n));
    }

    public function test_duplicate_scheme_number_is_rejected(): void
    {
        CustomerPlan::factory()->create(['scheme_number' => 'SSJ-2610-ABC123']);

        $this->expectException(QueryException::class);
        CustomerPlan::factory()->create(['scheme_number' => 'SSJ-2610-ABC123']);
    }

    public function test_all_statuses_are_supported(): void
    {
        foreach (CustomerPlanStatus::cases() as $status) {
            $customerPlan = CustomerPlan::factory()->status($status)->create();
            $this->assertSame($status, $customerPlan->fresh()->status);
        }

        $this->assertSame(
            ['pending', 'active', 'completed', 'cancelled', 'matured'],
            array_map(fn ($s) => $s->value, CustomerPlanStatus::cases()),
        );
    }

    public function test_user_with_customer_plans_cannot_be_deleted(): void
    {
        $customerPlan = CustomerPlan::factory()->create();

        $this->expectException(QueryException::class);
        $customerPlan->user->delete();
    }
}
