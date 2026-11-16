<?php

namespace App\Models;

use App\Enums\ContributionFrequency;
use App\Enums\OutputType;
use App\Enums\SchemeType;
use App\Enums\TenureUnit;
use App\Models\Concerns\HasSchemeTerms;
use App\Services\GoldSavingPlanService;
use App\Support\Money;
use App\Support\SchemeTerms;
use Illuminate\Support\Carbon;
use Database\Factories\PlanFactory;
use Illuminate\Support\Facades\DB;
use LogicException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A gold saving plan offered to customers.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $monthly_amount
 * @property int $duration_months
 * @property string $total_amount
 * @property array|null $benefits
 * @property bool $status
 */
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;
    use HasSchemeTerms;

    protected $fillable = [
        'name',
        'slug',
        'tagline',
        'description',
        'monthly_amount',
        'duration_months',
        'total_amount',
        'benefits',
        'terms',
        'is_featured',
        'status',
        'sort_order',
        ...self::SCHEME_TERM_ATTRIBUTES,
    ];

    protected function casts(): array
    {
        return [
            'monthly_amount' => 'decimal:2',
            'duration_months' => 'integer',
            'total_amount' => 'decimal:2',
            'benefits' => 'array',
            'is_featured' => 'boolean',
            'status' => 'boolean',
            'sort_order' => 'integer',
            ...$this->schemeTermCasts(),
        ];
    }

    protected static function booted(): void
    {
        // Configuration and the derived compatibility columns are kept in step here,
        // so total_amount can never disagree with the installments or be entered independently.
        static::saving(fn (Plan $plan) => $plan->syncSchemeTerms());

        // Plans are archived (deactivated), never deleted, once customers have joined.
        static::deleting(function (Plan $plan) {
            if ($plan->customerPlans()->exists()) {
                throw new LogicException('This plan has customer enrolments and cannot be deleted. Deactivate it instead.');
            }
        });

        // Public pages cache plans per request; make the next read see this change.
        static::saved(fn () => app()->forgetInstance(GoldSavingPlanService::class));
    }

    /**
     * Keep the configuration and the original columns consistent.
     *
     * Plans written the original way (only monthly_amount / duration_months,
     * e.g. existing code and factories) are FIXED monthly plans: minimum =
     * maximum = monthly_amount and a tenure of duration_months months — exactly
     * their existing behaviour. Configured plans set the new fields; the
     * original columns are then derived from them.
     */
    public function syncSchemeTerms(): void
    {
        $this->scheme_type ??= SchemeType::Saving;
        $this->contribution_frequency ??= ContributionFrequency::Monthly;
        $this->tenure_unit ??= TenureUnit::Months;
        $this->output_type ??= OutputType::Gold;
        $this->bonus_applicable ??= false;
        $this->early_exit_allowed ??= false;
        if (! $this->bonus_applicable) {
            // No bonus: type and value are not stored.
            $this->bonus_type = null;
            $this->bonus_value = null;
        }

        // Original-style writes of monthly_amount / duration_months.
        if ($this->minimum_amount === null || ($this->isDirty('monthly_amount') && ! $this->isDirty('minimum_amount'))) {
            $wasFixed = $this->minimum_amount === null || $this->getOriginal('maximum_amount') === $this->getOriginal('minimum_amount');
            $this->minimum_amount = $this->monthly_amount;
            if ($wasFixed && ! $this->isDirty('maximum_amount')) {
                $this->maximum_amount = $this->monthly_amount;
            }
        }
        if ($this->tenure_unit === TenureUnit::Months && $this->duration_months !== null
            && ($this->maximum_tenure === null || ($this->isDirty('duration_months') && ! $this->isDirty('maximum_tenure')))) {
            $this->maximum_tenure = $this->duration_months;
        }
        if (! $this->tenure_unit->isLimited()) {
            $this->maximum_tenure = null;
        }

        // Original columns derived from the configuration.
        $this->monthly_amount = $this->minimum_amount;
        $this->duration_months = $this->tenure_unit === TenureUnit::Months ? $this->maximum_tenure : null;
        $installments = $this->installmentCount();
        $this->total_amount = $installments === null ? null : Money::multiply($this->monthly_amount, $installments);
    }

    /** Installments within the tenure for a scheme starting today; null when open-ended. */
    public function installmentCount(?Carbon $start = null): ?int
    {
        return SchemeTerms::installmentCount($start ?? Carbon::today(), $this->contributionFrequency(), $this->tenureUnit(), $this->maximum_tenure);
    }

    /**
     * Once a customer has joined, the slug (public URL) must not change.
     */
    public function isSlugLocked(): bool
    {
        return $this->exists && $this->customerPlans()->exists();
    }

    public function scopeWithCustomerCounts(Builder $query): Builder
    {
        return $query->withCount([
            'customerPlans as enrolments_count',
            'customerPlans as customers_count' => fn (Builder $q) => $q->select(DB::raw('COUNT(DISTINCT user_id)')),
        ]);
    }

    public function customerPlans(): HasMany
    {
        return $this->hasMany(CustomerPlan::class);
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'customer_plans')
            ->using(CustomerPlan::class)
            ->withPivot(['id', 'scheme_number', 'status', 'start_date', 'maturity_date'])
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
