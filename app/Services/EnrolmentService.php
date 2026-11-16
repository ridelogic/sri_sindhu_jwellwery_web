<?php

namespace App\Services;

use App\Enums\CustomerPlanStatus;
use App\Models\CustomerPlan;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Enrols a customer in a gold saving plan (creates a customer_plans row).
 *
 * Financial safety:
 *  - The plan is re-read and locked inside the transaction; it must still be
 *    ACTIVE at that moment. Nothing financial is taken from the request.
 *  - monthly_amount, duration_months and total_commitment are COPIED from the
 *    plan at enrolment (CustomerPlan model), so later plan edits never change
 *    an existing commitment.
 *
 * Duplicate protection (double-click / resubmission):
 *  - an optional one-time form token is consumed atomically (Cache::add);
 *  - the customer's row is locked so their enrolments are serialised;
 *  - an enrolment in the same plan within DUPLICATE_WINDOW_SECONDS is returned
 *    instead of creating another one.
 * Joining several different plans — or the same plan again later — is allowed.
 *
 * Scheme numbers are unique (DB constraint); a rare collision under concurrent
 * requests is retried with a freshly generated number.
 */
class EnrolmentService
{
    public const DUPLICATE_WINDOW_SECONDS = 30;

    private const SCHEME_NUMBER_ATTEMPTS = 5;

    /**
     * @return array{0: CustomerPlan, 1: bool} the enrolment and whether it was newly created
     */
    public function enrol(User $user, Plan $plan, ?string $token = null): array
    {
        if ($user->isAdmin()) {
            throw ValidationException::withMessages(['plan' => 'Administrator accounts cannot join gold saving plans.']);
        }

        if ($token !== null && ! Cache::add('enrolment-token:'.$user->id.':'.$token, true, now()->addHours(2))) {
            // The same form was submitted twice — return what the first submission created.
            $existing = $this->recentEnrolment($user, $plan) ?? $user->customerPlans()->where('plan_id', $plan->id)->latest('id')->first();

            if ($existing) {
                return [$existing, false];
            }
        }

        return DB::transaction(function () use ($user, $plan) {
            // Serialise this customer's enrolments (concurrent duplicate requests wait here).
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $lockedPlan = Plan::query()->whereKey($plan->id)->lockForUpdate()->first();

            if ($lockedPlan === null || ! $lockedPlan->status) {
                throw ValidationException::withMessages(['plan' => 'This plan is no longer open for new enrolments. Please choose another plan.']);
            }

            if ($recent = $this->recentEnrolment($user, $lockedPlan)) {
                return [$recent, false];
            }

            return [$this->createWithUniqueSchemeNumber($user, $lockedPlan), true];
        });
    }

    private function recentEnrolment(User $user, Plan $plan): ?CustomerPlan
    {
        return $user->customerPlans()
            ->where('plan_id', $plan->id)
            ->where('created_at', '>=', Carbon::now()->subSeconds(self::DUPLICATE_WINDOW_SECONDS))
            ->latest('id')
            ->first();
    }

    private function createWithUniqueSchemeNumber(User $user, Plan $plan): CustomerPlan
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                // Savepoint, so a unique-constraint failure does not poison the outer transaction.
                return DB::transaction(fn () => CustomerPlan::query()->create([
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'start_date' => Carbon::today(),
                    // Awaiting the first payment — payments are a later stage.
                    'status' => CustomerPlanStatus::Pending,
                    // monthly_amount / duration_months / total_commitment / scheme_number are
                    // filled server-side by the CustomerPlan model from the locked plan row.
                ]));
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= self::SCHEME_NUMBER_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }
}
