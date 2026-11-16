<?php

namespace Tests\Unit\Services;

use App\Services\GoldCalculationService;
use App\Support\Money;
use InvalidArgumentException;
use OverflowException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pure arithmetic — no framework, no database, no floating point.
 */
class GoldCalculationServiceTest extends TestCase
{
    private GoldCalculationService $gold;

    protected function setUp(): void
    {
        $this->gold = new GoldCalculationService;
    }

    public function test_the_mandatory_example_is_exact(): void
    {
        // ₹5,000 ÷ ₹12,500/g = 0.400000 g
        $this->assertSame('0.400000', $this->gold->gramsFor(Money::toPaise('5000'), Money::toPaise('12500')));
        $this->assertSame(400000, $this->gold->microgramsFor(500000, 1250000));
    }

    /** @return array<string, array{string, string, string}> */
    public static function cases(): array
    {
        return [
            'whole gram' => ['12500', '12500', '1.000000'],
            'ten thousand at 10,000/g' => ['10000', '10000.00', '1.000000'],
            'repeating decimal truncated' => ['1000', '7000', '0.142857'],   // 0.142857142… → never rounded up
            'paise in rate' => ['5000', '12345.67', '0.405000'],              // 0.40500029…
            'paise in amount' => ['5000.50', '12500', '0.400040'],
            'large amount' => ['1000000', '6543.21', '152.830185'],           // 152.83018579…
            'tiny amount' => ['0.01', '12500', '0.000000'],
        ];
    }

    #[DataProvider('cases')]
    public function test_gold_grams_are_truncated_to_six_decimals(string $amount, string $rate, string $expected): void
    {
        $this->assertSame($expected, $this->gold->gramsFor(Money::toPaise($amount), Money::toPaise($rate)));
    }

    public function test_truncation_never_credits_more_gold_than_paid_for(): void
    {
        foreach ([[100000, 700000], [123456, 1234567], [999999, 1300000], [500000, 1250001]] as [$amount, $rate]) {
            $micrograms = $this->gold->microgramsFor($amount, $rate);

            // value of credited gold ≤ amount paid < value of credited gold + 1 µg
            $this->assertLessThanOrEqual($amount * 1_000_000, $micrograms * $rate);
            $this->assertGreaterThan($amount * 1_000_000, ($micrograms + 1) * $rate);
        }
    }

    public function test_money_arithmetic_is_exact(): void
    {
        $this->assertSame(30, Money::toPaise('0.1') + Money::toPaise('0.2'));     // 0.1 + 0.2 = 0.30 exactly
        $this->assertSame('55000.00', Money::multiply('5000', 11));
        $this->assertSame('66000.00', Money::multiply('6000', 11));
        $this->assertSame('0.30', Money::fromPaise(Money::toPaise('0.1') + Money::toPaise('0.2')));
        $this->assertSame(123456789, Money::toPaise('1234567.89'));
    }

    public function test_grams_sum_and_format_exactly(): void
    {
        $this->assertSame('0.900000', $this->gold->sumGrams(['0.400000', '0.500000']));
        $this->assertSame('1.000000', $this->gold->sumGrams(array_fill(0, 10, '0.1')));        // float would give 0.9999999…
        $this->assertSame('0.000000', $this->gold->sumGrams([]));
        $this->assertSame(1_000_001, $this->gold->toMicrograms('1.000001'));
        $this->assertSame('12.000500', $this->gold->formatMicrograms(12_000_500));
    }

    public function test_current_value_is_exact_and_truncated(): void
    {
        // 0.4 g × ₹13,000 = ₹5,200
        $this->assertSame(520000, $this->gold->valuePaise('0.400000', 1300000));
        // 0.142857 g × ₹7,000 = ₹999.999 → ₹999.99 (truncated to the paisa)
        $this->assertSame(99999, $this->gold->valuePaise('0.142857', 700000));
    }

    public function test_invalid_inputs_are_rejected(): void
    {
        foreach ([[0, 1250000], [500000, 0], [-1, 1250000]] as [$amount, $rate]) {
            try {
                $this->gold->microgramsFor($amount, $rate);
                $this->fail('Expected rejection');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(InvalidArgumentException::class);
        $this->gold->toMicrograms('0.1234567'); // more precision than stored
    }

    public function test_overflow_is_refused_rather_than_wrapping(): void
    {
        $this->expectException(OverflowException::class);
        $this->gold->microgramsFor(PHP_INT_MAX, 1250000);
    }
}
