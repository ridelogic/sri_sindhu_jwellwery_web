<?php

namespace App\Services;

use App\Models\Plan;
use App\Support\Demo\DemoPlans;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Gold Saving Scheme plans and scheme-level information.
 *
 * Source of truth:
 *   1. DATABASE DATA — active rows in the `plans` table, ordered by sort_order.
 *   2. FALLBACK DEMO DATA — App\Support\Demo\DemoPlans, used ONLY when the
 *      table has no active plans or cannot be reached.
 *
 * Both are normalised into the same array shape for the views.
 *
 * Wording deliberately avoids defining financial or legal benefits — the
 * final scheme terms (bonus, redemption, missed payments, closure) are not
 * yet confirmed and must come from Sri Sindhu Jewellery.
 */
class GoldSavingPlanService
{
    /** @var list<array<string, mixed>>|null */
    private ?array $plans = null;

    private bool $usingFallback = false;

    /**
     * All public plans, enriched with computed values and URLs.
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        if ($this->plans !== null) {
            return $this->plans;
        }

        $records = $this->databasePlans();

        // Fallback ONLY when the plans table is empty/unavailable. If plans exist
        // but are all inactive, the website correctly offers no plans.
        if ($records === null) {
            $this->usingFallback = true;
            $records = $this->fallbackPlans();
        }

        return $this->plans = $this->presentAll($records);
    }

    /**
     * An ACTIVE plan by slug (plans offered to new customers).
     */
    public function find(string $slug): ?array
    {
        foreach ($this->all() as $plan) {
            if ($plan['slug'] === $slug) {
                return $plan;
            }
        }

        return null;
    }

    /**
     * A plan for its public detail page: active plans, or an INACTIVE plan so
     * existing links (and existing customers) never hit a 404. Inactive plans
     * carry is_active = false so the page can say it is closed to new customers.
     */
    public function findForDisplay(string $slug): ?array
    {
        if ($active = $this->find($slug)) {
            return $active;
        }

        if ($this->isUsingFallback()) {
            return null;
        }

        try {
            $plan = Plan::query()->where('slug', $slug)->first();
        } catch (QueryException) {
            return null;
        }

        return $plan ? $this->present($this->fromModel($plan), 0) : null;
    }

    /**
     * Plans a customer can actually join: active DATABASE plans only. Demo
     * fallback plans are display-only and never joinable.
     *
     * @return list<array<string, mixed>>
     */
    public function joinable(): array
    {
        return $this->isUsingFallback() ? [] : $this->all();
    }

    public function findJoinable(string $slug): ?array
    {
        foreach ($this->joinable() as $plan) {
            if ($plan['slug'] === $slug) {
                return $plan;
            }
        }

        return null;
    }

    public function isUsingFallback(): bool
    {
        $this->all();

        return $this->usingFallback;
    }

    /**
     * Lowest monthly contribution among offered plans, or null when none are offered.
     */
    public function lowestMonthlyAmount(): ?float
    {
        // Only monthly plans: a daily or weekly amount is not a "per month" figure.
        $amounts = array_column(array_filter($this->all(), fn (array $plan) => $plan['per_period'] === 'month'), 'monthly_amount');

        return $amounts === [] ? null : (float) min($amounts);
    }

    /**
     * Plan-specific benefits when defined in the database, otherwise the
     * scheme-wide benefits.
     *
     * @return list<array{icon: string, title: string, text: string}>
     */
    public function benefitsFor(array $plan): array
    {
        $own = array_values(array_filter(
            $plan['benefits'] ?? [],
            fn ($benefit) => is_array($benefit) && filled($benefit['title'] ?? null),
        ));

        if ($own === []) {
            return $this->benefits();
        }

        return array_map(fn (array $benefit) => [
            'icon' => $benefit['icon'] ?? 'check',
            'title' => $benefit['title'],
            'text' => $benefit['text'] ?? '',
        ], $own);
    }

    /**
     * DATABASE DATA: active plans from the `plans` table.
     * Returns null when the table is empty or unavailable (→ fallback).
     *
     * @return list<array<string, mixed>>|null
     */
    private function databasePlans(): ?array
    {
        try {
            if (! Plan::query()->exists()) {
                return null;
            }

            return Plan::query()->active()->ordered()->get()
                ->map(fn (Plan $plan) => $this->fromModel($plan))
                ->all();
        } catch (QueryException $e) {
            Log::warning('GoldSavingPlanService: plans table unavailable, using fallback demo data.', ['code' => $e->getCode()]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fromModel(Plan $plan): array
    {
        return [
            'slug' => $plan->slug,
            'name' => $plan->name,
            'tagline' => $plan->tagline,
            'description' => $plan->description,
            'monthly_amount' => (float) $plan->monthly_amount,
            'duration_months' => $plan->duration_months,
            'total_amount' => $plan->total_amount === null ? null : (float) $plan->total_amount,
            'tenure_label' => $plan->tenureLabel(),
            'frequency' => $plan->contributionFrequency()->label(),
            'per_period' => $plan->contributionFrequency()->period(),
            'contribution_label' => $plan->contributionLabel(),
            'bonus_label' => $plan->bonusLabel(),
            'installments' => $plan->installmentCount(),
            'amount_label' => $plan->amountRangeLabel(),
            'is_fixed_amount' => $plan->isFixedAmount(),
            'output' => $plan->outputType()->label(),
            'scheme_type' => $plan->schemeType()->label(),
            'bonus_applicable' => (bool) $plan->bonus_applicable,
            'early_exit_allowed' => (bool) $plan->early_exit_allowed,
            'is_featured' => $plan->is_featured,
            'is_active' => $plan->status,
            'benefits' => $plan->benefits ?? [],
            'terms' => $plan->terms,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return list<array<string, mixed>>
     */
    private function presentAll(array $records): array
    {
        return array_values(array_map(
            fn (array $plan, int $index) => $this->present($plan, $index),
            $records,
            array_keys($records),
        ));
    }

    /**
     * FALLBACK DEMO DATA: sample plans (see DemoPlans).
     *
     * @return list<array<string, mixed>>
     */
    private function fallbackPlans(): array
    {
        return array_map(fn (array $plan) => $plan + [
            'total_amount' => $plan['monthly_amount'] * $plan['duration_months'],
            'benefits' => [],
            'terms' => null,
        ], DemoPlans::all());
    }

    /**
     * Benefits that apply to every plan. Operational only — no financial promises.
     *
     * @return list<array{icon: string, title: string, text: string}>
     */
    public function benefits(): array
    {
        return [
            ['icon' => 'calendar', 'title' => 'Fixed Monthly Contribution', 'text' => 'Save the same amount every month — simple to plan around.'],
            ['icon' => 'receipt', 'title' => 'A Receipt for Every Payment', 'text' => 'Each installment is acknowledged with a payment receipt.'],
            ['icon' => 'chart', 'title' => 'Track Your Progress', 'text' => 'See installments paid and remaining at a glance.'],
            ['icon' => 'gem', 'title' => 'Redeem Towards Jewellery', 'text' => 'Use your completed savings towards jewellery, as per scheme terms.'],
            ['icon' => 'trend', 'title' => 'Daily Gold Rate', 'text' => "Today's 22K and 24K rates are always published on our website."],
            ['icon' => 'shield', 'title' => 'Personal Assistance', 'text' => 'Our team guides you from enrolment to redemption.'],
        ];
    }

    /**
     * How monthly payments work. Generic until payment channels are finalised.
     *
     * @return list<string>
     */
    public function paymentSteps(): array
    {
        return [
            'Pay your first installment when you enrol.',
            'Pay each following installment once a month for the plan duration.',
            'Receive a receipt for every payment you make.',
            'Track every installment from your account once online accounts launch.',
        ];
    }

    /**
     * Notes shown wherever plan details appear. Placeholders for final terms.
     *
     * @return list<string>
     */
    public function importantNotes(): array
    {
        return [
            'Plan values shown on this website are indicative and may change before launch.',
            'Detailed scheme terms — including redemption, missed or late installments, and early closure — will be shared in writing at enrolment.',
            'Jewellery purchased at redemption is priced at the gold rate and making charges applicable on the day of purchase, as per scheme terms.',
            'Please contact our team before enrolling if you have any questions.',
        ];
    }

    /**
     * Normalise a database or fallback record into the shape the views use.
     */
    private function present(array $plan, int $index): array
    {
        return [
            'slug' => $plan['slug'],
            'number' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
            'name' => $plan['name'],
            'tagline' => $plan['tagline'] ?? '',
            'summary' => $plan['description'] ?? '',
            'monthly_amount' => $plan['monthly_amount'],
            'duration_months' => (int) $plan['duration_months'],
            'total_amount' => $plan['total_amount'],
            // Display terms. For fixed monthly plans these read exactly as before ("11 months", "₹5,000").
            'tenure_label' => $plan['tenure_label'] ?? $plan['duration_months'].' months',
            'frequency' => $plan['frequency'] ?? 'Monthly',
            'per_period' => array_key_exists('per_period', $plan) ? $plan['per_period'] : 'month',
            'per_period_phrase' => array_key_exists('per_period', $plan) && $plan['per_period'] === null ? 'per contribution' : 'a '.($plan['per_period'] ?? 'month'),
            'contribution_label' => $plan['contribution_label'] ?? 'Monthly Contribution',
            'bonus_label' => $plan['bonus_label'] ?? 'No bonus',
            'installments' => $plan['installments'] ?? (int) $plan['duration_months'],
            'amount_label' => $plan['amount_label'] ?? \App\Support\Money::inr($plan['monthly_amount']),
            'is_fixed_amount' => $plan['is_fixed_amount'] ?? true,
            'output' => $plan['output'] ?? 'Gold',
            'scheme_type' => $plan['scheme_type'] ?? 'Saving',
            'bonus_applicable' => (bool) ($plan['bonus_applicable'] ?? false),
            'early_exit_allowed' => (bool) ($plan['early_exit_allowed'] ?? false),
            'featured' => (bool) ($plan['is_featured'] ?? false),
            'is_active' => (bool) ($plan['is_active'] ?? true),
            'benefits' => $plan['benefits'] ?? [],
            'terms' => $plan['terms'] ?? null,
            'url' => route('gold.saving.show', $plan['slug']),
        ];
    }
}
