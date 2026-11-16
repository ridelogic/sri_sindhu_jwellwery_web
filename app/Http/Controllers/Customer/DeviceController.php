<?php

namespace App\Http\Controllers\Customer;

use App\Enums\DevicePlatform;
use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Services\DeviceTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Push device registration for the signed-in customer (foundation for the
 * future app/web push). The user always comes from the session — never from
 * the request — and the token is never echoed back.
 */
class DeviceController extends Controller
{
    public function store(Request $request, DeviceTokenService $devices): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:4096'],
            'platform' => ['required', Rule::enum(DevicePlatform::class)],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $device = $devices->register($request->user(), $data['token'], DevicePlatform::from($data['platform']), $data['device_name'] ?? null);

        return $request->expectsJson()
            ? response()->json(['id' => $device->id, 'platform' => $device->platform->value, 'device_name' => $device->device_name, 'active' => true], 201)
            : redirect()->route('customer.notifications.settings')->with('status', 'Device registered for notifications.');
    }

    public function deactivate(Request $request, int $device, DeviceTokenService $devices): JsonResponse|RedirectResponse
    {
        /** @var DeviceToken $model */
        $model = $request->user()->deviceTokens()->whereKey($device)->firstOrFail(); // own devices only

        $devices->deactivate($request->user(), $model);

        return $request->expectsJson()
            ? response()->json(['id' => $model->id, 'active' => false])
            : redirect()->route('customer.notifications.settings')->with('status', 'Device removed. It will no longer receive notifications.');
    }
}
