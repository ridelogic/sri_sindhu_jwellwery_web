<?php

namespace App\Services;

use App\Enums\GoldPurity;
use App\Models\GoldRate;
use App\Support\Money;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use OverflowException;

/**
 * Exact gold arithmetic. No floating point is used anywhere:
 *
 *   money  → integer paise
 *   gold   → integer micrograms (1 g = 1,000,000 µg), stored as DECIMAL(14,6) grams
 *
 * grams = amount ÷ rate, truncated (never rounded up) to 6 decimal places,
 * so a customer is never credited gold that was not paid for.
 */
class GoldCalculationService
{
    public const GRAM_DECIMALS = 6;

    public const MICROGRAMS_PER_GRAM = 1_000_000;

    public function purity(): GoldPurity
    {
        return GoldPurity::from(config('payments.gold_purity', GoldPurity::K22->value));
    }

    /**
     * The active rate in effect at a moment — the rate a payment made then is credited at.
     */
    public function rateAt(Carbon $moment, ?GoldPurity $purity = null): ?GoldRate
    {
        return GoldRate::effectiveAt($purity ?? $this->purity(), $moment);
    }

    /** @return int micrograms of gold bought by $amountPaise at $ratePaisePerGram */
    public function microgramsFor(int $amountPaise, int $ratePaisePerGram): int
    {
        if ($amountPaise <= 0 || $ratePaisePerGram <= 0) {
            throw new InvalidArgumentException('Amount and gold rate must be positive.');
        }

        if ($amountPaise > intdiv(PHP_INT_MAX, self::MICROGRAMS_PER_GRAM)) {
            throw new OverflowException('Amount is too large for exact gold calculation.');
        }

        return intdiv($amountPaise * self::MICROGRAMS_PER_GRAM, $ratePaisePerGram);
    }

    /** Gold grams as a 6-decimal string, e.g. ₹5,000 at ₹12,500/g → "0.400000". */
    public function gramsFor(int $amountPaise, int $ratePaisePerGram): string
    {
        return $this->formatMicrograms($this->microgramsFor($amountPaise, $ratePaisePerGram));
    }

    public function formatMicrograms(int $micrograms): string
    {
        $sign = $micrograms < 0 ? '-' : '';
        $micrograms = abs($micrograms);

        return $sign.intdiv($micrograms, self::MICROGRAMS_PER_GRAM).'.'
            .str_pad((string) ($micrograms % self::MICROGRAMS_PER_GRAM), self::GRAM_DECIMALS, '0', STR_PAD_LEFT);
    }

    /** "0.4", "0.400000", "12" → micrograms, exactly. */
    public function toMicrograms(int|string $grams): int
    {
        $value = trim((string) $grams);

        if (! preg_match('/^(\d+)(?:\.(\d{1,6}))?$/', $value, $m)) {
            throw new InvalidArgumentException('Invalid gold quantity.');
        }

        return ((int) $m[1]) * self::MICROGRAMS_PER_GRAM + (int) str_pad($m[2] ?? '0', self::GRAM_DECIMALS, '0');
    }

    /**
     * Sum of gram strings, exactly.
     *
     * @param  iterable<string>  $grams
     */
    public function sumGrams(iterable $grams): string
    {
        $total = 0;
        foreach ($grams as $value) {
            $total += $this->toMicrograms($value);
        }

        return $this->formatMicrograms($total);
    }

    /** Value in paise of $grams at $ratePaisePerGram (truncated to the paisa). */
    public function valuePaise(string $grams, int $ratePaisePerGram): int
    {
        return intdiv($this->toMicrograms($grams) * $ratePaisePerGram, self::MICROGRAMS_PER_GRAM);
    }

    public function ratePaise(GoldRate $rate): int
    {
        return Money::toPaise($rate->rate_per_gram);
    }
}
