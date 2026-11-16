<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Admin-editable company / website details, stored as key–value rows in `settings`.
 *
 * Only the keys listed in KEYS can be read or written. Values not yet saved fall back
 * to the current site configuration (config/site.php), so the form starts from what
 * the application already uses. These settings do not (yet) change the public website.
 */
class SettingsService
{
    /** Allowed keys => labels. */
    public const KEYS = [
        'company_name' => 'Jewellery / Company Name',
        'website_name' => 'Website Display Name',
        'company_phone' => 'Company Contact Phone',
        'company_email' => 'Company Email',
        'company_address' => 'Company Address',
    ];

    /** @return array<string, string|null> every allowed key with its saved value or default */
    public function all(): array
    {
        $saved = Setting::query()->whereIn('key', array_keys(self::KEYS))->pluck('value', 'key');

        $values = [];
        foreach ($this->defaults() as $key => $default) {
            $values[$key] = $saved->has($key) ? $saved[$key] : $default;
        }

        return $values;
    }

    public function get(string $key): ?string
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * Save validated values (all or nothing). Unknown keys are ignored. Every row is stamped
     * with this save's time and administrator, so "last saved" reflects the latest save even
     * when nothing changed.
     *
     * @param  array<string, string|null>  $values
     * @return list<string> the keys whose value actually changed
     */
    public function save(array $values, ?User $actor = null): array
    {
        $values = array_intersect_key($values, self::KEYS);
        $before = $this->all();

        DB::transaction(function () use ($values, $actor) {
            foreach ($values as $key => $value) {
                $setting = Setting::query()->firstOrNew(['key' => $key]);
                $setting->fill(['value' => $value, 'updated_by' => $actor?->id]);
                $setting->exists && ! $setting->isDirty() ? $setting->touch() : $setting->save();
            }
        });

        return array_values(array_filter(
            array_keys($values),
            fn (string $key) => (string) ($before[$key] ?? '') !== (string) ($values[$key] ?? ''),
        ));
    }

    /** @return array{at: \Illuminate\Support\Carbon|null, by: string|null} when and by whom settings were last saved */
    public function lastUpdated(): array
    {
        $latest = Setting::query()->whereIn('key', array_keys(self::KEYS))->with('updatedBy:id,name')->latest('updated_at')->first();

        return ['at' => $latest?->updated_at, 'by' => $latest?->updatedBy?->name];
    }

    /** @return array<string, string|null> */
    private function defaults(): array
    {
        return [
            'company_name' => config('site.name'),
            'website_name' => config('site.name'),
            'company_phone' => config('site.contact.phone'),
            'company_email' => config('site.contact.email'),
            'company_address' => config('site.contact.address'),
        ];
    }
}
