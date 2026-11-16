<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create(['name' => 'Office Admin']);
    }

    private function valid(array $overrides = []): array
    {
        return $overrides + [
            'company_name' => 'Sri Sindhu Jewellery Pvt Ltd',
            'website_name' => 'Sri Sindhu Jewellery',
            'company_phone' => '+91 98765 43210',
            'company_email' => 'info@srisindhu.example',
            'company_address' => "12 Temple Street\nMadurai 625001",
        ];
    }

    private function save(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->admin, 'web')->from(route('admin.settings.edit'))->put(route('admin.settings.update'), $this->valid($overrides));
    }

    public function test_admin_can_view_settings_with_current_defaults(): void
    {
        config(['site.name' => 'Sri Sindhu Jewellery', 'site.contact.phone' => null]);

        $this->actingAs($this->admin, 'web')->get(route('admin.settings.edit'))->assertOk()
            ->assertSee('Settings')
            ->assertSee('action="'.route('admin.settings.update').'"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('name="company_name"', false)->assertSee('name="website_name"', false)
            ->assertSee('name="company_phone"', false)->assertSee('name="company_email"', false)
            ->assertSee('name="company_address"', false)
            ->assertSee('value="Sri Sindhu Jewellery"', false)
            ->assertSee('Save Settings')
            ->assertSee('Not saved yet');
    }

    public function test_sidebar_settings_link_opens_the_settings_page(): void
    {
        $dashboard = $this->actingAs($this->admin, 'web')->get(route('admin.dashboard'))->assertOk();
        $dashboard->assertSee('href="'.route('admin.settings.edit').'"', false);

        $html = $this->get(route('admin.settings.edit'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('admin.settings.edit'), '/').'" class="admin-nav-link"\s+aria-current="page"/', $html);
    }

    public function test_valid_settings_are_saved_and_shown_after_reload(): void
    {
        $this->save()->assertRedirect(route('admin.settings.edit'))->assertSessionHasNoErrors()->assertSessionHas('status', 'Settings saved successfully!');

        $this->assertSame(5, Setting::query()->count());
        $this->assertSame('Sri Sindhu Jewellery Pvt Ltd', Setting::query()->where('key', 'company_name')->value('value'));
        $this->assertSame($this->admin->id, Setting::query()->where('key', 'company_email')->value('updated_by'));

        // Reopen the page: saved values are loaded.
        $this->get(route('admin.settings.edit'))->assertOk()
            ->assertSee('value="Sri Sindhu Jewellery Pvt Ltd"', false)
            ->assertSee('value="+91 98765 43210"', false)
            ->assertSee('value="info@srisindhu.example"', false)
            ->assertSee("12 Temple Street\nMadurai 625001", false)
            ->assertSee('Last saved')->assertSee('by Office Admin');

        $this->assertSame('info@srisindhu.example', app(SettingsService::class)->get('company_email'));
    }

    public function test_saving_again_updates_values_and_optional_fields_can_be_cleared(): void
    {
        $this->save();
        $this->save(['company_name' => '  Sri  Sindhu   Gold  ', 'company_phone' => '', 'company_email' => ' INFO@Example.COM ', 'company_address' => ''])->assertSessionHasNoErrors();

        $this->assertSame(5, Setting::query()->count()); // updated in place, no duplicates
        $settings = app(SettingsService::class)->all();
        $this->assertSame('Sri Sindhu Gold', $settings['company_name']);
        $this->assertNull($settings['company_phone']);
        $this->assertSame('info@example.com', $settings['company_email']);
        $this->assertNull($settings['company_address']);
    }

    public function test_invalid_input_is_rejected_and_nothing_is_saved(): void
    {
        $pairs = [
            [['company_name' => ''], 'company_name'],
            [['website_name' => '   '], 'website_name'],
            [['company_name' => str_repeat('x', 121)], 'company_name'],
            [['company_name' => '<script>alert(1)</script>'], 'company_name'],
            [['company_phone' => 'call me'], 'company_phone'],
            [['company_phone' => '12345'], 'company_phone'],                 // too few digits
            [['company_phone' => '+91 98765 43210 99999'], 'company_phone'], // too long
            [['company_email' => 'not-an-email'], 'company_email'],
            [['company_address' => str_repeat('a', 501)], 'company_address'],
            [['company_address' => 'Street <b>1</b>'], 'company_address'],
        ];

        foreach ($pairs as [$overrides, $field]) {
            $this->save($overrides)->assertRedirect(route('admin.settings.edit'))->assertSessionHasErrors($field);
        }

        $this->assertSame(0, Setting::query()->count());

        // The error summary is shown on the form.
        $this->save(['company_email' => 'bad'])->assertSessionHasErrors('company_email');
        $this->get(route('admin.settings.edit'))->assertSee('data-settings-errors', false)->assertSee('Please enter a valid email address.');
    }

    public function test_unknown_fields_are_ignored(): void
    {
        $this->save(['app_key' => 'stolen', 'razorpay_secret' => 'x'])->assertSessionHasNoErrors();

        $this->assertSame(0, Setting::query()->whereIn('key', ['app_key', 'razorpay_secret'])->count());
        $this->assertSame(5, Setting::query()->count());
    }

    public function test_page_does_not_expose_credentials(): void
    {
        config(['services.razorpay.secret' => 'rzp_secret_value_123', 'sms.msg91.auth_key' => 'msg91_key_value_456']);

        $this->actingAs($this->admin, 'web')->get(route('admin.settings.edit'))->assertOk()
            ->assertDontSee('rzp_secret_value_123')->assertDontSee('msg91_key_value_456')
            ->assertDontSee((string) config('app.key'));
    }

    public function test_guests_and_customers_cannot_view_or_update_settings(): void
    {
        $this->get(route('admin.settings.edit'))->assertRedirect(route('admin.login'));
        $this->put(route('admin.settings.update'), $this->valid())->assertRedirect(route('admin.login'));

        $customer = User::factory()->create();
        $this->actingAs($customer, 'customer');
        $this->get(route('admin.settings.edit'))->assertRedirect(route('admin.login'));
        $this->put(route('admin.settings.update'), $this->valid())->assertRedirect(route('admin.login'));

        $this->assertSame(0, Setting::query()->count());
    }

    public function test_update_requires_put_and_a_csrf_token(): void
    {
        $this->actingAs($this->admin, 'web')->post(route('admin.settings.update'), $this->valid())->assertMethodNotAllowed();

        // CSRF comes from the web group; both routes sit behind the admin middleware.
        $this->assertContains(ValidateCsrfToken::class, app(HttpKernel::class)->getMiddlewareGroups()['web']);
        foreach (['edit', 'update'] as $action) {
            $middleware = app('router')->getRoutes()->getByName('admin.settings.'.$action)->gatherMiddleware();
            $this->assertContains('web', $middleware, $action);
            $this->assertContains('admin', $middleware, $action);
        }
        $this->assertSame(0, Setting::query()->count());
    }

    // Save confirmation --------------------------------------------------------------------------

    public function test_successful_save_shows_alert_and_confirmation_panel(): void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-10-10 14:35:20');
        config(['site.name' => 'Sri Sindhu Jewellery', 'site.contact.phone' => null, 'site.contact.email' => null, 'site.contact.address' => null]);

        $html = $this->followingRedirects()->actingAs($this->admin, 'web')
            ->from(route('admin.settings.edit'))->put(route('admin.settings.update'), $this->valid())
            ->assertOk()
            ->assertSee('Settings saved successfully!')
            ->assertSee('data-autodismiss="5000"', false)
            ->assertSee('data-settings-saved', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/data-saved-status>Saved successfully</', $html);
        $this->assertMatchesRegularExpression('/data-saved-at>10 Oct 2026, 2:35:20 PM</', $html);
        $this->assertMatchesRegularExpression('/data-saved-by>Office Admin</', $html);
        // First save: only fields that differ from the current values are listed. The website
        // display name submitted equals the default (site name), so it is not reported as changed.
        $this->assertMatchesRegularExpression('/data-saved-changes>Jewellery \/ Company Name, Company Contact Phone, Company Email, Company Address</', $html);

        // The confirmation is one-time: a plain reload shows the saved values but no panel or alert.
        $this->get(route('admin.settings.edit'))->assertOk()
            ->assertDontSee('data-settings-saved', false)
            ->assertDontSee('Settings saved successfully!')
            ->assertSee('value="Sri Sindhu Jewellery Pvt Ltd"', false);

        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_confirmation_lists_only_the_fields_that_changed(): void
    {
        $this->save();

        $this->save(['company_phone' => '+91 90000 11111', 'company_email' => 'sales@srisindhu.example'])
            ->assertSessionHas('settings_saved', fn (array $saved) => $saved['changed'] === ['Company Contact Phone', 'Company Email'] && $saved['by'] === 'Office Admin');
    }

    public function test_saving_unchanged_values_still_confirms_and_updates_last_saved(): void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-10-10 10:00:00');
        $this->save();

        \Illuminate\Support\Carbon::setTestNow('2026-10-10 11:30:00');
        $this->save()->assertSessionHas('status', 'Settings saved successfully!')
            ->assertSessionHas('settings_saved', fn (array $saved) => $saved['changed'] === []);

        $this->assertSame(5, Setting::query()->count());
        $this->assertSame('2026-10-10 11:30:00', app(SettingsService::class)->lastUpdated()['at']->format('Y-m-d H:i:s'));

        $this->followingRedirects()->from(route('admin.settings.edit'))->put(route('admin.settings.update'), $this->valid())
            ->assertSee('No changes — the values were already up to date.');

        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_validation_failure_shows_no_success_and_keeps_entered_values(): void
    {
        $this->save(['company_email' => 'not-an-email', 'company_name' => 'Typed Name'])
            ->assertSessionHasErrors('company_email')
            ->assertSessionMissing('status')
            ->assertSessionMissing('settings_saved');

        $this->get(route('admin.settings.edit'))
            ->assertSee('data-settings-errors', false)
            ->assertSee('value="Typed Name"', false)
            ->assertDontSee('data-settings-saved', false)
            ->assertDontSee('Settings saved successfully!');
    }

    public function test_server_error_shows_error_keeps_input_and_saves_nothing(): void
    {
        $failing = \Mockery::mock(SettingsService::class)->makePartial();
        $failing->shouldReceive('save')->andThrow(new \RuntimeException('database down'));
        $this->app->instance(SettingsService::class, $failing);

        $this->save(['company_name' => 'Unsaved Name'])
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHas('settings_error')
            ->assertSessionMissing('status')
            ->assertSessionMissing('settings_saved');

        $this->assertSame(0, Setting::query()->count());

        $this->app->forgetInstance(SettingsService::class);
        $this->get(route('admin.settings.edit'))
            ->assertSee('Settings could not be saved because of a server error.')
            ->assertSee('value="Unsaved Name"', false)
            ->assertDontSee('Settings saved successfully!');
    }

    public function test_page_names_the_storage_table_and_save_button_shows_saving_state(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.settings.edit'))->assertOk()
            ->assertSee('data-settings-storage', false)
            ->assertSee('<code class="font-semibold text-ad-ink">settings</code>', false)
            ->assertSee('data-saving-label="Saving…"', false)
            ->assertSee('data-submit-once', false)
            ->assertDontSee('data-autodismiss', false); // only right after a save
    }
}
