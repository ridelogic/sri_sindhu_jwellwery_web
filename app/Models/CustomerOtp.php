<?php

namespace App\Models;

use App\Enums\OtpPurpose;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A one-time password record. Holds only a keyed hash of the code.
 *
 * @property OtpPurpose $purpose
 * @property string $code_hash
 */
class CustomerOtp extends Model
{
    protected $fillable = ['mobile_number', 'purpose', 'user_id', 'code_hash', 'expires_at', 'ip_address'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'purpose' => OtpPurpose::class,
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    /** Not yet used up or replaced (may still be expired). */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('consumed_at')->whereNull('invalidated_at');
    }
}
