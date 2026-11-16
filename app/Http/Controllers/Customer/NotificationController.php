<?php

namespace App\Http\Controllers\Customer;

use App\Enums\NotificationPreferenceKey;
use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

/**
 * The signed-in customer's notification centre. Notifications are always
 * looked up WITHIN the customer's own notifications, so another customer's
 * notification id simply returns 404. State changes are POST/PUT + CSRF.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('customer.notifications.index', [
            'notifications' => $user->notifications()->paginate(20),
            'unread' => $user->unreadNotifications()->count(),
        ]);
    }

    public function show(Request $request, string $notification): View
    {
        $model = $this->find($request, $notification);
        $model->markAsRead();

        return view('customer.notifications.show', ['notification' => $model]);
    }

    public function read(Request $request, string $notification): RedirectResponse|JsonResponse
    {
        $this->find($request, $notification)->markAsRead();

        return $request->expectsJson()
            ? response()->json(['unread' => $request->user()->unreadNotifications()->count()])
            : back(fallback: route('customer.notifications.index'));
    }

    public function readAll(Request $request): RedirectResponse|JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $request->expectsJson()
            ? response()->json(['unread' => 0])
            : back(fallback: route('customer.notifications.index'))->with('status', 'All notifications marked as read.');
    }

    public function settings(Request $request, NotificationService $notifications): View
    {
        return view('customer.notifications.settings', [
            'preferences' => $notifications->preferencesFor($request->user()),
            'keys' => NotificationPreferenceKey::cases(),
            'devices' => $request->user()->deviceTokens()->active()->latest('last_used_at')->get(),
        ]);
    }

    public function updateSettings(Request $request, NotificationService $notifications): RedirectResponse
    {
        $rules = collect(NotificationPreferenceKey::cases())->mapWithKeys(fn ($key) => [$key->value => ['nullable', 'boolean']])->all();
        $values = collect($request->validate($rules))->map(fn ($value) => (bool) $value)->all();

        $notifications->updatePreferences($request->user(), $values);

        return redirect()->route('customer.notifications.settings')->with('status', 'Your notification preferences have been saved.');
    }

    private function find(Request $request, string $id): DatabaseNotification
    {
        /** @var DatabaseNotification */
        return $request->user()->notifications()->whereKey($id)->firstOrFail();
    }
}
