<?php

namespace Tests\Feature\Customer;

use App\Enums\CustomerPlanStatus;
use App\Models\CustomerPlan;
use App\Models\Plan;
use App\Models\User;
use App\Services\EnrolmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerPlansTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private Plan $basic;

    private Plan $plus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::factory()->create();
        $this->basic = Plan::factory()->create(['name' => 'Gold Basic', 'slug' => 'gold-basic', 'monthly_amount' => 1000, 'duration_months' => 11, 'sort_order' => 1]);
        $this->plus = Plan::factory()->create(['name' => 'Gold Plus', 'slug' => 'gold-plus', 'monthly_amount' => 2000, 'duration_months' => 11, 'sort_order' => 2]);
    }

    private function join(string $slug, ?string $token = null, array $extra = []): TestResponse
    {
        return $this->actingAs($this->customer, 'customer')
            ->post(route('customer.plans.join', $slug), $extra + ['enrolment_token' => $token ?? (string) Str::uuid()]);
    }

    public function test_customer_can_view_active_plans(): void
    {
        Plan::factory()->inactive()->create(['name' => 'Retired Plan', 'slug' => 'retired']);

        $this->actingAs($this->customer, 'customer')->get(route('customer.plans.index'))
            ->assertOk()
            ->assertSee('Gold Basic')->assertSee('Gold Plus')
            ->assertSee('₹1,000')->assertSee('₹11,000')->assertSee('11 months')
            ->assertSee('Join This Scheme')
            ->assertDontSee('Retired Plan');

        $this->get(route('customer.plans.show', 'gold-plus'))->assertOk()
            ->assertSee('Gold Plus')->assertSee('₹2,000')->assertSee('₹22,000')
            ->assertSee('name="enrolment_token"', false)
            ->assertDontSee('name="monthly_amount"', false);
    }

    public function test_inactive_or_unknown_plan_cannot_be_viewed_for_joining(): void
    {
        Plan::factory()->inactive()->create(['slug' => 'retired']);

        $this->actingAs($this->customer, 'customer')->get(route('customer.plans.show', 'retired'))->assertNotFound();
        $this->get(route('customer.plans.show', 'nope'))->assertNotFound();
    }

    public function test_customer_can_join_another_active_plan(): void
    {
        CustomerPlan::factory()->for($this->customer)->for($this->basic)->create();

        $response = $this->join('gold-plus');

        $scheme = $this->customer->customerPlans()->where('plan_id', $this->plus->id)->sole();
        $response->assertRedirect(route('customer.schemes.show', $scheme->scheme_number))->assertSessionHas('status');

        $this->assertSame('2000.00', $scheme->monthly_amount);
        $this->assertSame('22000.00', $scheme->total_commitment);
        $this->assertSame(CustomerPlanStatus::Pending, $scheme->status);
        $this->assertSame(2, $this->customer->customerPlans()->count());
    }

    public function test_multiple_schemes_are_allowed(): void
    {
        $premium = Plan::factory()->create(['slug' => 'gold-premium', 'monthly_amount' => 5000]);

        $this->join('gold-basic');
        $this->join('gold-plus');
        $this->join('gold-premium');

        $this->assertSame(3, $this->customer->customerPlans()->count());
        $this->assertCount(3, $this->customer->customerPlans()->pluck('scheme_number')->unique());

        $dashboard = $this->get(route('customer.dashboard'))->getContent();
        $this->assertSame(3, substr_count($dashboard, 'data-scheme-card='));
    }

    public function test_customer_cannot_join_inactive_plan(): void
    {
        $this->basic->update(['status' => false]);

        $this->join('gold-basic')->assertRedirect(route('customer.plans.index'))->assertSessionHasErrors('plan');
        $this->join('unknown-plan')->assertNotFound();

        $this->assertSame(0, CustomerPlan::query()->count());
    }

    public function test_plan_deactivated_between_view_and_join_is_rejected_by_the_service(): void
    {
        $this->basic->update(['status' => false]);

        $this->expectException(ValidationException::class);
        app(EnrolmentService::class)->enrol($this->customer, $this->basic);
    }

    public function test_customer_cannot_modify_financial_commitment(): void
    {
        $this->join('gold-basic', null, [
            'monthly_amount' => '1', 'duration_months' => '99', 'total_commitment' => '5',
            'scheme_number' => 'HACKED', 'status' => 'completed', 'plan_id' => (string) $this->plus->id, 'user_id' => '999',
        ]);

        $scheme = CustomerPlan::query()->sole();
        $this->assertTrue($scheme->plan->is($this->basic));
        $this->assertSame($this->customer->id, $scheme->user_id);
        $this->assertSame('1000.00', $scheme->monthly_amount);
        $this->assertSame(11, $scheme->duration_months);
        $this->assertSame('11000.00', $scheme->total_commitment);
        $this->assertNotSame('HACKED', $scheme->scheme_number);
        $this->assertSame(CustomerPlanStatus::Pending, $scheme->status);
    }

    public function test_double_submission_with_the_same_token_creates_one_enrolment(): void
    {
        $token = (string) Str::uuid();

        $first = $this->join('gold-plus', $token);
        $second = $this->join('gold-plus', $token);

        $this->assertSame(1, CustomerPlan::query()->count());
        $scheme = CustomerPlan::query()->sole();
        $first->assertRedirect(route('customer.schemes.show', $scheme->scheme_number));
        $second->assertRedirect(route('customer.schemes.show', $scheme->scheme_number))
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'already received'));
    }

    public function test_rapid_repeat_with_a_new_token_is_treated_as_duplicate(): void
    {
        $this->join('gold-plus');
        $this->join('gold-plus');

        $this->assertSame(1, CustomerPlan::query()->count());

        // Joining the same plan again later is a deliberate, allowed enrolment.
        Carbon::setTestNow(now()->addSeconds(EnrolmentService::DUPLICATE_WINDOW_SECONDS + 5));
        $this->join('gold-plus');
        $this->assertSame(2, CustomerPlan::query()->count());
        Carbon::setTestNow();
    }

    public function test_join_requires_a_valid_token(): void
    {
        $this->actingAs($this->customer, 'customer')->post(route('customer.plans.join', 'gold-plus'), [])->assertSessionHasErrors('enrolment_token');
        $this->join('gold-plus', 'not-a-uuid')->assertSessionHasErrors('enrolment_token');
        $this->assertSame(0, CustomerPlan::query()->count());
    }

    public function test_guest_and_admin_cannot_join(): void
    {
        $this->post(route('customer.plans.join', 'gold-plus'), ['enrolment_token' => (string) Str::uuid()])->assertRedirect(route('login'));

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'web')->post(route('customer.plans.join', 'gold-plus'), ['enrolment_token' => (string) Str::uuid()])->assertRedirect(route('login'));

        $this->expectException(ValidationException::class);
        app(EnrolmentService::class)->enrol($admin, $this->plus);
    }

    public function test_scheme_numbers_stay_unique_and_existing_numbers_are_unchanged(): void
    {
        $existing = CustomerPlan::factory()->for($this->customer)->create(['scheme_number' => 'SSJ-2601-KEEP01']);

        foreach (range(1, 5) as $i) {
            Carbon::setTestNow(now()->addMinutes($i));
            $this->join('gold-basic');
        }
        Carbon::setTestNow();

        $this->assertSame('SSJ-2601-KEEP01', $existing->fresh()->scheme_number);
        $numbers = CustomerPlan::query()->pluck('scheme_number');
        $this->assertCount(6, $numbers);
        $this->assertCount(6, $numbers->unique());
    }

    public function test_demo_fallback_plans_are_never_joinable(): void
    {
        Plan::query()->delete(); // empty table → website shows demo plans

        $this->actingAs($this->customer, 'customer')->get(route('customer.plans.index'))->assertOk()->assertSee('data-no-plans', false);
        $this->get(route('customer.plans.show', 'gold-basic'))->assertNotFound();
    }
}
