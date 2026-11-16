<?php

namespace App\Services;

use App\Enums\BonusType;
use App\Models\CustomerPlan;

/**
 * Bonus projection from a scheme's OWN snapshot (bonus_type / bonus_value).
 *
 * Configuration foundation only: the bonus is payable at maturity, and NOTHING
 * here is called from installment payments — no bonus is ever credited
 * automatically. Settlement/payout processing is a later stage and must use
 * this projection rather than re-deriving the rules.
 *
 * Exact arithmetic: money in integer paise, gold in integer micrograms;
 * percentages are truncated to the paisa (never rounded up).
 */
class SchemeBonusService
{
    public function __construct(private GoldCalculationService $gold) {}

    /**
     * @return array{type: BonusType, amount_paise: ?int, gold_micrograms: ?int}|null null when no bonus applies
     */
    public function projected(CustomerPlan $scheme, int $paidPaise): ?array
    {
        if (! $scheme->bonus_applicable || $scheme->bonus_type === null || $scheme->bonus_value === null) {
            return null;
        }

        $value = (string) $scheme->bonus_value;

        return match ($scheme->bonus_type) {
            // value is a percentage with up to 2 decimals → hundredths of a percent
            BonusType::Percentage => ['type' => BonusType::Percentage, 'amount_paise' => intdiv($paidPaise * $this->hundredths($value), 10000), 'gold_micrograms' => null],
            BonusType::FixedAmount => ['type' => BonusType::FixedAmount, 'amount_paise' => $this->hundredths($value), 'gold_micrograms' => null],
            BonusType::Gold => ['type' => BonusType::Gold, 'amount_paise' => null, 'gold_micrograms' => $this->gold->toMicrograms($this->trimmed($value))],
        };
    }

    /** "5.250000" → 525 (exactly; validation allows at most 2 decimals for these types). */
    private function hundredths(string $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ((int) $whole) * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function trimmed(string $value): string
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return $fraction === '' ? $whole : $whole.'.'.substr($fraction, 0, GoldCalculationService::GRAM_DECIMALS);
    }
}
