<?php

namespace App\Services;

use App\Enums\CustomerPlanStatus;
use App\Enums\KycStatus;
use App\Models\CustomerPlan;
use App\Models\GoldRate;
use App\Models\GoldTransaction;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Read model for the customer portal.
 *
 * Amounts paid and gold accumulated come only from successful payments and
 * the gold ledger (each credit carries its own historical rate snapshot).
 * "Current value" is the one figure using today's rate: it values the gold
 * already held — it never changes how much gold a past payment bought.
 */
class CustomerAccountService
{
    public const NOT_SUBMITTED = 'Not Submitted';

    public function __construct(private GoldCalculationService $gold) {}

    /**
     * @return Collection<int, CustomerPlan>
     */
    public function schemes(User $customer): Collection
    {
        return $customer->customerPlans()->with('plan:id,name,slug,status')->latest('created_at')->latest('id')->get();
    }

    public function summary(User $customer, Collection $schemes): array
    {
        $open = $schemes->filter(fn (CustomerPlan $s) => in_array($s->status, CustomerPlanStatus::open(), true));
        $paidPaise = (int) Payment::query()->where('user_id', $customer->id)->successful()->sum('amount_paise');
        $grams = $this->gold->sumGrams(GoldTransaction::query()->where('user_id', $customer->id)->pluck('gold_grams'));
        $hasPayments = $paidPaise > 0;

        return [
            'active_schemes' => $open->count(),
            'awaiting_first_payment' => $open->filter(fn (CustomerPlan $s) => $s->status === CustomerPlanStatus::Pending)->count(),
            'total_contributed' => Money::fromPaise($paidPaise),
            'gold_grams' => $grams,
            'current_value' => $hasPayments ? $this->currentValue($grams) : null,
            'has_payments' => $hasPayments,
            'kyc_status' => ($kyc = $this->kyc($customer))['label'],
            'kyc' => $kyc,
        ];
    }

    /**
     * Per-scheme figures for many schemes at once (two queries in total).
     *
     * @param  Collection<int, CustomerPlan>  $schemes
     * @return Collection<int, array>
     */
    public function progressFor(Collection $schemes): Collection
    {
        $ids = $schemes->pluck('id');
        $payments = Payment::query()->whereIn('customer_plan_id', $ids)->successful()
            ->get(['customer_plan_id', 'amount_paise', 'paid_at'])->groupBy('customer_plan_id');
        $gold = GoldTransaction::query()->whereIn('customer_plan_id', $ids)
            ->get(['customer_plan_id', 'gold_grams'])->groupBy('customer_plan_id');

        return $schemes->mapWithKeys(fn (CustomerPlan $scheme) => [
            $scheme->id => $this->progress($scheme, $payments->get($scheme->id, collect()), $gold->get($scheme->id, collect())),
        ]);
    }

    public function schemeProgress(CustomerPlan $scheme): array
    {
        return $this->progressFor(collect([$scheme]))->first();
    }

    private function progress(CustomerPlan $scheme, Collection $payments, Collection $gold): array
    {
        $paidPaise = (int) $payments->sum('amount_paise');
        $grams = $this->gold->sumGrams($gold->pluck('gold_grams'));
        $count = $payments->count();

        return [
            'amount_paid' => Money::fromPaise($paidPaise),
            'gold_grams' => $grams,
            'current_value' => $count > 0 ? $this->currentValue($grams) : null,
            'installments_paid' => $count,
            'last_payment_at' => $payments->max('paid_at'),
            'next_installment' => $scheme->installments_total === null || $count < $scheme->installments_total ? $count + 1 : null,
        ];
    }

    /** Value of $grams at the latest active rate, or null when no rate exists. */
    private function currentValue(string $grams): ?string
    {
        $rate = GoldRate::latestFor($this->gold->purity());

        return $rate ? Money::fromPaise($this->gold->valuePaise($grams, $this->gold->ratePaise($rate))) : null;
    }

    /**
     * KYC status label only — no identity data ever leaves this method.
     */
    public function kycStatus(User $customer): string
    {
        return $this->currentKycStatus($customer)?->label() ?? self::NOT_SUBMITTED;
    }

    /**
     * KYC status for the dashboard card: label, workflow key and call to action.
     * Never includes identity data.
     *
     * @return array{key: string, label: string, message: string, cta: string}
     */
    public function kyc(User $customer): array
    {
        $status = $this->currentKycStatus($customer) ?? KycStatus::Pending;

        return [
            'key' => $status->key(),
            'label' => $status->label(),
            'message' => match ($status) {
                KycStatus::Pending => 'Complete your KYC to continue.',
                KycStatus::Submitted => 'Your KYC is currently under review.',
                KycStatus::Verified => 'Your KYC has been verified.',
                KycStatus::Rejected => 'Your KYC needs attention.',
            },
            'cta' => match ($status) {
                KycStatus::Pending => 'Complete KYC',
                KycStatus::Submitted => 'View Status',
                KycStatus::Verified => 'View KYC',
                KycStatus::Rejected => 'Update KYC',
            },
        ];
    }

    /** Read fresh (status only) so a decision made moments ago is always reflected. */
    private function currentKycStatus(User $customer): ?KycStatus
    {
        return $customer->kycProfile()->value('kyc_status'); // cast to KycStatus by the model
    }
}
