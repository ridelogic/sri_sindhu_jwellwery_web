<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

/**
 * How often a customer contributes — independent of the tenure, e.g.
 * "Daily contributions for 90 days". Existing schemes are Monthly.
 *
 *   Daily   — one installment per calendar day
 *   Weekly  — one installment per 7 days from the start date
 *   Monthly — one installment per calendar month (the original Stage 6 rule)
 *   Yearly  — one installment per calendar year
 *   Unlimited — no schedule: contribute any time while the scheme is open
 */
enum ContributionFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case Unlimited = 'unlimited';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function isScheduled(): bool
    {
        return $this !== self::Unlimited;
    }

    /** "day", "week", "month", "year"; null when there is no schedule. */
    public function period(): ?string
    {
        return match ($this) {
            self::Daily => 'day',
            self::Weekly => 'week',
            self::Monthly => 'month',
            self::Yearly => 'year',
            self::Unlimited => null,
        };
    }

    /** The tenure unit counted in the same periods (Monthly ↔ Months, …). */
    public function matchingTenureUnit(): ?TenureUnit
    {
        return match ($this) {
            self::Daily => TenureUnit::Days,
            self::Weekly => TenureUnit::Weeks,
            self::Monthly => TenureUnit::Months,
            self::Yearly => TenureUnit::Years,
            self::Unlimited => null,
        };
    }

    /** Start of installment $index (0-based) for a scheme starting on $start. */
    public function periodStart(Carbon $start, int $index): Carbon
    {
        $start = $start->copy()->startOfDay();

        return match ($this) {
            self::Daily => $start->addDays($index),
            self::Weekly => $start->addWeeks($index),
            // Calendar months / years, as Stage 6: the first installment is due from the start date.
            self::Monthly => $index === 0 ? $start : $start->startOfMonth()->addMonthsNoOverflow($index),
            self::Yearly => $index === 0 ? $start : $start->startOfYear()->addYears($index),
            self::Unlimited => $start,
        };
    }

    /** Installment periods that have begun by $at (including the current one). */
    public function periodsElapsed(Carbon $start, Carbon $at): int
    {
        $days = (int) floor($start->copy()->startOfDay()->diffInDays($at->copy()->startOfDay(), false));

        return match ($this) {
            self::Daily => $days + 1,
            self::Weekly => $days < 0 ? 0 : intdiv($days, 7) + 1,
            self::Monthly => ($at->year * 12 + $at->month) - ($start->year * 12 + $start->month) + 1,
            self::Yearly => $at->year - $start->year + 1,
            self::Unlimited => PHP_INT_MAX,
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_column(array_map(fn (self $c) => ['v' => $c->value, 'l' => $c->label()], self::cases()), 'l', 'v');
    }
}
