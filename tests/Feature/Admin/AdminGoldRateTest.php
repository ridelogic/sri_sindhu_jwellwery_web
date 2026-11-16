<?php

namespace Tests\Feature\Admin;

use App\Enums\GoldPurity;
use App\Models\GoldRate;
use App\Models\User;
use App\Services\GoldRateService;
use App\Services\GoldRateUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class AdminGoldRateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-06 16:00:00');
        $this->admin = User::factory()->admin()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function submit(string $time, string|float $rate22, string|float $rate24, array $extra = []): TestResponse
    {
        return $this->actingAs($this->admin)
            ->from(route('admin.gold-rates.create'))
            // Overrides first: with +, the left-hand array's keys win.
            ->post(route('admin.gold-rates.store'), $extra + [
                'rate_date' => '2026-10-06',
                'rate_time' => $time,
                'rate_22k' => $rate22,
                'rate_24k' => $rate24,
                'source' => 'Manual Admin Update',
            ]);
    }

    private function homepageRateSection(): string
    {
        $this->app->forgetInstance(GoldRateService::class);
        preg_match('/<section id="gold-rate".*?<\/section>/s', $this->get(route('home'))->getContent(), $match);

        return $match[0] ?? '';
    }

    // 1–2. Access control -------------------------------------------------------

    public function test_guest_is_blocked_from_every_gold_rate_page(): void
    {
        foreach (['admin.gold-rates.index', 'admin.gold-rates.create', 'admin.gold-rates.history'] as $route) {
            $this->get(route($route))->assertRedirect(route('admin.login'));
        }

        $this->post(route('admin.gold-rates.store'), ['rate_date' => '2026-10-06', 'rate_time' => '10:00', 'rate_22k' => 7000, 'rate_24k' => 7650])
            ->assertRedirect(route('admin.login'));

        $this->assertDatabaseCount('gold_rates', 0);
    }

    public function test_customer_is_blocked_from_every_gold_rate_page(): void
    {
        $customer = User::factory()->create();

        foreach (['admin.gold-rates.index', 'admin.gold-rates.create', 'admin.gold-rates.history'] as $route) {
            $this->actingAs($customer)->get(route($route))->assertForbidden();
        }

        $this->actingAs($customer)
            ->post(route('admin.gold-rates.store'), ['rate_date' => '2026-10-06', 'rate_time' => '10:00', 'rate_22k' => 7000, 'rate_24k' => 7650])
            ->assertForbidden();

        $this->assertDatabaseCount('gold_rates', 0);
    }

    // 3–4. Pages ------------------------------------------------------------------

    public function test_admin_can_view_rate_management(): void
    {
        GoldRate::factory()->rate(7000)->on('2026-10-06', '09:30:00')->create(['source' => 'Manual Admin Update']);
        GoldRate::factory()->purity(GoldPurity::K24)->rate(7650)->on('2026-10-06', '09:30:00')->create(['source' => 'Manual Admin Update']);

        $response = $this->actingAs($this->admin)->get(route('admin.gold-rates.index'))->assertOk();

        $response->assertSee('Gold Rate Management')
            ->assertSee("Update Today's Rate")
            ->assertSee('href="'.route('admin.gold-rates.create').'"', false);

        $this->assertMatchesRegularExpression('/data-current-rate="22K".*?data-rate-status>Active.*?data-rate-per-gram>₹7,000<.*?data-rate-per-10g>₹70,000<.*?data-rate-updated>06 Oct 2026, 09:30 AM</s', $response->getContent());
        $this->assertMatchesRegularExpression('/data-current-rate="24K".*?data-rate-per-gram>₹7,650<.*?data-rate-per-10g>₹76,500</s', $response->getContent());
    }

    public function test_rate_management_page_handles_no_rates(): void
    {
        $this->actingAs($this->admin)->get(route('admin.gold-rates.index'))
            ->assertOk()
            ->assertSee('No 22K rate has been recorded yet')
            ->assertSee('No gold rates recorded yet');
    }

    public function test_admin_can_open_update_form(): void
    {
        $this->actingAs($this->admin)->get(route('admin.gold-rates.create'))
            ->assertOk()
            ->assertSee('name="rate_date"', false)
            ->assertSee('type="date"', false)
            ->assertSee('name="rate_time"', false)
            ->assertSee('type="time"', false)
            ->assertSee('name="rate_22k"', false)
            ->assertSee('name="rate_24k"', false)
            ->assertSee('step="0.01"', false)
            ->assertSee('value="Manual Admin Update"', false)
            ->assertSee('data-rate-10g="22K"', false)
            ->assertSee('name="_token"', false)
            ->assertDontSee('name="rate_22k_10g"', false)
            ->assertDontSee('name="rate_per_10_gram"', false);
    }

    // 5–6. Creating rates ---------------------------------------------------------

    public function test_admin_can_submit_valid_rates(): void
    {
        $this->submit('10:15', '7000.50', '7650.25')
            ->assertRedirect(route('admin.gold-rates.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseCount('gold_rates', 2);

        $rate22 = GoldRate::latestFor('22K');
        $this->assertSame('7000.50', $rate22->rate_per_gram);
        $this->assertSame('10:15:00', $rate22->rate_time);
        $this->assertSame('Manual Admin Update', $rate22->source);
        $this->assertTrue($rate22->status);
        $this->assertSame('7650.25', GoldRate::latestFor('24K')->rate_per_gram);
    }

    public function test_ten_gram_value_is_calculated_server_side_and_never_trusted_from_the_browser(): void
    {
        $this->submit('10:00', '7000.55', '7650.10', [
            'rate_22k_10g' => 1, 'rate_per_10_gram' => 1, 'rate_24k_10g' => 1, // tampered values must be ignored
            'status' => 0,
        ])->assertRedirect(route('admin.gold-rates.index'));

        $this->assertSame('70005.50', GoldRate::latestFor('22K')->rate_per_10_gram);
        $this->assertSame('76501.00', GoldRate::latestFor('24K')->rate_per_10_gram);
        $this->assertTrue(GoldRate::latestFor('22K')->status);
    }

    // 7–9. Validation -------------------------------------------------------------

    public function test_zero_is_rejected(): void
    {
        $this->submit('10:00', '0', '7650')->assertSessionHasErrors(['rate_22k' => 'The 22K rate must be greater than zero.']);
        $this->submit('10:00', '7000', '0.00')->assertSessionHasErrors('rate_24k');
        $this->assertDatabaseCount('gold_rates', 0);
    }

    public function test_negative_is_rejected(): void
    {
        $this->submit('10:00', '-7000', '7650')->assertSessionHasErrors('rate_22k');
        $this->submit('10:00', '7000', '-1')->assertSessionHasErrors('rate_24k');
        $this->assertDatabaseCount('gold_rates', 0);
    }

    public function test_invalid_decimals_and_formats_are_rejected(): void
    {
        $this->submit('10:00', '7000.123', '7650')->assertSessionHasErrors(['rate_22k' => 'The 22K rate can have at most 2 decimal places (paise), e.g. 7000.50.']);
        $this->submit('10:00', 'abc', '7650')->assertSessionHasErrors('rate_22k');
        $this->submit('10:00', '7000', '7650.999')->assertSessionHasErrors('rate_24k');
        $this->submit('10:00', '7000', '99999999')->assertSessionHasErrors('rate_24k');
        $this->assertDatabaseCount('gold_rates', 0);
    }

    public function test_date_time_and_required_fields_are_validated(): void
    {
        $this->actingAs($this->admin)->post(route('admin.gold-rates.store'), [])
            ->assertSessionHasErrors(['rate_date', 'rate_time', 'rate_22k', 'rate_24k']);

        $this->submit('25:99', 7000, 7650)->assertSessionHasErrors('rate_time');
        $this->submit('10:00', 7000, 7650, ['rate_date' => '2026-13-45'])->assertSessionHasErrors('rate_date');
        $this->submit('10:00', 7000, 7650, ['rate_date' => '2026-10-07'])->assertSessionHasErrors(['rate_date' => 'The rate date cannot be in the future.']);
        $this->submit('18:00', 7000, 7650)->assertSessionHasErrors(['rate_time' => 'The effective time cannot be in the future.']);
        $this->submit('10:00', 7650, 7000)->assertSessionHasErrors('rate_24k'); // 24K must be higher than 22K
        $this->submit('10:00', 7000, 7650, ['source' => GoldRate::SOURCE_DEVELOPMENT])->assertSessionHasErrors('source');

        $this->assertDatabaseCount('gold_rates', 0);
    }

    public function test_duplicate_update_for_the_same_date_and_time_is_rejected(): void
    {
        $this->submit('10:00', 7000, 7650)->assertSessionHasNoErrors();
        $this->submit('10:00', 7010, 7660)->assertSessionHasErrors('rate_time');

        $this->assertDatabaseCount('gold_rates', 2);
    }

    // 10–13. Immutability and active-rate logic ------------------------------------

    public function test_the_exact_three_updates_scenario(): void
    {
        $this->submit('09:00', '7000', '7650')->assertSessionHasNoErrors();
        $first = GoldRate::query()->orderBy('id')->get()->map->only(['id', 'rate_per_gram', 'rate_per_10_gram', 'rate_time', 'purity', 'source']);

        $this->submit('12:00', '7050', '7700')->assertSessionHasNoErrors();
        $this->submit('15:00', '7100', '7750')->assertSessionHasNoErrors();

        // 3 historical updates = 6 records.
        $this->assertDatabaseCount('gold_rates', 6);
        $this->assertSame(3, GoldRate::query()->forPurity('22K')->count());
        $this->assertSame(3, GoldRate::query()->forPurity('24K')->count());

        // Previous rate values are unchanged.
        $this->assertEquals($first->all(), GoldRate::query()->whereIn('id', $first->pluck('id'))->orderBy('id')->get()->map->only(['id', 'rate_per_gram', 'rate_per_10_gram', 'rate_time', 'purity', 'source'])->all());

        // Only the 15:00 rates are active.
        $this->assertSame(['7100.00', '7750.00'], GoldRate::query()->active()->orderBy('purity')->pluck('rate_per_gram')->all());
        $this->assertSame(4, GoldRate::query()->where('status', false)->count());

        // Service + homepage.
        $service = app(GoldRateService::class);
        $this->assertSame('7100.00', $service->latest22K()->rate_per_gram);
        $this->assertSame('7750.00', $service->latest24K()->rate_per_gram);

        $section = $this->homepageRateSection();
        $this->assertMatchesRegularExpression('/data-gold-rate="22K".*?₹7,100.*?₹71,000/s', $section);
        $this->assertMatchesRegularExpression('/data-gold-rate="24K".*?₹7,750.*?₹77,500/s', $section);
        $this->assertStringContainsString('06 Oct 2026, 3:00 PM', $section);

        // History lists all three updates, newest first.
        $history = $this->actingAs($this->admin)->get(route('admin.gold-rates.history'))->assertOk();
        $history->assertSeeInOrder(['15:00', '₹7,100', '₹7,750', 'Manual Admin Update', 'Active',
            '12:00', '₹7,050', '₹7,700', 'Manual Admin Update', 'Inactive',
            '09:00', '₹7,000', '₹7,650', 'Manual Admin Update', 'Inactive']);
        $this->assertSame(3, substr_count($history->getContent(), 'data-rate-update'));
    }

    public function test_old_rate_remains_unchanged_and_new_rate_becomes_active(): void
    {
        $this->submit('09:00', '7000', '7650');
        $old = GoldRate::latestFor('22K');

        $this->submit('13:00', '7040', '7690');

        $old->refresh();
        $this->assertSame('7000.00', $old->rate_per_gram);
        $this->assertSame('70000.00', $old->rate_per_10_gram);
        $this->assertSame('09:00:00', $old->rate_time);
        $this->assertFalse($old->status);

        $new = GoldRate::latestFor('22K');
        $this->assertNotSame($old->id, $new->id);
        $this->assertTrue($new->status);
        $this->assertSame('7040.00', $new->rate_per_gram);
    }

    public function test_latest_effective_time_wins_not_created_at(): void
    {
        // Recorded first, effective later.
        $this->submit('13:00', '7080', '7730');
        // Recorded afterwards, but back-dated to an earlier time the same day.
        $this->submit('09:00', '7000', '7650');

        $this->assertSame('7080.00', GoldRate::latestFor('22K')->rate_per_gram);
        $this->assertSame('7730.00', app(GoldRateService::class)->latest24K()->rate_per_gram);
        $this->assertSame(1, GoldRate::query()->forPurity('22K')->active()->count());
        $this->assertDatabaseCount('gold_rates', 4);
    }

    public function test_previous_days_keep_their_own_active_closing_rate(): void
    {
        GoldRate::factory()->rate(6950)->on('2026-10-05', '15:00:00')->create();
        GoldRate::factory()->purity(GoldPurity::K24)->rate(7600)->on('2026-10-05', '15:00:00')->create();

        $this->submit('09:00', '7000', '7650');

        $this->assertSame(2, GoldRate::query()->forPurity('22K')->active()->count()); // one per day
        $this->assertSame('7000.00', GoldRate::latestFor('22K')->rate_per_gram);
    }

    public function test_an_admin_deactivated_rate_is_not_revived(): void
    {
        $mistake = GoldRate::factory()->rate(9000)->on('2026-10-06', '14:00:00')->create();
        $mistake->update(['status' => false]);

        $this->submit('10:00', '7000', '7650');

        $this->assertFalse($mistake->fresh()->status);
        $this->assertSame('7000.00', GoldRate::latestFor('22K')->rate_per_gram);
    }

    // 14–16. Homepage + preview label ----------------------------------------------

    public function test_homepage_displays_latest_admin_rate(): void
    {
        $this->submit('15:00', '7100', '7750');

        $section = $this->homepageRateSection();

        $this->assertStringContainsString('₹7,100', $section);
        $this->assertStringContainsString('₹71,000', $section);
        $this->assertStringContainsString('₹7,750', $section);
        $this->assertStringContainsString('₹77,500', $section);
    }

    public function test_preview_label_appears_for_development_seed_and_disappears_for_manual_rate(): void
    {
        GoldRate::factory()->development()->rate(7000)->on('2026-10-06', '09:30:00')->create();
        GoldRate::factory()->development()->purity(GoldPurity::K24)->rate(7650)->on('2026-10-06', '09:30:00')->create();

        $this->assertStringContainsString('data-sample-notice', $this->homepageRateSection());

        $this->submit('10:00', '7020', '7670')->assertSessionHasNoErrors();

        $section = $this->homepageRateSection();
        $this->assertStringNotContainsString('data-sample-notice', $section);
        $this->assertStringContainsString('₹7,020', $section);

        // The development rows are kept (history), just no longer the active rate.
        $this->assertSame(2, GoldRate::query()->where('source', GoldRate::SOURCE_DEVELOPMENT)->count());
    }

    // 17. History -----------------------------------------------------------------------

    public function test_history_contains_all_previous_rates_and_can_be_filtered(): void
    {
        GoldRate::factory()->rate(6900)->on('2026-10-01', '09:30:00')->create();
        GoldRate::factory()->purity(GoldPurity::K24)->rate(7550)->on('2026-10-01', '09:30:00')->create();
        $this->submit('09:00', '7000', '7650');
        $this->submit('12:00', '7050', '7700');

        $all = $this->actingAs($this->admin)->get(route('admin.gold-rates.history'))->assertOk();
        $this->assertSame(3, substr_count($all->getContent(), 'data-rate-update'));
        $all->assertSee('₹6,900')->assertSee('₹7,000')->assertSee('₹7,050');

        $filtered = $this->get(route('admin.gold-rates.history', ['from' => '2026-10-06', 'to' => '2026-10-06']))->assertOk();
        $this->assertSame(2, substr_count($filtered->getContent(), 'data-rate-update'));
        $filtered->assertDontSee('₹6,900');

        $this->get(route('admin.gold-rates.history', ['from' => '2026-10-06', 'to' => '2026-10-01']))
            ->assertSessionHasErrors('to');
    }

    public function test_history_is_paginated(): void
    {
        foreach (range(1, 25) as $i) {
            $date = Carbon::parse('2026-09-01')->addDays($i)->toDateString();
            GoldRate::factory()->rate(6800 + $i)->on($date)->create();
            GoldRate::factory()->purity(GoldPurity::K24)->rate(7400 + $i)->on($date)->create();
        }

        $page1 = $this->actingAs($this->admin)->get(route('admin.gold-rates.history'))->assertOk();
        $this->assertSame(20, substr_count($page1->getContent(), 'data-rate-update'));
        $page1->assertSee('25 updates');

        $page2 = $this->get(route('admin.gold-rates.history', ['page' => 2]))->assertOk();
        $this->assertSame(5, substr_count($page2->getContent(), 'data-rate-update'));
    }

    // 18–19. No edit / delete -----------------------------------------------------------

    public function test_existing_rates_cannot_be_deleted(): void
    {
        $this->submit('09:00', '7000', '7650');
        $rate = GoldRate::query()->first();

        // No delete route exists at all.
        $this->actingAs($this->admin)->delete(route('admin.gold-rates.index').'/'.$rate->id)->assertNotFound();
        $this->actingAs($this->admin)->delete(route('admin.gold-rates.store'))->assertStatus(405);

        $this->expectException(LogicException::class);
        $rate->delete();
    }

    public function test_existing_rates_cannot_be_edited(): void
    {
        $this->submit('09:00', '7000', '7650');
        $rate = GoldRate::query()->first();

        // No edit/update routes exist at all.
        $this->actingAs($this->admin)->put(route('admin.gold-rates.index').'/'.$rate->id, ['rate_22k' => 1])->assertNotFound();
        $this->actingAs($this->admin)->put(route('admin.gold-rates.store'), ['rate_22k' => 1])->assertStatus(405);
        $this->actingAs($this->admin)->get(route('admin.gold-rates.index').'/'.$rate->id.'/edit')->assertNotFound();
        $this->assertSame('7000.00', $rate->fresh()->rate_per_gram);

        $this->expectException(LogicException::class);
        $rate->update(['rate_per_gram' => 1]);
    }

    // 20. Dashboard ---------------------------------------------------------------------

    public function test_dashboard_shows_latest_rate_and_links_to_management(): void
    {
        $this->submit('09:00', '7000', '7650');
        $this->submit('15:00', '7100', '7750');

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('₹7,100')
            ->assertSee('₹7,750')
            ->assertSee('06 Oct 2026, 3:00 PM')
            ->assertSee('href="'.route('admin.gold-rates.index').'"', false)
            ->assertDontSee('₹7,000');
    }

    // 21. Atomic update -------------------------------------------------------------------

    public function test_failed_update_rolls_back_completely(): void
    {
        $this->submit('09:00', '7000', '7650');
        $before = GoldRate::query()->orderBy('id')->get(['id', 'status', 'rate_per_gram'])->toArray();

        // Simulate a database failure while writing the 24K record (after 22K was written).
        GoldRate::creating(function (GoldRate $rate) {
            if ($rate->purity === GoldPurity::K24) {
                throw new RuntimeException('Simulated failure');
            }
        });

        try {
            app(GoldRateUpdateService::class)->record(Carbon::parse('2026-10-06'), '12:00', ['22K' => 7050, '24K' => 7700]);
            $this->fail('The update should have failed.');
        } catch (RuntimeException) {
            // expected
        }

        // Nothing new, and the previous active rate was not deactivated.
        $this->assertSame($before, GoldRate::query()->orderBy('id')->get(['id', 'status', 'rate_per_gram'])->toArray());
        $this->assertSame('7000.00', GoldRate::latestFor('22K')->rate_per_gram);
    }

    public function test_invalid_24k_means_neither_rate_is_saved(): void
    {
        $this->submit('09:00', '7000', '7650');

        $this->submit('12:00', '7050', '-5')->assertSessionHasErrors('rate_24k');

        $this->assertDatabaseCount('gold_rates', 2);
        $this->assertSame('7000.00', GoldRate::latestFor('22K')->rate_per_gram);
    }

    // Security -------------------------------------------------------------------------------

    public function test_rate_update_writes_nothing_to_the_log(): void
    {
        Log::spy();

        $this->submit('09:00', '7000', '7650');

        Log::shouldNotHaveReceived('info');
        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('error');
    }

    public function test_store_route_is_csrf_protected(): void
    {
        $this->assertContains('web', app('router')->getRoutes()->getByName('admin.gold-rates.store')->gatherMiddleware());
        $this->assertContains('admin', app('router')->getRoutes()->getByName('admin.gold-rates.store')->gatherMiddleware());
    }
}
