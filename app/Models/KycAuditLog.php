<?php

namespace App\Models;

use App\Enums\KycAuditAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only KYC audit entry. Stores IDs and the action only — never PAN,
 * Aadhaar, document numbers or file contents.
 */
class KycAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['kyc_profile_id', 'actor_id', 'action', 'note'];

    protected function casts(): array
    {
        return [
            'action' => KycAuditAction::class,
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('KYC audit entries cannot be changed.'));
        static::deleting(fn () => throw new LogicException('KYC audit entries cannot be deleted.'));
    }

    public function kycProfile(): BelongsTo
    {
        return $this->belongsTo(KycProfile::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
