<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\Auth\AdminLoginController;
use App\Models\User;
use Database\Seeders\DevelopmentAdminSeeder;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $attributes = []): User
    {
        return User::factory()->admin()->create(['password' => 'Correct-Horse-9!'] + $attributes);
    }

    public function test_guest_cannot_access_admin_and_is_redirected_to_admin_login(): void
    {
        $this->get('/admin')
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }

    public function test_guest_is_never_sent_to_the_public_customer_login(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $this->assertSame(route('admin.login'), $response->headers->get('Location'));
        $this->assertNotSame(route('login'), $response->headers->get('Location'));
    }

    public function test_customer_cannot_access_admin(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get('/admin')->assertForbidden();
    }

    public function test_admin_can_access_admin_dashboard(): void
    {
        $this->actingAs($this->admin(['name' => 'Priya Admin']))
            ->get('/admin')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Priya Admin')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_admin_login_page_loads_and_is_not_the_customer_login(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('Administration')
            ->assertSee('action="'.route('admin.login.store').'"', false)
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('action="'.route('login.store').'"', false)
            ->assertDontSee(route('admin.login.store'), false);
    }

    public function test_admin_login_succeeds_with_correct_credentials(): void
    {
        $admin = $this->admin();

        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'Correct-Horse-9!'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_development_seed_admin_can_sign_in(): void
    {
        $this->seed(DevelopmentAdminSeeder::class);

        $this->post(route('admin.login.store'), ['email' => DevelopmentAdminSeeder::EMAIL, 'password' => DevelopmentAdminSeeder::PASSWORD])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertTrue(auth()->user()->isAdmin());
    }

    public function test_invalid_credentials_fail_with_a_generic_message(): void
    {
        $admin = $this->admin();

        $this->from(route('admin.login'))
            ->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'wrong-password'])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors(['email' => 'Invalid credentials.']);

        // Unknown email gets exactly the same message (no account enumeration).
        $this->from(route('admin.login'))
            ->post(route('admin.login.store'), ['email' => 'nobody@example.test', 'password' => 'whatever'])
            ->assertSessionHasErrors(['email' => 'Invalid credentials.']);

        $this->assertGuest();
    }

    public function test_customer_credentials_cannot_sign_in_to_admin(): void
    {
        $customer = User::factory()->create(['password' => 'Customer-Pass-1!']);

        $this->from(route('admin.login'))
            ->post(route('admin.login.store'), ['email' => $customer->email, 'password' => 'Customer-Pass-1!'])
            ->assertSessionHasErrors(['email' => 'Invalid credentials.']);

        $this->assertGuest();
    }

    public function test_session_is_regenerated_after_login(): void
    {
        $admin = $this->admin();

        $this->get(route('admin.login'));
        $before = session()->getId();

        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'Correct-Horse-9!']);

        $this->assertNotSame($before, session()->getId());
        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_logout_invalidates_session_and_regenerates_token(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin');

        $sessionBefore = session()->getId();
        $tokenBefore = session()->token();

        $this->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('status');

        $this->assertGuest();
        $this->assertNotSame($sessionBefore, session()->getId());
        $this->assertNotSame($tokenBefore, session()->token());

        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_logout_requires_an_admin_session(): void
    {
        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->get('/admin/logout')->assertStatus(405);
    }

    public function test_non_admin_users_remain_blocked_on_every_admin_route(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->post(route('admin.logout'))->assertForbidden();
        $this->getJson(route('admin.dashboard'))->assertForbidden();
    }

    public function test_signed_in_admin_visiting_login_is_sent_to_dashboard(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.login'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_intended_admin_url_is_honoured_but_external_urls_are_not(): void
    {
        $admin = $this->admin();

        $this->withSession(['url.intended' => 'https://evil.example/phish'])
            ->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'Correct-Horse-9!'])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        $admin = $this->admin();
        RateLimiter::clear('admin-login|'.strtolower($admin->email).'|127.0.0.1');

        for ($i = 0; $i < AdminLoginController::MAX_ATTEMPTS; $i++) {
            $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'wrong']);
        }

        // Even the correct password is refused while locked out.
        $this->from(route('admin.login'))
            ->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'Correct-Horse-9!'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertStringContainsString('Too many sign-in attempts', session('errors')->first('email'));
    }

    public function test_failed_login_never_logs_or_flashes_the_password(): void
    {
        Log::spy();
        $admin = $this->admin();

        $this->from(route('admin.login'))
            ->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'Secret-Leak-Check-1']);

        $this->assertNull(session()->getOldInput('password'));
        Log::shouldNotHaveReceived('info');
        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('error');
    }

    public function test_admin_forms_are_csrf_protected(): void
    {
        // Laravel deliberately skips CSRF verification while running unit tests,
        // so verify the protection structurally: the routes run through the "web"
        // group (which contains the CSRF middleware) and the forms send a token.
        $webGroup = app(HttpKernel::class)->getMiddlewareGroups()['web'];
        $this->assertContains(ValidateCsrfToken::class, $webGroup);

        foreach (['admin.login.store', 'admin.logout'] as $name) {
            $this->assertContains('web', app('router')->getRoutes()->getByName($name)->gatherMiddleware());
        }

        $this->get(route('admin.login'))->assertSee('name="_token"', false);
        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertSee('name="_token"', false);
    }

    public function test_is_admin_cannot_be_mass_assigned(): void
    {
        $user = User::create(['name' => 'Sneaky', 'email' => 'sneaky@example.test', 'password' => 'password', 'is_admin' => true]);

        $this->assertFalse($user->fresh()->isAdmin());
    }

    public function test_public_customer_login_never_authenticates_an_admin(): void
    {
        $admin = $this->admin();

        $this->from(route('login'))
            ->post(route('login.store'), ['login' => $admin->email, 'password' => 'Correct-Horse-9!'])
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
