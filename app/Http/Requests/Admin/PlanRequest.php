<?php

namespace App\Http\Requests\Admin;

use App\Enums\BonusType;
use App\Enums\ContributionFrequency;
use App\Enums\OutputType;
use App\Enums\SchemeType;
use App\Enums\TenureUnit;
use App\Models\Plan;
use App\Support\Money;
use App\Support\SchemeTerms;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create / update a gold saving plan.
 *
 * The admin form posts the full configuration (scheme type, tenure unit and
 * maximum tenure, minimum / maximum amount, output, bonus, early exit).
 *
 * The original payload — monthly_amount + duration_months, a fixed monthly
 * plan — is still accepted unchanged and validated exactly as before.
 *
 * total_amount is NOT accepted — the model always derives it (exact paise arithmetic).
 */
class PlanRequest extends FormRequest
{
    public const MAX_MONTHLY = 10000000; // ₹1 crore per installment — sanity ceiling

    public const MAX_DURATION = 120;     // months

    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    /** The original fixed-monthly payload (no configuration fields). */
    public function isFixedMonthlyPayload(): bool
    {
        return $this->has('monthly_amount') && ! $this->has('minimum_amount');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::lower(trim((string) $this->input('slug'))),
        ]);

        if (! $this->isFixedMonthlyPayload()) {
            // Never trust disabled/hidden inputs: unlimited tenure has no maximum tenure,
            // and an unlimited maximum amount has no value.
            if ($this->input('tenure_unit') === TenureUnit::Unlimited->value) {
                $this->merge(['maximum_tenure' => null]);
            }
            if ($this->boolean('maximum_amount_unlimited')) {
                $this->merge(['maximum_amount' => null]);
            }
            // No bonus → bonus type and value are ignored.
            if (! $this->boolean('bonus_applicable')) {
                $this->merge(['bonus_type' => null, 'bonus_value' => null]);
            }
        }
    }

    public function rules(): array
    {
        $plan = $this->route('plan');

        $common = [
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required', 'string', 'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('plans', 'slug')->ignore($plan?->id),
            ],
            'tagline' => ['nullable', 'string', 'max:160'],
            'status' => ['required', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ];

        if ($this->isFixedMonthlyPayload()) {
            return $common + [
                'monthly_amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:'.self::MAX_MONTHLY],
                'duration_months' => ['required', 'integer', 'min:1', 'max:'.self::MAX_DURATION],
            ];
        }

        $unit = TenureUnit::tryFrom((string) $this->input('tenure_unit'));
        $bonusType = BonusType::tryFrom((string) $this->input('bonus_type'));
        $bonus = $this->boolean('bonus_applicable');

        return $common + [
            'scheme_type' => ['required', Rule::enum(SchemeType::class)],
            'contribution_frequency' => ['required', Rule::enum(ContributionFrequency::class)],
            'tenure_unit' => ['required', Rule::enum(TenureUnit::class)],
            'maximum_tenure' => $unit === TenureUnit::Unlimited
                ? ['nullable']
                : ['required', 'integer', 'min:1', 'max:'.($unit?->maxValue() ?? self::MAX_DURATION)],
            'minimum_amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:'.self::MAX_MONTHLY],
            'maximum_amount_unlimited' => ['sometimes', 'boolean'],
            'maximum_amount' => $this->boolean('maximum_amount_unlimited')
                ? ['nullable']
                : ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:'.self::MAX_MONTHLY],
            'output_type' => ['required', Rule::enum(OutputType::class)],
            'bonus_applicable' => ['required', 'boolean'],
            'bonus_type' => $bonus ? ['required', Rule::enum(BonusType::class)] : ['nullable'],
            'bonus_value' => $bonus
                ? ['required', 'numeric', 'decimal:0,'.($bonusType?->decimals() ?? 2), 'gt:0', 'max:'.($bonusType?->maxValue() ?? 100)]
                : ['nullable'],
            'early_exit_allowed' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter the plan name.',
            'slug.required' => 'Please enter a URL slug, e.g. gold-basic.',
            'slug.regex' => 'The slug may contain only lowercase letters, numbers and single hyphens, e.g. gold-basic.',
            'slug.unique' => 'Another plan already uses this slug.',
            'monthly_amount.required' => 'Please enter the monthly contribution.',
            'monthly_amount.numeric' => 'The monthly contribution must be a number, e.g. 5000.00.',
            'monthly_amount.decimal' => 'The monthly contribution can have at most 2 decimal places (paise).',
            'monthly_amount.gt' => 'The monthly contribution must be greater than zero.',
            'monthly_amount.max' => 'The monthly contribution looks too high — please check the amount.',
            'duration_months.required' => 'Please enter the duration in months.',
            'duration_months.integer' => 'The duration must be a whole number of months.',
            'duration_months.min' => 'The duration must be at least 1 month.',
            'duration_months.max' => 'The duration cannot be more than '.self::MAX_DURATION.' months.',
            'scheme_type.required' => 'Please choose the scheme type.',
            'scheme_type.enum' => 'Please choose Saving or Exchange.',
            'tenure_unit.required' => 'Please choose the plan tenure unit.',
            'tenure_unit.enum' => 'Please choose Days, Weeks, Months, Years or Unlimited.',
            'maximum_tenure.required' => 'Please enter the maximum tenure.',
            'maximum_tenure.integer' => 'The maximum tenure must be a whole number.',
            'maximum_tenure.min' => 'The maximum tenure must be greater than 0.',
            'maximum_tenure.max' => 'The maximum tenure cannot be more than :max '.($this->input('tenure_unit') ?: 'units').'.',
            'minimum_amount.required' => 'Please enter the minimum amount.',
            'minimum_amount.numeric' => 'The minimum amount must be a number, e.g. 500.00.',
            'minimum_amount.decimal' => 'The minimum amount can have at most 2 decimal places (paise).',
            'minimum_amount.gt' => 'The minimum amount must be greater than zero.',
            'minimum_amount.max' => 'The minimum amount looks too high — please check the amount.',
            'maximum_amount.required' => 'Please enter the maximum amount, or choose Unlimited.',
            'maximum_amount.numeric' => 'The maximum amount must be a number, e.g. 50000.00.',
            'maximum_amount.decimal' => 'The maximum amount can have at most 2 decimal places (paise).',
            'maximum_amount.gt' => 'The maximum amount must be greater than zero.',
            'maximum_amount.max' => 'The maximum amount looks too high — please check the amount.',
            'output_type.required' => 'Please choose the output.',
            'output_type.enum' => 'Please choose Money, Gold or Anyone.',
            'bonus_applicable.required' => 'Please choose whether a bonus applies.',
            'contribution_frequency.required' => 'Please choose the contribution frequency.',
            'contribution_frequency.enum' => 'Please choose Daily, Weekly, Monthly, Yearly or Unlimited.',
            'bonus_type.required' => 'Please choose the bonus type.',
            'bonus_type.enum' => 'Please choose Percentage, Fixed Amount or Gold.',
            'bonus_value.required' => 'Please enter the bonus value.',
            'bonus_value.numeric' => 'The bonus value must be a number.',
            'bonus_value.decimal' => 'The bonus value can have at most :decimal decimal places for this bonus type.',
            'bonus_value.gt' => 'The bonus value must be greater than zero.',
            'bonus_value.max' => match (BonusType::tryFrom((string) $this->input('bonus_type'))) {
                BonusType::Percentage => 'A percentage bonus cannot be more than 100%.',
                BonusType::Gold => 'A gold bonus cannot be more than 1,000 grams.',
                default => 'The bonus value looks too high — please check it.',
            },
            'early_exit_allowed.required' => 'Please choose whether early exit is allowed.',
            'status.required' => 'Please choose whether the plan is active.',
            'sort_order.integer' => 'The sort order must be a whole number.',
            'sort_order.min' => 'The sort order cannot be negative.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var Plan|null $plan */
                $plan = $this->route('plan');

                // Once customers have joined, the public URL (slug) is locked.
                if ($plan?->isSlugLocked() && $this->input('slug') !== $plan->slug) {
                    $validator->errors()->add('slug', 'This plan has customer enrolments, so its URL slug cannot be changed.');
                }

                // Maximum amount must not be below the minimum (compared exactly, in paise).
                if (! $this->isFixedMonthlyPayload() && ! $validator->errors()->hasAny(['minimum_amount', 'maximum_amount'])
                    && $this->input('maximum_amount') !== null
                    && Money::toPaise((string) $this->input('maximum_amount')) < Money::toPaise((string) $this->input('minimum_amount'))) {
                    $validator->errors()->add('maximum_amount', 'The maximum amount must be greater than or equal to the minimum amount.');
                }

                // A contribution period cannot be longer than the whole tenure (e.g. Yearly for 90 days).
                $frequency = ContributionFrequency::tryFrom((string) $this->input('contribution_frequency'));
                $unit = TenureUnit::tryFrom((string) $this->input('tenure_unit'));
                if (! $this->isFixedMonthlyPayload() && $frequency && $unit && ! $validator->errors()->has('maximum_tenure')
                    && ! SchemeTerms::frequencyFitsTenure($frequency, $unit, $unit->isLimited() ? (int) $this->input('maximum_tenure') : null)) {
                    $validator->errors()->add('contribution_frequency', 'A '.strtolower($frequency->label()).' contribution does not fit within a tenure of '.$unit->describe((int) $this->input('maximum_tenure')).'.');
                }
            },
        ];
    }

    /**
     * Validated data mapped to model attributes (no total_amount — it is derived).
     * Description, benefits and terms are no longer edited here: they are not
     * written, so values already saved on a plan are kept unchanged.
     *
     * @return array<string, mixed>
     */
    public function planAttributes(): array
    {
        $data = $this->validated();

        $attributes = [
            'name' => trim($data['name']),
            'slug' => $data['slug'],
            'tagline' => filled($data['tagline'] ?? null) ? trim($data['tagline']) : null,
            'status' => (bool) $data['status'],
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'sort_order' => (int) $data['sort_order'],
        ];

        if ($this->isFixedMonthlyPayload()) {
            // A fixed monthly plan: exactly the original behaviour.
            return $attributes + [
                'monthly_amount' => $data['monthly_amount'],
                'duration_months' => (int) $data['duration_months'],
                'minimum_amount' => $data['monthly_amount'],
                'maximum_amount' => $data['monthly_amount'],
                'contribution_frequency' => ContributionFrequency::Monthly,
                'tenure_unit' => TenureUnit::Months,
                'maximum_tenure' => (int) $data['duration_months'],
            ];
        }

        $unit = TenureUnit::from($data['tenure_unit']);
        $bonus = (bool) $data['bonus_applicable'];

        return $attributes + [
            'scheme_type' => SchemeType::from($data['scheme_type']),
            'contribution_frequency' => ContributionFrequency::from($data['contribution_frequency']),
            'bonus_type' => $bonus ? BonusType::from($data['bonus_type']) : null,
            'bonus_value' => $bonus ? (string) $data['bonus_value'] : null,
            'tenure_unit' => $unit,
            'maximum_tenure' => $unit->isLimited() ? (int) $data['maximum_tenure'] : null,
            'minimum_amount' => $data['minimum_amount'],
            'maximum_amount' => $data['maximum_amount'] ?? null, // null = no upper limit
            'output_type' => OutputType::from($data['output_type']),
            'bonus_applicable' => (bool) $data['bonus_applicable'],
            'early_exit_allowed' => (bool) $data['early_exit_allowed'],
        ];
    }
}
