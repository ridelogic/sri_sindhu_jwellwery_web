<?php

namespace App\Enums;

/**
 * What a scheme's contributions accumulate as.
 *
 *   Gold   — every successful payment buys gold at the historical rate in effect
 *            at payment time (Stage 6 gold ledger). Existing plans are Gold.
 *   Money  — payments are recorded as money only; no gold is credited.
 *            Settlement at maturity is a later stage.
 *   Anyone — money or gold, decided at settlement. Stored and displayed only;
 *            payments are recorded as money and no gold is credited automatically.
 */
enum OutputType: string
{
    case Money = 'money';
    case Gold = 'gold';
    case Anyone = 'anyone';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Only Gold schemes credit gold on payment. */
    public function creditsGold(): bool
    {
        return $this === self::Gold;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_column(array_map(fn (self $c) => ['v' => $c->value, 'l' => $c->label()], self::cases()), 'l', 'v');
    }
}
