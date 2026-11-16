<?php

namespace Tests\Feature\Database;

use App\Enums\GoldPurity;
use App\Models\GoldRate;
use App\Services\GoldRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use LogicException;
use Tests\TestCase;

class GoldRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_gold_rate_can_be_created_with_casts(): void
    {
        $rate = GoldRate::create([
            'rate_date' => '2026-10-06',
            'rate_time' => '09:30:00',
            'purity' => '22K',
            'rate_per_gram' => 7000,
            'source' => 'manual',
        ])->fresh();

        $this->assertInstanceOf(Carbon::class, $rate->rate_date);
        $this->assertSame('09:30:00', $rate->rate_time);
        $this->assertSame(GoldPurity::K22, $rate->purity);
        $this->assertSame('7000.00', $rate->rate_per_gram);
        $this->assertSame('70000.00', $rate->rate_per_10_gram); // derived when omitted
        $this->assertTrue($rate->status);
    }

    public function test_22k_and_24k_rates_are_stored_separately(): void
    {
        GoldRate::factory()->purity(GoldPurity::K22)->rate(7000)->create();
        GoldRate::factory()->purity(GoldPurity::K24)->rate(7650)->create();

        $this->assertSame('7000.00', GoldRate::query()->forPurity('22K')->value('rate_per_gram'));
        $this->assertSame('7650.00', GoldRate::query()->forPurity(GoldPurity::K24)->value('rate_per_gram'));
    }

    public function test_new_rates_create_new_records_and_history_is_preserved(): void
    {
        GoldRate::factory()->rate(6950)->on('2026-10-05')->create();
        GoldRate::factory()->rate(6980)->on('2026-10-06', '09:30:00')->create();
        GoldRate::factory()->rate(7000)->on('2026-10-06', '15:00:00')->create();

        $this->assertSame(3, GoldRate::query()->forPurity('22K')->count());
        $this->assertSame(['6950.00', '6980.00', '7000.00'], GoldRate::query()->orderBy('id')->pluck('rate_per_gram')->all());
    }

    public function test_rate_values_cannot_be_overwritten(): void
    {
        $rate = GoldRate::factory()->rate(7000)->create();

        try {
            $rate->update(['rate_per_gram' => 7100]);
            $this->fail('Updating a historical rate should not be allowed.');
        } catch (LogicException) {
            $this->assertSame('7000.00', $rate->fresh()->rate_per_gram);
        }
    }

    public function test_rates_cannot_be_deleted_but_can_be_deactivated(): void
    {
        $rate = GoldRate::factory()->create();

        try {
            $rate->delete();
            $this->fail('Deleting a historical rate should not be allowed.');
        } catch (LogicException) {
            $this->assertDatabaseHas('gold_rates', ['id' => $rate->id]);
        }

        $rate->update(['status' => false]);
        $this->assertFalse($rate->fresh()->status);
    }

    public function test_latest_active_rate_is_retrieved_per_purity(): void
    {
        GoldRate::factory()->rate(6950)->on('2026-10-05')->create();
        GoldRate::factory()->rate(6990)->on('2026-10-06', '09:30:00')->create();
        GoldRate::factory()->rate(7000)->on('2026-10-06', '15:00:00')->create();
        GoldRate::factory()->rate(9999)->on('2026-10-07')->inactive()->create(); // newer but inactive
        GoldRate::factory()->purity(GoldPurity::K24)->rate(7650)->on('2026-10-06')->create();

        $this->assertSame('7000.00', GoldRate::latestFor('22K')->rate_per_gram);
        $this->assertSame('7650.00', GoldRate::latestFor(GoldPurity::K24)->rate_per_gram);
        $this->assertSame('7000.00', app(GoldRateService::class)->latestForPurity('22K')->rate_per_gram);
        $this->assertNull(GoldRate::query()->forPurity('18K')->active()->first());
    }

    public function test_rate_effective_at_a_moment_uses_history(): void
    {
        GoldRate::factory()->rate(6950)->on('2026-10-05', '09:30:00')->create();
        GoldRate::factory()->rate(7000)->on('2026-10-06', '09:30:00')->create();

        $this->assertSame('6950.00', GoldRate::effectiveAt('22K', Carbon::parse('2026-10-06 08:00'))->rate_per_gram);
        $this->assertSame('7000.00', GoldRate::effectiveAt('22K', Carbon::parse('2026-10-06 10:00'))->rate_per_gram);
        $this->assertNull(GoldRate::effectiveAt('22K', Carbon::parse('2026-10-01')));
    }

    public function test_active_scope(): void
    {
        GoldRate::factory()->create();
        GoldRate::factory()->inactive()->create();

        $this->assertSame(1, GoldRate::query()->active()->count());
    }
}
