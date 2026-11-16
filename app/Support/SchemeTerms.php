<?php

namespace App\Support;

use App\Enums\ContributionFrequency;
use App\Enums\TenureUnit;
use Illuminate\Support\Carbon;

/**
 * Tenure and schedule arithmetic shared by plans and customer schemes.
 *
 *   maturity      = start + maximum tenure in its own unit (null when unlimited)
 *   installments  = contribution periods that begin before maturity
 *                   (same unit, e.g. Monthly + 11 months → exactly 11 — every
 *                   existing scheme); null when open-ended (unlimited tenure or
 *                   unlimited frequency)
 */
final class SchemeTerms
{
    public static function maturityDate(Carbon $start, TenureUnit $unit, ?int $tenure): ?Carbon
    {
        return $unit->isLimited() && $tenure ? $unit->addTo($start, $tenure) : null;
    }

    public static function installmentCount(Carbon $start, ContributionFrequency $frequency, TenureUnit $unit, ?int $tenure): ?int
    {
        if (! $unit->isLimited() || ! $tenure || ! $frequency->isScheduled()) {
            return null;
        }

        if ($frequency->matchingTenureUnit() === $unit) {
            return $tenure;
        }

        // Count whole contribution periods from the start date (anniversaries), so
        // Monthly over 5 years = 60, exactly like Monthly over 60 months.
        $maturity = self::maturityDate($start, $unit, $tenure);
        $period = $frequency->matchingTenureUnit();
        $count = 0;
        while ($period->addTo($start, $count)->lessThan($maturity)) {
            $count++;
        }

        return max(1, $count);
    }

    /** A contribution period must not be longer than the whole tenure (e.g. Yearly for 90 days). */
    public static function frequencyFitsTenure(ContributionFrequency $frequency, TenureUnit $unit, ?int $tenure, ?Carbon $start = null): bool
    {
        if (! $frequency->isScheduled() || ! $unit->isLimited() || ! $tenure) {
            return true;
        }

        $start ??= Carbon::today();

        return $frequency->matchingTenureUnit()->addTo($start, 1)->lessThanOrEqualTo(self::maturityDate($start, $unit, $tenure));
    }
}
