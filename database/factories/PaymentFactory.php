<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\CustomerPlan;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Unfinished, failed or cancelled attempts for tests. Successful payments
 * must go through PaymentService so the gold ledger is always consistent.
 *
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'customer_plan_id' => CustomerPlan::factory(),
            'user_id' => fn (array $attributes) => CustomerPlan::query()->find($attributes['customer_plan_id'])->user_id,
            'installment_number' => 1,
            'amount_paise' => fn (array $attributes) => Money::toPaise(CustomerPlan::query()->find($attributes['customer_plan_id'])->monthly_amount),
            'currency' => 'INR',
            'status' => PaymentStatus::Created,
            'gateway' => 'razorpay',
            'gateway_order_id' => 'order_'.Str::random(14),
        ];
    }

    public function status(PaymentStatus $status): static
    {
        return $this->state(fn () => match ($status) {
            PaymentStatus::Failed => ['status' => $status, 'failed_at' => now(), 'failure_reason' => 'Payment was declined by the bank.'],
            PaymentStatus::Cancelled => ['status' => $status, 'cancelled_at' => now()],
            default => ['status' => $status],
        });
    }
}
