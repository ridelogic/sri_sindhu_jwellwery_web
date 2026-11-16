<?php

namespace Tests\Feature\Database;

use App\Models\CustomerPlan;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_can_be_created_with_casts(): void
    {
        $plan = Plan::create([
            'name' => 'Gold Basic',
            'slug' => 'gold-basic',
            'monthly_amount' => 1000,
            'duration_months' => 11,
            'benefits' => [['title' => 'Fixed Monthly Contribution', 'text' => 'Same amount every month.']],
            'status' => true,
        ])->fresh();

        $this->assertSame('1000.00', $plan->monthly_amount);
        $this->assertSame('11000.00', $plan->total_amount);
        $this->assertSame(11, $plan->duration_months);
        $this->assertIsArray($plan->benefits);
        $this->assertSame('Fixed Monthly Contribution', $plan->benefits[0]['title']);
        $this->assertTrue($plan->status);
    }

    public function test_total_amount_is_always_derived_from_instalments(): void
    {
        $plan = Plan::factory()->create(['monthly_amount' => 2000, 'duration_months' => 11, 'total_amount' => 1]);
        $this->assertSame('22000.00', $plan->fresh()->total_amount);

        $plan->update(['monthly_amount' => 2500]);
        $this->assertSame('27500.00', $plan->fresh()->total_amount);
    }

    public function test_plan_slug_is_unique(): void
    {
        Plan::factory()->create(['slug' => 'gold-plus']);

        $this->expectException(QueryException::class);
        Plan::factory()->create(['slug' => 'gold-plus']);
    }

    public function test_active_scope_and_ordering(): void
    {
        Plan::factory()->create(['slug' => 'b', 'sort_order' => 2]);
        Plan::factory()->create(['slug' => 'a', 'sort_order' => 1]);
        Plan::factory()->inactive()->create(['slug' => 'hidden', 'sort_order' => 0]);

        $this->assertSame(['a', 'b'], Plan::query()->active()->ordered()->pluck('slug')->all());
    }

    public function test_plan_has_customer_plans_and_customers(): void
    {
        $plan = Plan::factory()->create();
        $users = User::factory()->count(2)->create();

        foreach ($users as $user) {
            CustomerPlan::factory()->for($user)->for($plan)->create();
        }

        $this->assertCount(2, $plan->customerPlans);
        $this->assertEqualsCanonicalizing($users->pluck('id')->all(), $plan->customers->pluck('id')->all());
    }

    public function test_plan_with_customer_plans_cannot_be_deleted(): void
    {
        $customerPlan = CustomerPlan::factory()->create();

        // Refused by the model guard (clear message); the restrict FK is a second safety net.
        $this->expectException(\LogicException::class);
        $customerPlan->plan->delete();
    }
}
