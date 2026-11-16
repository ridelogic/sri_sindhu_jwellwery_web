<?php

namespace App\Services;

use App\Enums\DevicePlatform;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Push device registrations. A user may have many devices; a token belongs to
 * exactly one user at a time (enforced by the unique token hash).
 */
class DeviceTokenService
{
    /**
     * Register (or refresh) a device for the signed-in user. If the same token
     * was registered by another account on this device (e.g. a different
     * customer signed in), it moves to the current user — a device only
     * receives notifications for the account currently using it.
     */
    public function register(User $user, string $token, DevicePlatform $platform, ?string $deviceName = null): DeviceToken
    {
        $hash = DeviceToken::hashToken($token);

        return DB::transaction(function () use ($user, $token, $hash, $platform, $deviceName) {
            $device = DeviceToken::query()->where('token_hash', $hash)->lockForUpdate()->first() ?? new DeviceToken(['token_hash' => $hash]);

            $device->forceFill([
                'user_id' => $user->id,
                'token' => $token,
                'platform' => $platform,
                'device_name' => $deviceName ?: $device->device_name,
                'is_active' => true,
                'last_used_at' => now(),
            ])->save();

            return $device;
        });
    }

    /** Only the owner can deactivate their device. */
    public function deactivate(User $user, DeviceToken $device): bool
    {
        if ($device->user_id !== $user->id) {
            return false;
        }

        return $device->forceFill(['is_active' => false])->save();
    }
}
