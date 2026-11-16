<?php

namespace Tests\Feature;

use App\Enums\GoldPurity;
use App\Models\GoldRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The homepage "Today's Gold Rate" is driven by the gold_rates table
 * (via GoldRateService); Gold Rate is not a main navigation item.
 */
class HomepageGoldRateTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seedRates(): void
    {
        Carbon::setTestNow('2026-10-06 14:00:00');

        GoldRate::factory()->rate(6950)->on('2026-10-05', '09:30:00')->create();
        GoldRate::factory()->rate(7010)->on('2026-10-06', '09:30:00')->create();
        GoldRate::factory()->rate(7025)->on('2026-10-06', '11:15:00')->create();               // latest active 22K
        GoldRate::factory()->rate(9999)->on('2026-10-06', '12:00:00')->inactive()->create();    // ignored
        GoldRate::factory()->purity(GoldPurity::K24)->rate(7600)->on('2026-10-05', '09:30:00')->create();
        GoldRate::factory()->purity(GoldPurity::K24)->rate(7665)->on('2026-10-06', '09:30:00')->create(); // latest 24K
    }

    private function rateSection(TestResponse $response): string
    {
        preg_match('/<section id="gold-rate".*?<\/section>/s', $response->getContent(), $match);

        return $match[0] ?? '';
    }

    private function header(TestResponse $response): string
    {
        preg_match('/<header class="site-header.*?<\/header>/s', $response->getContent(), $match);

        return $match[0] ?? '';
    }

    public function test_homepage_shows_latest_active_database_rates(): void
    {
        $this->seedRates();

        $section = $this->rateSection($this->get(route('home'))->assertOk());

        $this->assertStringContainsString("Today's", $section);
        $this->assertMatchesRegularExpression('/data-gold-rate="22K".*?₹7,025.*?Per Gram.*?22K \/ 10 Gram.*?₹70,250/s', $section);
        $this->assertMatchesRegularExpression('/data-gold-rate="24K".*?₹7,665.*?Per Gram.*?24K \/ 10 Gram.*?₹76,650/s', $section);
        $this->assertStringNotContainsString('₹9,999', $section);
        $this->assertStringNotContainsString('₹7,010', $section);
    }

    public function test_homepage_shows_the_update_timestamp_of_the_latest_rate(): void
    {
        $this->seedRates();

        $section = $this->rateSection($this->get(route('home')));

        $this->assertStringContainsString('Updated <time datetime="2026-10-06T11:15:00', $section);
        $this->assertStringContainsString('06 Oct 2026, 11:15 AM', $section);
    }

    public function test_real_database_rates_are_not_labelled_as_preview(): void
    {
        $this->seedRates();

        $this->assertStringNotContainsString('data-sample-notice', $this->rateSection($this->get(route('home'))));
    }

    public function test_hero_rate_strip_uses_the_same_database_rates(): void
    {
        $this->seedRates();

        preg_match('/data-gold-rate-strip.*?<\/div>\s*<\/div>/s', $this->get(route('home'))->getContent(), $strip);

        $this->assertStringContainsString('₹7,025', $strip[0]);
        $this->assertStringContainsString('₹7,665', $strip[0]);
    }

    public function test_new_admin_rate_appears_without_editing_the_homepage(): void
    {
        $this->seedRates();
        $this->assertStringContainsString('₹7,025', $this->rateSection($this->get(route('home'))));

        // A new rate row (as the future admin module will create) — no view changes needed.
        Carbon::setTestNow('2026-10-06 16:00:00');
        GoldRate::factory()->rate(7040)->on('2026-10-06', '15:45:00')->create();
        $this->app->forgetScopedInstances();

        $section = $this->rateSection($this->get(route('home')));
        $this->assertStringContainsString('₹7,040', $section);
        $this->assertStringContainsString('₹70,400', $section);
        $this->assertStringContainsString('06 Oct 2026, 3:45 PM', $section);
    }

    public function test_empty_database_keeps_the_labelled_development_fallback(): void
    {
        $section = $this->rateSection($this->get(route('home'))->assertOk());

        $this->assertStringContainsString('data-sample-notice', $section);
        $this->assertStringContainsString('₹7,000', $section);
        $this->assertStringContainsString('₹70,000', $section);
    }

    public function test_development_seed_rates_are_labelled_as_preview_on_the_homepage(): void
    {
        GoldRate::factory()->development()->rate(7000)->create();
        GoldRate::factory()->development()->purity(GoldPurity::K24)->rate(7650)->create();

        $this->assertStringContainsString('data-sample-notice', $this->rateSection($this->get(route('home'))));
    }

    public function test_gold_rate_is_not_in_the_header_navigation(): void
    {
        $header = $this->header($this->get(route('home')));

        $this->assertNotSame('', $header);
        $this->assertStringNotContainsString('href="'.route('gold.rate').'"', $header);
        $this->assertStringNotContainsString('>Gold Rate<', $header);

        foreach (['home', 'about', 'gold.saving', 'faq', 'contact', 'login'] as $route) {
            $this->assertStringContainsString('href="'.route($route).'"', $header, "Header should link to [$route].");
        }
        $this->assertStringContainsString('Join Gold Saving', $header);
    }

    public function test_view_gold_rate_history_link_points_to_the_detail_page(): void
    {
        $section = $this->rateSection($this->get(route('home')));

        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('gold.rate'), '/').'"[^>]*>\s*View Gold Rate History/', $section);
        $this->get(route('gold.rate'))->assertOk()->assertSee('Gold Rate');
    }
}
