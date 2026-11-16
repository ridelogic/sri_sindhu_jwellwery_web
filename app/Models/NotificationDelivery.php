<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One push attempt for one notification to one device.
 */
class NotificationDelivery extends Model
{
    protected $fillable = ['notification_id', 'user_id', 'device_token_id', 'channel', 'provider', 'status', 'error', 'attempted_at'];

    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'attempted_at' => 'datetime',
        ];
    }

    public function deviceToken(): BelongsTo
    {
        return $this->belongsTo(DeviceToken::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
