<?php

namespace App\Enums;

/**
 * Bonus configured on a scheme. Configuration only — it is never credited on
 * an installment payment; see SchemeBonusService (projection for maturity).
 *
 *   Percentage  — % of the total successfully paid (2 decimals, ≤ 100)
 *   FixedAmount — rupees (2 decimals)
 *   Gold        — grams (6 decimals: the gold ledger precision)
 */
enum BonusType: string
{
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';
    case Gold = 'gold';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Percentage',
            self::FixedAmount => 'Fixed Amount',
            self::Gold => 'Gold',
        };
    }

    public function decimals(): int
    {
        return $this === self::Gold ? 6 : 2;
    }

    public function maxValue(): int
    {
        return match ($this) {
            self::Percentage => 100,
            self::FixedAmount => 10000000,
            self::Gold => 1000,
        };
    }

    /** "5%", "₹1,000", "1.5 g" */
    public function format(string $value): string
    {
        $trimmed = str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;

        return match ($this) {
            self::Percentage => $trimmed.'%',
            self::FixedAmount => \App\Support\Money::inr($value),
            self::Gold => $trimmed.' g',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_column(array_map(fn (self $c) => ['v' => $c->value, 'l' => $c->label()], self::cases()), 'l', 'v');
    }
}
