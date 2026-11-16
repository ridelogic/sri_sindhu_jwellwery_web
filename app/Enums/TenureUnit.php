<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

/**
 * Unit of a scheme's maximum tenure. Each unit is stored as chosen — days,
 * weeks and years are never converted into months. Unlimited = open-ended:
 * no maximum tenure and no maturity date.
 */
enum TenureUnit: string
{
    case Days = 'days';
    case Weeks = 'weeks';
    case Months = 'months';
    case Years = 'years';
    case Unlimited = 'unlimited';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function isLimited(): bool
    {
        return $this !== self::Unlimited;
    }

    /** Largest maximum tenure accepted for this unit (10 years). */
    public function maxValue(): ?int
    {
        return match ($this) {
            self::Days => 3650,
            self::Weeks => 520,
            self::Months => 120,
            self::Years => 10,
            self::Unlimited => null,
        };
    }

    /** "11 months", "1 day", "5 years", "Unlimited" */
    public function describe(?int $value): string
    {
        if (! $this->isLimited() || $value === null) {
            return 'Unlimited';
        }

        return $value.' '.($value === 1 ? rtrim($this->value, 's') : $this->value);
    }

    /**
     * Calendar arithmetic per unit — never a fixed month length.
     * Months/years do not overflow (31 Jan + 1 month = 28/29 Feb).
     */
    public function addTo(Carbon $date, int $value): ?Carbon
    {
        $date = $date->copy();

        return match ($this) {
            self::Days => $date->addDays($value),
            self::Weeks => $date->addWeeks($value),
            self::Months => $date->addMonthsNoOverflow($value),
            self::Years => $date->addYearsNoOverflow($value),
            self::Unlimited => null,
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_column(array_map(fn (self $c) => ['v' => $c->value, 'l' => $c->label()], self::cases()), 'l', 'v');
    }
}
