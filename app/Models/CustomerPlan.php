<?php

namespace App\Models;

use App\Enums\ContributionFrequency;
use App\Enums\CustomerPlanStatus;
use App\Enums\OutputType;
use App\Enums\SchemeType;
use App\Enums\TenureUnit;
use App\Models\Concerns\HasSchemeTerms;
use App\Support\Money;
use App\Support\SchemeTerms;
use Database\Factories\CustomerPlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A customer's enrolment in a plan. A user may hold many of these at once.
 *
 * Extends Pivot so it can back User::plans() / Plan::customers(), while still
 * being a first-class model with its own auto-incrementing id.
 *
 * @property int $id
 * @property string $scheme_number
 * @property Carbon $start_date
 * @property Carbon|null $maturity_date
 * @property string $monthly_amount
 * @property int $duration_months
 * @property string $total_commitment
 * @property CustomerPlanStatus $status
 */
class CustomerPlan extends Pivot
{
    /** @use HasFactory<CustomerPlanFactory> */
    use HasFactory;
    use HasSchemeTerms;

    public const SCHEME_PREFIX = 'SSJ';

    protected $table = 'customer_plans';

    public $incrementing = true;

    protected $fillable = [
        'user_id',
        'plan_id',
        'scheme_number',
        'start_date',
        'maturity_date',
        'monthly_amount',
        'duration_months',
        'total_commitment',
        'status',
        'installments_total',
        ...self::SCHEME_TERM_ATTRIBUTES,
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'maturity_date' => 'date',
            'monthly_amount' => 'decimal:2',
            'duration_months' => 'integer',
            'total_commitment' => 'decimal:2',
            'status' => CustomerPlanStatus::class,
            'installments_total' => 'integer',
            ...$this->schemeTermCasts(),
        ];
    }

    protected static function booted(): void
    {
        // A single "saving" handler: Eloquent fires "saving" BEFORE "creating",
        // so the enrolment snapshot must happen here, ahead of the totals.
        static::saving(function (CustomerPlan $customerPlan) {
            if (! $customerPlan->exists) {
                $customerPlan->snapshotTerms();
            }

            // Existing schemes keep their stored terms; the total is re-derived from them
            // (for every pre-existing scheme: monthly_amount × duration_months, as before).
            $customerPlan->total_commitment = $customerPlan->installments_total === null
                ? null
                : Money::multiply($customerPlan->monthly_amount, $customerPlan->installments_total);
        });
    }

    /**
     * Copy the plan's terms at enrolment so later plan edits never change this
     * scheme. Values given explicitly win; a scheme created the original way
     * (monthly_amount / duration_months only) is a fixed monthly scheme.
     */
    private function snapshotTerms(): void
    {
        if ($this->monthly_amount !== null && $this->minimum_amount === null) {
            $this->minimum_amount = $this->monthly_amount;
            $this->maximum_amount = $this->monthly_amount;
        }
        if ($this->duration_months !== null && $this->tenure_unit === null) {
            $this->tenure_unit = TenureUnit::Months;
            $this->maximum_tenure ??= $this->duration_months;
        }

        if ($this->plan_id && ($plan = Plan::query()->find($this->plan_id))) {
            if ($this->minimum_amount === null) {
                $this->minimum_amount = $plan->minimum_amount ?? $plan->monthly_amount;
                $this->maximum_amount = $plan->hasUnlimitedMaximum() ? null : $plan->maximum_amount;
            }
            if ($this->tenure_unit === null) {
                $this->tenure_unit = $plan->tenureUnit();
                $this->maximum_tenure = $plan->maximum_tenure ?? $plan->duration_months;
            }
            $this->scheme_type ??= $plan->schemeType();
            $this->contribution_frequency ??= $plan->contributionFrequency();
            $this->output_type ??= $plan->outputType();
            if ($this->bonus_applicable === null) {
                $this->bonus_applicable = (bool) $plan->bonus_applicable;
                $this->bonus_type = $plan->bonus_type;
                $this->bonus_value = $plan->bonus_value;
            }
            $this->early_exit_allowed ??= (bool) $plan->early_exit_allowed;
        }

        $this->scheme_type ??= SchemeType::Saving;
        $this->contribution_frequency ??= ContributionFrequency::Monthly;
        $this->tenure_unit ??= TenureUnit::Months;
        $this->output_type ??= OutputType::Gold;
        $this->bonus_applicable ??= false;
        $this->early_exit_allowed ??= false;
        if (! $this->tenure_unit->isLimited()) {
            $this->maximum_tenure = null;
        }

        $this->monthly_amount ??= $this->minimum_amount;
        $this->duration_months = $this->tenure_unit === TenureUnit::Months ? $this->maximum_tenure : null;
        $this->start_date ??= Carbon::today();
        $this->scheme_number ??= static::generateSchemeNumber();
        $this->installments_total ??= SchemeTerms::installmentCount($this->start_date, $this->contribution_frequency, $this->tenure_unit, $this->maximum_tenure);
        $this->maturity_date ??= SchemeTerms::maturityDate($this->start_date, $this->tenure_unit, $this->maximum_tenure);
    }

    /** Gold is credited on payment only for Gold-output schemes (every pre-existing scheme). */
    public function creditsGold(): bool
    {
        return $this->outputType()->creditsGold();
    }

    /** "3 of 11" or, for open-ended schemes, "3". */
    public function installmentsLabel(int $paid): string
    {
        return $this->installments_total === null ? (string) $paid : $paid.' of '.$this->installments_total;
    }

    /**
     * Unique, human-friendly scheme number, e.g. SSJ-2610-7KQ2M9.
     */
    public static function generateSchemeNumber(): string
    {
        do {
            $number = sprintf('%s-%s-%s', self::SCHEME_PREFIX, now()->format('ym'), Str::upper(Str::random(6)));
        } while (static::query()->where('scheme_number', $number)->exists());

        return $number;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'customer_plan_id');
    }

    public function goldTransactions(): HasMany
    {
        return $this->hasMany(GoldTransaction::class, 'customer_plan_id');
    }

    public function scopeStatus(Builder $query, CustomerPlanStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(fn ($s) => $s->value, CustomerPlanStatus::open()));
    }
}
