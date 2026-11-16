<?php

namespace Tests\Feature\Database;

use App\Enums\GoldPurity;
use App\Models\GoldRate;
use App\Models\Plan;
use App\Services\GoldRateService;
use App\Services\GoldSavingPlanService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DevelopmentCustomerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public website reads DATABASE DATA when present and falls back to
 * clearly-marked DEMO DATA only when the tables are empty.
 */
class PublicSiteDataSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_database_uses_fallback_demo_data(): void
    {
        $this->assertTrue(app(GoldRateService::class)->isUsingFallback());
        $this->assertTrue(app(GoldRateService::class)->isSample());
        $this->assertTrue(app(GoldSavingPlanService::class)->isUsingFallback());

        $this->get(route('gold.saving'))->assertOk()->assertSee('Gold Basic');
    }

    public function test_plans_from_the_database_replace_the_fallback(): void
    {
        Plan::factory()->create(['name' => 'Gold Lakshmi', 'slug' => 'gold-lakshmi', 'monthly_amount' => 3000, 'duration_months' => 12, 'sort_order' => 1]);
        Plan::factory()->inactive()->create(['name' => 'Retired Plan', 'slug' => 'retired-plan']);

        $this->assertFalse(app(GoldSavingPlanService::class)->isUsingFallback());

        $this->get(route('gold.saving'))
            ->assertOk()
            ->assertSee('Gold Lakshmi')
            ->assertSee('₹3,000')
            ->assertSee('₹36,000')
            ->assertDontSee('Retired Plan')
            ->assertDontSee('Gold Basic');

        $this->get(route('gold.saving.show', 'gold-lakshmi'))->assertOk()->assertSee('Gold Lakshmi.');
        // Inactive plans keep their page (no broken links) but are not offered.
        $this->get(route('gold.saving.show', 'retired-plan'))->assertOk()->assertSee('data-plan-closed', false)->assertDontSee('Join This Plan');
        $this->get(route('gold.saving.show', 'never-existed'))->assertNotFound();
    }

    public function test_plan_specific_benefits_are_shown_on_the_plan_page(): void
    {
        Plan::factory()->create([
            'slug' => 'gold-special',
            'benefits' => [['icon' => 'gem', 'title' => 'Bridal Consultation', 'text' => 'A dedicated session with our team.']],
        ]);

        $this->get(route('gold.saving.show', 'gold-special'))->assertSee('Bridal Consultation');
    }

    public function test_gold_rates_from_the_database_replace_the_fallback(): void
    {
        GoldRate::factory()->rate(7100)->on(today()->subDay()->toDateString())->create();
        GoldRate::factory()->rate(7200)->on(today()->toDateString())->create();
        GoldRate::factory()->purity(GoldPurity::K24)->rate(7800)->on(today()->subDay()->toDateString())->create();
        GoldRate::factory()->purity(GoldPurity::K24)->rate(7850)->on(today()->toDateString())->create();

        $service = app(GoldRateService::class);
        $this->assertFalse($service->isUsingFallback());
        $this->assertFalse($service->isSample(), 'Manually entered rates are real and should not be marked as preview.');

        $this->get(route('gold.rate'))
            ->assertOk()
            ->assertSee('₹7,200')
            ->assertSee('₹72,000')
            ->assertSee('₹7,850')
            ->assertSee('Previous Rate')
            ->assertSee('₹7,100')
            ->assertDontSee('data-sample-notice', false);

        $this->get(route('home'))->assertSee('₹7,200');
    }

    public function test_development_seed_rates_are_still_labelled_as_preview(): void
    {
        GoldRate::factory()->development()->rate(7000)->create();
        GoldRate::factory()->development()->purity(GoldPurity::K24)->rate(7650)->create();

        $this->assertFalse(app(GoldRateService::class)->isUsingFallback());
        $this->assertTrue(app(GoldRateService::class)->isSample());

        $this->get(route('gold.rate'))->assertSee('data-sample-notice', false);
    }

    public function test_seeder_creates_development_data_and_site_still_works(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(['gold-basic', 'gold-plus', 'gold-premium'], Plan::query()->ordered()->pluck('slug')->all());
        $this->assertSame(GoldRate::query()->count(), GoldRate::query()->where('source', GoldRate::SOURCE_DEVELOPMENT)->count());
        $this->assertGreaterThanOrEqual(10, GoldRate::query()->forPurity('22K')->count());
        $this->assertGreaterThanOrEqual(10, GoldRate::query()->forPurity('24K')->count());

        $customer = \App\Models\User::query()->where('email', DevelopmentCustomerSeeder::EMAIL)->firstOrFail();
        $this->assertCount(3, $customer->customerPlans);
        $this->assertNotNull($customer->kycProfile);

        foreach (['home', 'about', 'gold.rate', 'gold.saving', 'faq', 'contact', 'login', 'register'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('gold.rate'))->assertSee('data-sample-notice', false);

        // Re-running the seeder adds nothing and overwrites no rate history.
        $rates = GoldRate::query()->count();
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($rates, GoldRate::query()->count());
        $this->assertSame(3, Plan::query()->count());
    }
}
