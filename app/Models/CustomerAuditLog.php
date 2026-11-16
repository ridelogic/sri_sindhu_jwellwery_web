<?php

namespace App\Models;

use App\Enums\CustomerAuditAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only record of admin actions on a customer account. Stores changed
 * field NAMES only — never values, passwords or identity data.
 */
class CustomerAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'actor_id', 'action', 'fields', 'note'];

    protected function casts(): array
    {
        return [
            'action' => CustomerAuditAction::class,
            'fields' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Customer audit entries cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Customer audit entries cannot be deleted.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
