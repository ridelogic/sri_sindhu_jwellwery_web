<?php

namespace App\Models\Concerns;

use App\Enums\BonusType;
use App\Enums\ContributionFrequency;
use App\Enums\OutputType;
use App\Enums\SchemeType;
use App\Enums\TenureUnit;
use App\Support\Money;

/**
 * Scheme configuration shared by Plan (the catalogue) and CustomerPlan (the
 * customer's snapshot taken at enrolment).
 *
 * @property SchemeType|null $scheme_type
 * @property TenureUnit|null $tenure_unit
 * @property int|null $maximum_tenure
 * @property string|null $minimum_amount
 * @property string|null $maximum_amount null = no upper limit
 * @property OutputType|null $output_type
 * @property bool|null $bonus_applicable
 * @property bool|null $early_exit_allowed
 */
trait HasSchemeTerms
{
    public const SCHEME_TERM_ATTRIBUTES = [
        'scheme_type', 'contribution_frequency', 'tenure_unit', 'maximum_tenure', 'minimum_amount', 'maximum_amount',
        'output_type', 'bonus_applicable', 'bonus_type', 'bonus_value', 'early_exit_allowed',
    ];

    /** @return array<string, string> */
    protected function schemeTermCasts(): array
    {
        return [
            'scheme_type' => SchemeType::class,
            'contribution_frequency' => ContributionFrequency::class,
            'bonus_type' => BonusType::class,
            'tenure_unit' => TenureUnit::class,
            'maximum_tenure' => 'integer',
            'minimum_amount' => 'decimal:2',
            'maximum_amount' => 'decimal:2',
            'output_type' => OutputType::class,
            'bonus_applicable' => 'boolean',
            'early_exit_allowed' => 'boolean',
        ];
    }

    public function contributionFrequency(): ContributionFrequency
    {
        return $this->contribution_frequency ?? ContributionFrequency::Monthly;
    }

    /** "Monthly Contribution" (fixed amount), "Daily Amount" (a range), "Contribution" (no schedule). */
    public function contributionLabel(): string
    {
        $frequency = $this->contributionFrequency();
        $noun = $this->isFixedAmount() ? 'Contribution' : 'Amount';

        return $frequency->isScheduled() ? $frequency->label().' '.$noun : 'Contribution '.$noun;
    }

    /** "5%", "₹1,000", "1 g" — or "No bonus". */
    public function bonusLabel(): string
    {
        if (! $this->bonus_applicable || $this->bonus_type === null || $this->bonus_value === null) {
            return $this->bonus_applicable ? 'Yes' : 'No bonus';
        }

        return $this->bonus_type->format((string) $this->bonus_value).' ('.$this->bonus_type->label().')';
    }

    public function tenureUnit(): TenureUnit
    {
        return $this->tenure_unit ?? TenureUnit::Months;
    }

    public function outputType(): OutputType
    {
        return $this->output_type ?? OutputType::Gold;
    }

    public function schemeType(): SchemeType
    {
        return $this->scheme_type ?? SchemeType::Saving;
    }

    public function hasUnlimitedTenure(): bool
    {
        return ! $this->tenureUnit()->isLimited();
    }

    /** "11 months", "90 days", "5 years", "Unlimited" */
    public function tenureLabel(): string
    {
        return $this->tenureUnit()->describe($this->maximum_tenure);
    }

    public function hasUnlimitedMaximum(): bool
    {
        return $this->maximum_amount === null;
    }

    /** One fixed amount per installment (every existing plan). */
    public function isFixedAmount(): bool
    {
        return $this->maximum_amount !== null && Money::toPaise($this->maximum_amount) === Money::toPaise($this->minimumAmount());
    }

    public function minimumAmount(): string
    {
        return (string) ($this->minimum_amount ?? $this->monthly_amount);
    }

    /** "₹5,000" (fixed), "₹500 – ₹50,000", "From ₹500" (no upper limit). */
    public function amountRangeLabel(): string
    {
        if ($this->isFixedAmount()) {
            return Money::inr($this->minimumAmount());
        }

        return $this->hasUnlimitedMaximum()
            ? 'From '.Money::inr($this->minimumAmount())
            : Money::inr($this->minimumAmount()).' – '.Money::inr($this->maximum_amount);
    }

    public function maximumAmountLabel(): string
    {
        return $this->hasUnlimitedMaximum() ? 'Unlimited' : Money::inr($this->maximum_amount);
    }
}
