<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

/**
 * Admin Settings: company / website details. No credentials or API keys are shown or stored here.
 */
class SettingsController extends Controller
{
    public function edit(SettingsService $settings): View
    {
        $connection = config('database.default');

        return view('admin.settings.edit', [
            'settings' => $settings->all(),
            'labels' => SettingsService::KEYS,
            'lastUpdated' => $settings->lastUpdated(),
            // Where these values live (names only — never credentials).
            'storage' => [
                'table' => (new Setting)->getTable(),
                'connection' => $connection,
                'database' => basename((string) config("database.connections.{$connection}.database")),
            ],
        ]);
    }

    public function update(UpdateSettingsRequest $request, SettingsService $settings): RedirectResponse
    {
        try {
            $changed = $settings->save($request->settings(), $request->user());
        } catch (Throwable $e) {
            report($e);

            // The save runs in one transaction, so nothing was written.
            return back()->withInput()->with('settings_error', 'Settings could not be saved because of a server error. Nothing was changed — please try again.');
        }

        return redirect()->route('admin.settings.edit')
            ->with('status', 'Settings saved successfully!')
            ->with('status_autodismiss', true)
            ->with('settings_saved', [
                'at' => now()->toIso8601String(),
                'by' => $request->user()->name,
                'changed' => array_map(fn (string $key) => SettingsService::KEYS[$key], $changed),
            ]);
    }
}
