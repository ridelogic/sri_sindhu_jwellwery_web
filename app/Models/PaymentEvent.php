<?php

namespace App\Models;

use App\Enums\PaymentEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only payment audit entry. Never contains gateway credentials or signatures.
 */
class PaymentEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['payment_id', 'actor_id', 'event', 'note'];

    protected function casts(): array
    {
        return ['event' => PaymentEventType::class, 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Payment events cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Payment events cannot be deleted.'));
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
