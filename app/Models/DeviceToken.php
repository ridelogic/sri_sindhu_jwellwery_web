<?php

namespace App\Models;

use App\Enums\DevicePlatform;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A push-notification device registration. The token is encrypted at rest and
 * hidden from serialisation; uniqueness is enforced on its SHA-256 hash.
 *
 * @property string $token
 * @property DevicePlatform $platform
 * @property bool $is_active
 * @property Carbon|null $last_used_at
 */
class DeviceToken extends Model
{
    protected $fillable = ['user_id', 'token', 'token_hash', 'platform', 'device_name', 'is_active', 'last_used_at'];

    protected $hidden = ['token', 'token_hash'];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'platform' => DevicePlatform::class,
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Last 4 characters only, for recognising a device without revealing the token. */
    public function maskedToken(): string
    {
        return '…'.substr($this->token, -4);
    }
}
