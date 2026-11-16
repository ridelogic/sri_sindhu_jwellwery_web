<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Support\Money;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use LogicException;

/**
 * One payment attempt for one installment of a customer's scheme.
 *
 * The amount is copied from customer_plans.monthly_amount (the customer's
 * agreed commitment), never from the plan's current price.
 *
 * Once a payment is successful its financial fields are frozen, and payments
 * can never be deleted. The gateway signature, idempotency key and metadata
 * are hidden from serialisation.
 *
 * @property int $id
 * @property string $reference
 * @property int $amount_paise
 * @property PaymentStatus $status
 * @property Carbon|null $paid_at
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    private const FROZEN_WHEN_SUCCESSFUL = [
        'user_id', 'customer_plan_id', 'installment_number', 'amount_paise', 'currency',
        'status', 'gateway', 'gateway_order_id', 'gateway_payment_id', 'paid_at',
    ];

    protected $fillable = [
        'reference', 'user_id', 'customer_plan_id', 'installment_number', 'amount_paise', 'currency',
        'status', 'payment_method', 'gateway', 'gateway_order_id', 'gateway_payment_id', 'gateway_signature',
        'idempotency_key', 'initiated_at', 'paid_at', 'failed_at', 'cancelled_at', 'failure_reason', 'metadata',
    ];

    protected $hidden = ['gateway_signature', 'idempotency_key', 'metadata'];

    protected $attributes = [
        'status' => 'created',
        'currency' => 'INR',
    ];

    protected function casts(): array
    {
        return [
            'amount_paise' => 'integer',
            'installment_number' => 'integer',
            'status' => PaymentStatus::class,
            'initiated_at' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            $payment->reference ??= static::generateReference();
            $payment->initiated_at ??= now();
        });

        static::updating(function (Payment $payment) {
            if ($payment->getOriginal('status') === PaymentStatus::Success) {
                $changed = array_intersect(array_keys($payment->getDirty()), self::FROZEN_WHEN_SUCCESSFUL);
                if ($changed !== []) {
                    throw new LogicException('A successful payment cannot be changed ('.implode(', ', $changed).').');
                }
            }
        });

        static::deleting(fn () => throw new LogicException('Payments are permanent financial records and cannot be deleted.'));
    }

    /** e.g. SSJP-261007-7KQ2M9XA */
    public static function generateReference(): string
    {
        do {
            $reference = 'SSJP-'.now()->format('ymd').'-'.Str::upper(Str::random(8));
        } while (static::query()->where('reference', $reference)->exists());

        return $reference;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customerPlan(): BelongsTo
    {
        return $this->belongsTo(CustomerPlan::class);
    }

    public function goldTransaction(): HasOne
    {
        return $this->hasOne(GoldTransaction::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class)->latest('created_at')->latest('id');
    }

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::Success->value);
    }

    public function isSuccessful(): bool
    {
        return $this->status === PaymentStatus::Success;
    }

    /** Amount in rupees as an exact decimal string ("5000.00"). */
    public function amount(): string
    {
        return Money::fromPaise($this->amount_paise);
    }
}
