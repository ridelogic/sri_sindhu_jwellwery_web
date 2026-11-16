<?php

namespace App\Models;

use App\Enums\GoldPurity;
use App\Enums\GoldTransactionType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * The permanent gold ledger. Each row is a self-contained snapshot: the
 * amount, the exact rate used, its date/time and purity, and the grams
 * credited. It is never recalculated, updated or deleted, so later rate
 * changes cannot affect it.
 *
 * @property int $amount_paise
 * @property int $rate_per_gram_paise
 * @property string $gold_grams
 * @property GoldPurity $purity
 * @property Carbon $rate_date
 * @property string|null $rate_time
 * @property Carbon $transaction_date
 */
class GoldTransaction extends Model
{
    protected $fillable = [
        'user_id', 'customer_plan_id', 'payment_id', 'gold_rate_id', 'transaction_type', 'amount_paise',
        'rate_per_gram_paise', 'gold_grams', 'purity', 'rate_date', 'rate_time', 'transaction_date', 'description', 'metadata',
    ];

    protected $hidden = ['metadata'];

    protected function casts(): array
    {
        return [
            'transaction_type' => GoldTransactionType::class,
            'amount_paise' => 'integer',
            'rate_per_gram_paise' => 'integer',
            'gold_grams' => 'decimal:6',
            'purity' => GoldPurity::class,
            'rate_date' => 'date',
            'rate_time' => 'string',
            'transaction_date' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Gold transactions are permanent and cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Gold transactions are permanent and cannot be deleted.'));
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function customerPlan(): BelongsTo
    {
        return $this->belongsTo(CustomerPlan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Rate snapshot in rupees per gram ("12500.00"). */
    public function ratePerGram(): string
    {
        return Money::fromPaise($this->rate_per_gram_paise);
    }

    /** "06 Oct 2026, 9:30 AM" — when the applied rate took effect. */
    public function rateEffectiveLabel(): string
    {
        $time = $this->rate_time ? Carbon::createFromFormat('H:i:s', substr($this->rate_time, 0, 8))->format('g:i A') : null;

        return $this->rate_date->format('d M Y').($time ? ', '.$time : '');
    }
}
