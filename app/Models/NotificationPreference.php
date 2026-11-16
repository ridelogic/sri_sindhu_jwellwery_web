<?php

namespace App\Models;

use App\Enums\NotificationPreferenceKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's notification switches. No row = all defaults.
 */
class NotificationPreference extends Model
{
    protected $fillable = ['user_id', 'gold_rate_updates', 'payment_updates', 'kyc_updates', 'promotional_notifications'];

    protected function casts(): array
    {
        return [
            'gold_rate_updates' => 'boolean',
            'payment_updates' => 'boolean',
            'kyc_updates' => 'boolean',
            'promotional_notifications' => 'boolean',
        ];
    }

    /** @return array<string, bool> */
    public static function defaults(): array
    {
        return collect(NotificationPreferenceKey::cases())->mapWithKeys(fn ($key) => [$key->value => $key->default()])->all();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
