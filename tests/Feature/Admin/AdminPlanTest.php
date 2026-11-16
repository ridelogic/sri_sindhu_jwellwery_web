<?php

namespace Tests\Feature\Admin;

use App\Enums\CustomerPlanStatus;
use App\Models\CustomerPlan;
use App\Models\Plan;
use App\Models\User;
use App\Services\GoldSavingPlanService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use LogicException;
use Tests\TestCase;

class AdminPlanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function validPayload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Gold Basic',
            'slug' => 'gold-basic',
            'tagline' => 'A graceful first step into gold.',
            'monthly_amount' => '5000',
            'duration_months' => '11',
            'status' => '1',
            'is_featured' => '0',
            'sort_order' => '1',
        ];
    }

    private function create(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->admin)->from(route('admin.plans.create'))->post(route('admin.plans.store'), $this->validPayload($overrides));
    }

    private function updatePlan(Plan $plan, array $overrides = []): TestResponse
    {
        $payload = $overrides + [
            'name' => $plan->name, 'slug' => $plan->slug, 'tagline' => $plan->tagline,
            'monthly_amount' => $plan->monthly_amount, 'duration_months' => $plan->duration_months,
            'status' => $plan->status ? '1' : '0',
            'is_featured' => $plan->is_featured ? '1' : '0', 'sort_order' => $plan->sort_order,
        ];

        return $this->actingAs($this->admin)->from(route('admin.plans.edit', $plan))->put(route('admin.plans.update', $plan), $payload);
    }

    private function fresh(): void
    {
        // Public pages cache plans per request; simulate a new request.
        $this->app->forgetInstance(GoldSavingPlanService::class);
    }

    // 1–2. Access -----------------------------------------------------------------------

    public function test_guest_is_blocked_from_admin_plans(): void
    {
        $plan = Plan::factory()->create();

        foreach ([route('admin.plans.index'), route('admin.plans.create'), route('admin.plans.show', $plan), route('admin.plans.edit', $plan)] as $url) {
            $this->get($url)->assertRedirect(route('admin.login'));
        }
        $this->post(route('admin.plans.store'), $this->validPayload())->assertRedirect(route('admin.login'));
        $this->put(route('admin.plans.update', $plan), ['name' => 'x'])->assertRedirect(route('admin.login'));
        $this->post(route('admin.plans.deactivate', $plan))->assertRedirect(route('admin.login'));

        $this->assertTrue($plan->fresh()->status);
    }

    public function test_customer_is_blocked_from_admin_plans(): void
    {
        $plan = Plan::factory()->create();
        $customer = User::factory()->create();

        foreach ([route('admin.plans.index'), route('admin.plans.create'), route('admin.plans.show', $plan), route('admin.plans.edit', $plan)] as $url) {
            $this->actingAs($customer)->get($url)->assertForbidden();
        }
        $this->actingAs($customer)->post(route('admin.plans.store'), $this->validPayload(['slug' => 'sneaky']))->assertForbidden();
        $this->actingAs($customer)->post(route('admin.plans.deactivate', $plan))->assertForbidden();

        $this->assertDatabaseMissing('plans', ['slug' => 'sneaky']);
        $this->assertTrue($plan->fresh()->status);
    }

    // 3–6. List / create / view / edit ---------------------------------------------------

    public function test_admin_can_list_plans(): void
    {
        Plan::factory()->create(['name' => 'Gold Basic', 'slug' => 'gold-basic', 'monthly_amount' => 1000, 'duration_months' => 11]);
        Plan::factory()->inactive()->create(['name' => 'Old Plan', 'slug' => 'old-plan']);

        $response = $this->actingAs($this->admin)->get(route('admin.plans.index'))->assertOk();

        $response->assertSee('Gold Saving Plans')
            ->assertSee('Gold Basic')->assertSee('gold-basic')->assertSee('₹1,000')->assertSee('11 months')->assertSee('₹11,000')
            ->assertSee('Old Plan')->assertSee('Inactive')
            ->assertSee('href="'.route('admin.plans.create').'"', false);
    }

    public function test_admin_can_create_a_plan(): void
    {
        $this->actingAs($this->admin)->get(route('admin.plans.create'))->assertOk()
            // The form posts the plan configuration (amounts per installment, tenure in its own unit).
            ->assertSee('name="minimum_amount"', false)->assertSee('name="maximum_amount"', false)
            ->assertSee('name="tenure_unit"', false)->assertSee('name="maximum_tenure"', false)
            ->assertDontSee('name="total_amount"', false);

        $response = $this->create();

        $plan = Plan::query()->where('slug', 'gold-basic')->firstOrFail();
        $response->assertRedirect(route('admin.plans.show', $plan))->assertSessionHas('status');

        $this->assertSame('Gold Basic', $plan->name);
        $this->assertSame('5000.00', $plan->monthly_amount);
        $this->assertSame(11, $plan->duration_months);
        $this->assertSame('55000.00', $plan->total_amount);
        $this->assertTrue($plan->status);
        $this->assertSame(1, $plan->sort_order);
    }

    public function test_admin_can_view_a_plan(): void
    {
        $this->create();
        $plan = Plan::query()->firstOrFail();
        // Previously saved website text (no longer edited on the form): the description is still shown.
        $plan->forceFill([
            'description' => 'An accessible monthly plan.',
            'benefits' => [['title' => 'Fixed Monthly Contribution', 'text' => 'Same amount every month.']],
            'terms' => 'Scheme terms apply.',
        ])->save();

        $this->actingAs($this->admin)->get(route('admin.plans.show', $plan))->assertOk()
            ->assertSee('Gold Basic')->assertSee('An accessible monthly plan.')
            ->assertSee('₹5,000')->assertSee('11 months')->assertSee('₹55,000')
            ->assertSee('Active')->assertSee('Customers Enrolled')
            ->assertSee($plan->created_at->format('d M Y'))
            ->assertDontSee('data-enrolment-notice', false);
    }

    public function test_plan_details_page_has_no_benefits_or_terms_cards(): void
    {
        $saved = Plan::factory()->create([
            'slug' => 'gold-saved',
            'benefits' => [['title' => 'Fixed Monthly Contribution', 'text' => 'Same amount every month.']],
            'terms' => 'Scheme terms apply.',
        ]);
        $empty = Plan::factory()->create(['slug' => 'gold-empty', 'benefits' => null, 'terms' => null]);

        foreach ([$saved, $empty] as $plan) {
            $this->actingAs($this->admin)->get(route('admin.plans.show', $plan))->assertOk()
                ->assertDontSee('benefits-heading', false)
                ->assertDontSee('terms-heading', false)
                ->assertDontSee('data-plan-benefits', false)
                ->assertDontSee('No plan-specific benefits')
                ->assertDontSee('No terms entered.')
                ->assertDontSee('Fixed Monthly Contribution')
                ->assertDontSee('Scheme terms apply.')
                ->assertSee('data-plan-configuration', false); // other plan information remains
        }

        // Saved data is untouched — only the cards are gone.
        $saved->refresh();
        $this->assertSame('Fixed Monthly Contribution', $saved->benefits[0]['title']);
        $this->assertSame('Scheme terms apply.', $saved->terms);
    }

    public function test_admin_can_edit_a_plan(): void
    {
        $plan = Plan::factory()->create(['name' => 'Gold Basic', 'slug' => 'gold-basic', 'monthly_amount' => 1000, 'duration_months' => 11]);

        $this->actingAs($this->admin)->get(route('admin.plans.edit', $plan))->assertOk()->assertSee('value="gold-basic"', false);

        $this->updatePlan($plan, ['name' => 'Gold Basic Plus', 'monthly_amount' => '1500.50', 'duration_months' => '12'])
            ->assertRedirect(route('admin.plans.show', $plan))
            ->assertSessionHasNoErrors();

        $plan->refresh();
        $this->assertSame('Gold Basic Plus', $plan->name);
        $this->assertSame('1500.50', $plan->monthly_amount);
        $this->assertSame('18006.00', $plan->total_amount);
    }

    // 7. Total ------------------------------------------------------------------------------

    public function test_total_is_monthly_times_duration_calculated_server_side(): void
    {
        $this->create(['monthly_amount' => '5000', 'duration_months' => '11', 'total_amount' => '1']); // tampered total ignored
        $this->assertSame('55000.00', Plan::query()->where('slug', 'gold-basic')->value('total_amount'));

        $this->create(['slug' => 'gold-odd', 'monthly_amount' => '1234.56', 'duration_months' => '11']);
        $this->assertSame('13580.16', Plan::query()->where('slug', 'gold-odd')->value('total_amount'));

        // Exact paise arithmetic (no floating-point drift).
        $this->assertSame('3.30', Money::multiply('0.10', 33));
        $this->assertSame('13580.16', Money::multiply('1234.56', 11));
        $this->assertSame(123456, Money::toPaise('1234.56'));
        $this->assertSame('1234.50', Money::fromPaise(Money::toPaise('1234.5')));
    }

    // 8–11. Validation -----------------------------------------------------------------------

    public function test_invalid_monthly_amount_is_rejected(): void
    {
        $this->create(['monthly_amount' => 'abc'])->assertSessionHasErrors('monthly_amount');
        $this->create(['monthly_amount' => '1000.555'])->assertSessionHasErrors(['monthly_amount' => 'The monthly contribution can have at most 2 decimal places (paise).']);
        $this->create(['monthly_amount' => ''])->assertSessionHasErrors('monthly_amount');
        $this->assertDatabaseCount('plans', 0);
    }

    public function test_zero_amount_is_rejected(): void
    {
        $this->create(['monthly_amount' => '0'])->assertSessionHasErrors(['monthly_amount' => 'The monthly contribution must be greater than zero.']);
        $this->assertDatabaseCount('plans', 0);
    }

    public function test_negative_amount_is_rejected(): void
    {
        $this->create(['monthly_amount' => '-500'])->assertSessionHasErrors('monthly_amount');
        $this->assertDatabaseCount('plans', 0);
    }

    public function test_invalid_duration_is_rejected(): void
    {
        foreach (['0', '-1', '11.5', 'twelve', '', '121'] as $duration) {
            $this->create(['duration_months' => $duration])->assertSessionHasErrors('duration_months');
        }
        $this->assertDatabaseCount('plans', 0);
    }

    public function test_other_fields_are_validated(): void
    {
        $this->create(['name' => ''])->assertSessionHasErrors('name');
        $this->create(['name' => str_repeat('x', 101)])->assertSessionHasErrors('name');
        $this->create(['slug' => 'Gold Basic!'])->assertSessionHasErrors('slug');
        $this->create(['slug' => '-gold-'])->assertSessionHasErrors('slug');
        $this->create(['sort_order' => '-1'])->assertSessionHasErrors('sort_order');
        $this->create(['status' => 'maybe'])->assertSessionHasErrors('status');
        $this->assertDatabaseCount('plans', 0);
    }

    // 12. Slug -----------------------------------------------------------------------------------

    public function test_slug_uniqueness_is_enforced(): void
    {
        $this->create()->assertSessionHasNoErrors();
        $this->create(['name' => 'Another'])->assertSessionHasErrors(['slug' => 'Another plan already uses this slug.']);

        $other = Plan::factory()->create(['slug' => 'gold-plus']);
        $this->updatePlan($other, ['slug' => 'gold-basic'])->assertSessionHasErrors('slug');

        // A plan may keep its own slug when edited.
        $this->updatePlan($other, ['name' => 'Gold Plus II'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('plans', 2);
    }

    public function test_slug_is_normalised_to_lowercase(): void
    {
        $this->create(['slug' => 'GOLD-BASIC'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('plans', ['slug' => 'gold-basic']);
    }

    public function test_slug_is_locked_once_customers_have_joined(): void
    {
        $plan = Plan::factory()->create(['slug' => 'gold-basic']);

        // No enrolments yet: slug can change.
        $this->updatePlan($plan, ['slug' => 'gold-starter'])->assertSessionHasNoErrors();
        $plan->refresh();
        $this->assertSame('gold-starter', $plan->slug);

        CustomerPlan::factory()->for($plan)->create();

        $this->actingAs($this->admin)->get(route('admin.plans.edit', $plan))
            ->assertSee('Locked — customers have joined this plan', false)
            ->assertSee('readonly', false);

        $this->updatePlan($plan, ['slug' => 'gold-renamed'])
            ->assertSessionHasErrors(['slug' => 'This plan has customer enrolments, so its URL slug cannot be changed.']);
        $this->assertSame('gold-starter', $plan->fresh()->slug);
    }

    // 13. Description, benefits and terms (no longer on the admin form) ------------------------------

    public function test_create_and_edit_forms_do_not_show_description_benefits_or_terms(): void
    {
        $plan = Plan::factory()->create();

        foreach ([route('admin.plans.create'), route('admin.plans.edit', $plan)] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk()
                ->assertDontSee('name="description"', false)
                ->assertDontSee('name="terms"', false)
                ->assertDontSee('data-benefits', false)
                ->assertDontSee('Add Benefit')
                ->assertSee('name="tagline"', false); // other fields remain
        }
    }

    public function test_plan_saves_without_description_benefits_or_terms(): void
    {
        $this->create()->assertSessionHasNoErrors();

        $plan = Plan::query()->where('slug', 'gold-basic')->firstOrFail();
        $this->assertNull($plan->description);
        $this->assertNull($plan->benefits); // website falls back to the scheme-wide benefits
        $this->assertNull($plan->terms);
    }

    public function test_posted_description_benefits_and_terms_are_ignored(): void
    {
        $this->create([
            'description' => 'Injected description.',
            'benefits' => [['title' => 'Injected', 'text' => 'x']],
            'terms' => 'Injected terms.',
        ])->assertSessionHasNoErrors();

        $plan = Plan::query()->where('slug', 'gold-basic')->firstOrFail();
        $this->assertNull($plan->description);
        $this->assertNull($plan->benefits);
        $this->assertNull($plan->terms);
    }

    public function test_editing_a_plan_keeps_existing_description_benefits_and_terms(): void
    {
        $benefits = [['title' => 'Fixed Monthly Contribution', 'text' => 'Same amount every month.']];
        $plan = Plan::factory()->create([
            'slug' => 'gold-basic', 'description' => 'Saved description.', 'benefits' => $benefits, 'terms' => 'Saved terms.',
        ]);

        $this->updatePlan($plan, ['name' => 'Gold Basic Renamed', 'description' => 'Overwrite attempt.', 'terms' => ''])
            ->assertSessionHasNoErrors();

        $plan->refresh();
        $this->assertSame('Gold Basic Renamed', $plan->name);
        $this->assertSame('Saved description.', $plan->description);
        $this->assertSame($benefits, $plan->benefits);
        $this->assertSame('Saved terms.', $plan->terms);
    }

    // 14–15. Public visibility ---------------------------------------------------------------------

    public function test_active_plan_appears_publicly(): void
    {
        $this->create()->assertSessionHasNoErrors();
        $this->fresh();

        $this->get(route('gold.saving'))->assertOk()->assertSee('Gold Basic')->assertSee(route('gold.saving.show', 'gold-basic'), false);
        $this->get(route('home'))->assertSee('Gold Basic');
        $this->get(route('gold.saving.show', 'gold-basic'))->assertOk()->assertSee('Join This Plan')->assertSee('Fixed Monthly Contribution');
    }

    public function test_inactive_plan_is_not_offered(): void
    {
        $active = Plan::factory()->create(['name' => 'Gold Plus', 'slug' => 'gold-plus']);
        $plan = Plan::factory()->create(['name' => 'Gold Basic', 'slug' => 'gold-basic']);

        $this->actingAs($this->admin)->post(route('admin.plans.deactivate', $plan))
            ->assertRedirect()->assertSessionHas('status');
        $this->assertFalse($plan->fresh()->status);

        $this->fresh();
        $this->get(route('gold.saving'))->assertOk()->assertDontSee('Gold Basic')->assertSee('Gold Plus');
        $this->get(route('home'))->assertDontSee('Gold Basic');
        $this->get(route('register', ['plan' => 'gold-basic']))->assertDontSee('Selected plan');

        // Its page still works (no broken links) but says it is closed.
        $this->get(route('gold.saving.show', 'gold-basic'))->assertOk()
            ->assertSee('data-plan-closed', false)
            ->assertSee('noindex', false)
            ->assertDontSee('Join This Plan');

        // Reactivate.
        $this->actingAs($this->admin)->post(route('admin.plans.activate', $plan))->assertSessionHas('status');
        $this->assertTrue($plan->fresh()->status);
        $this->fresh();
        $this->get(route('gold.saving'))->assertSee('Gold Basic');
    }

    public function test_deactivating_every_plan_never_brings_back_demo_plans(): void
    {
        $plan = Plan::factory()->create(['name' => 'Only Plan', 'slug' => 'only-plan']);
        $this->actingAs($this->admin)->post(route('admin.plans.deactivate', $plan));

        $this->fresh();
        $this->get(route('gold.saving'))->assertOk()->assertDontSee('Gold Premium')->assertDontSee('Only Plan');
        $this->get(route('home'))->assertOk()->assertSee('New plans are coming soon.');
        $this->get(route('about'))->assertOk();
        $this->get(route('gold.rate'))->assertOk();
    }

    // 16–17. Existing customer protection ---------------------------------------------------------

    public function test_the_mandatory_existing_customer_scenario(): void
    {
        // Admin creates Gold Basic ₹5,000 × 11 = ₹55,000.
        $this->create(['monthly_amount' => '5000', 'duration_months' => '11'])->assertSessionHasNoErrors();
        $plan = Plan::query()->where('slug', 'gold-basic')->firstOrFail();
        $this->assertSame('55000.00', $plan->total_amount);

        $this->fresh();
        $this->get(route('gold.saving'))->assertSee('₹5,000')->assertSee('₹55,000');
        $this->get(route('gold.saving.show', 'gold-basic'))->assertSee('₹5,000');

        // Customer A joins.
        $customerPlan = CustomerPlan::query()->create(['user_id' => User::factory()->create()->id, 'plan_id' => $plan->id, 'status' => CustomerPlanStatus::Active]);
        $before = $customerPlan->fresh()->only(['monthly_amount', 'duration_months', 'total_commitment', 'status', 'scheme_number', 'start_date', 'maturity_date']);

        // Admin changes the plan to ₹6,000 × 11.
        $this->updatePlan($plan->fresh(), ['monthly_amount' => '6000', 'duration_months' => '11'])->assertSessionHasNoErrors();

        // plans: ₹6,000 / ₹66,000
        $plan->refresh();
        $this->assertSame('6000.00', $plan->monthly_amount);
        $this->assertSame('66000.00', $plan->total_amount);

        // customer_plans: unchanged ₹5,000 / ₹55,000
        $customerPlan->refresh();
        $this->assertSame('5000.00', $customerPlan->monthly_amount);
        $this->assertSame(11, $customerPlan->duration_months);
        $this->assertSame('55000.00', $customerPlan->total_commitment);
        $this->assertEquals($before, $customerPlan->only(array_keys($before)));

        // Public pages show the new values.
        $this->fresh();
        $this->get(route('gold.saving'))->assertSee('₹6,000')->assertSee('₹66,000')->assertDontSee('₹55,000');
        $this->get(route('gold.saving.show', 'gold-basic'))->assertSee('₹6,000')->assertSee('₹66,000');
    }

    public function test_deactivating_a_plan_does_not_alter_customer_plans(): void
    {
        $plan = Plan::factory()->create(['monthly_amount' => 2000, 'duration_months' => 11]);
        $enrolments = CustomerPlan::factory()->count(2)->for($plan)->status(CustomerPlanStatus::Active)->create();
        $before = CustomerPlan::query()->orderBy('id')->get()->map->only(['id', 'plan_id', 'monthly_amount', 'total_commitment', 'status', 'updated_at'])->all();

        $this->actingAs($this->admin)->post(route('admin.plans.deactivate', $plan))->assertSessionHas('status');

        $this->assertFalse($plan->fresh()->status);
        $this->assertEquals($before, CustomerPlan::query()->orderBy('id')->get()->map->only(['id', 'plan_id', 'monthly_amount', 'total_commitment', 'status', 'updated_at'])->all());

        // Existing enrolments still resolve their (inactive) plan correctly.
        $this->assertSame($plan->name, $enrolments->first()->fresh()->plan->name);
    }

    // 18–19. No delete + counts ------------------------------------------------------------------------

    public function test_plan_with_enrolments_cannot_be_deleted(): void
    {
        $plan = Plan::factory()->create();
        CustomerPlan::factory()->for($plan)->create();

        // No delete route exists.
        $this->actingAs($this->admin)->delete(route('admin.plans.update', $plan))->assertStatus(405);
        $this->actingAs($this->admin)->get(route('admin.plans.index'))->assertDontSee('Delete');

        $this->expectException(LogicException::class);
        $plan->delete();
    }

    public function test_customer_counts_are_correct(): void
    {
        $plan = Plan::factory()->create(['slug' => 'counted']);
        [$a, $b] = User::factory()->count(2)->create();
        CustomerPlan::factory()->count(2)->for($a)->for($plan)->create(); // same customer twice
        CustomerPlan::factory()->for($b)->for($plan)->create();
        CustomerPlan::factory()->create(); // another plan

        $index = $this->actingAs($this->admin)->get(route('admin.plans.index'))->getContent();
        $this->assertMatchesRegularExpression('/data-plan-row="counted".*?data-plan-customers>2</s', $index);

        $this->actingAs($this->admin)->get(route('admin.plans.show', $plan))
            ->assertSee('data-enrolment-notice', false)
            ->assertSee('This plan has existing customer enrolments. Financial values for existing enrolments are preserved.')
            ->assertSee('3 enrolments in total');

        $this->assertMatchesRegularExpression('/data-plan-customers>2</', $this->get(route('admin.plans.show', $plan))->getContent());
    }

    // 20–22. Public integration -------------------------------------------------------------------------

    public function test_public_pages_read_database_plan_values(): void
    {
        Plan::factory()->create(['name' => 'Gold Lakshmi', 'slug' => 'gold-lakshmi', 'monthly_amount' => '3333.50', 'duration_months' => 12, 'tagline' => 'Tagline from the database.']);

        $this->get(route('gold.saving'))->assertOk()->assertSee('Gold Lakshmi')->assertSee('₹3,333.50')->assertSee('₹40,002')->assertSee('Tagline from the database.');
        $this->get(route('gold.saving.show', 'gold-lakshmi'))->assertOk()->assertSee('Gold Lakshmi.')->assertSee('₹3,333.50')->assertSee('12 Months')->assertSee('₹40,002');
    }

    public function test_changing_a_plan_updates_future_public_display(): void
    {
        $plan = Plan::factory()->create(['name' => 'Gold Plus', 'slug' => 'gold-plus', 'monthly_amount' => 2000, 'duration_months' => 11]);
        $this->get(route('gold.saving.show', 'gold-plus'))->assertSee('₹2,000');

        $this->updatePlan($plan, ['monthly_amount' => '2500', 'name' => 'Gold Plus Select'])->assertSessionHasNoErrors();

        $this->fresh();
        $this->get(route('gold.saving'))->assertSee('Gold Plus Select')->assertSee('₹2,500')->assertSee('₹27,500');
        $this->get(route('gold.saving.show', 'gold-plus'))->assertSee('₹2,500')->assertDontSee('₹2,000 ');
        $this->get(route('home'))->assertSee('Gold Plus Select');
    }

    // Dashboard + security -----------------------------------------------------------------------------

    public function test_dashboard_links_active_plans_to_plan_management(): void
    {
        Plan::factory()->count(2)->create();

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertSee('Manage Plans')
            ->assertSee('href="'.route('admin.plans.index').'"', false);
    }

    public function test_plan_routes_are_admin_and_csrf_protected(): void
    {
        foreach (['admin.plans.store', 'admin.plans.update', 'admin.plans.activate', 'admin.plans.deactivate'] as $name) {
            $middleware = app('router')->getRoutes()->getByName($name)->gatherMiddleware();
            $this->assertContains('web', $middleware);
            $this->assertContains('admin', $middleware);
        }

        $this->actingAs($this->admin)->get(route('admin.plans.create'))->assertSee('name="_token"', false);
    }

    public function test_activate_and_deactivate_require_post(): void
    {
        $plan = Plan::factory()->create();

        $this->actingAs($this->admin)->get(route('admin.plans.index').'/'.$plan->id.'/deactivate')->assertStatus(405);
        $this->assertTrue($plan->fresh()->status);
    }
}
