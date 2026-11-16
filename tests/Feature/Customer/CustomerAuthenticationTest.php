<?php

namespace Tests\Feature\Customer;

use App\Http\Controllers\Auth\LoginController;
use App\Models\CustomerOtp;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\SignsInWithOtp;
use Tests\TestCase;

/**
 * Customer sign-in with mobile number + OTP on the "customer" guard, and the
 * separation from admin (password) sign-in.
 */
class CustomerAuthenticationTest extends TestCase
{
    use RefreshDatabase;
    use SignsInWithOtp;

    private const MOBILE = '9876543210';

    protected function setUp(): void
    {
        parent::setUp();
        $this->useDevelopmentSms();
    }

    private function customer(array $attributes = []): User
    {
        // Overrides first: with +, the left-hand array's keys win.
        return User::factory()->create($attributes + ['email' => 'priya@example.com', 'mobile_number' => self::MOBILE]);
    }

    public function test_guest_can_view_login_page(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertSee('Welcome')
            ->assertSee('name="mobile"', false)
            ->assertSee('Send OTP')
            ->assertDontSee('name="password"', false)
            ->assertDontSee('name="otp"', false)
            ->assertSee('name="_token"', false)
            ->assertDontSee('launching soon');
    }

    public function test_customer_can_login_with_mobile_and_otp(): void
    {
        $customer = $this->customer();

        $this->requestLoginOtp('+91 98765 43210')->assertRedirect(route('login'))
            ->assertSessionHas('status', str_replace(':mobile', '+91 98XXX XX210', LoginController::SENT_MESSAGE));
        $code = $this->developmentOtp();
        $this->get(route('login'))->assertOk()->assertSee('name="otp"', false)->assertSee('+91 98XXX XX210')->assertSee('Verify OTP')
            ->assertSee('Resend OTP')->assertSee('Change mobile number')->assertDontSee('name="mobile"', false);

        $this->submitLoginOtp($code)->assertRedirect(route('customer.dashboard'));

        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertGuest('web');
        $this->get(route('customer.dashboard'))->assertOk()->assertSee('Welcome, ');
    }

    public function test_wrong_otp_and_unregistered_numbers_fail_generically(): void
    {
        $this->customer();

        $this->requestLoginOtp(self::MOBILE);
        $this->submitLoginOtp('000000' === $this->developmentOtp() ? '111111' : '000000')
            ->assertSessionHasErrors(['otp' => LoginController::INVALID_OTP_MESSAGE]);

        // An unregistered number gets the same response, but no OTP is created or sent.
        $this->post(route('login.cancel'));
        $this->requestLoginOtp('9123456789')->assertSessionHas('status', str_replace(':mobile', '+91 91XXX XX789', LoginController::SENT_MESSAGE));
        $this->assertNull($this->developmentOtp());
        $this->assertSame(0, CustomerOtp::query()->where('mobile_number', '9123456789')->count());
        $this->submitLoginOtp('123456')->assertSessionHasErrors(['otp' => LoginController::INVALID_OTP_MESSAGE]);

        $this->assertGuest('customer');
    }

    public function test_invalid_mobile_number_is_rejected(): void
    {
        foreach (['12345', '5876543210', 'not-a-number', ''] as $mobile) {
            $this->from(route('login'))->post(route('login.store'), ['mobile' => $mobile])->assertSessionHasErrors('mobile');
        }
        $this->assertSame(0, CustomerOtp::query()->count());
    }

    public function test_admin_cannot_sign_in_through_customer_login(): void
    {
        User::factory()->admin()->create(['email' => 'admin@example.test', 'mobile_number' => '9000000009']);

        $this->otpLogin('9000000009')->assertSessionHasErrors(['otp' => LoginController::INVALID_OTP_MESSAGE]);

        $this->assertSame(0, CustomerOtp::query()->count()); // treated like an unregistered number
        $this->assertGuest('customer');
        $this->assertGuest('web');
    }

    public function test_customer_session_is_regenerated_on_login(): void
    {
        $this->customer();
        $this->requestLoginOtp(self::MOBILE);
        $before = session()->getId();

        $this->submitLoginOtp($this->developmentOtp());

        $this->assertNotSame($before, session()->getId());
    }

    public function test_customer_can_logout(): void
    {
        $this->actingAs($this->customer(), 'customer')->get(route('customer.dashboard'))->assertOk();
        $token = session()->token();
        $id = session()->getId();

        $this->post(route('customer.logout'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('notice', 'You have been signed out.');

        $this->assertGuest('customer');
        $this->assertNotSame($token, session()->token());
        $this->assertNotSame($id, session()->getId());
        $this->get(route('customer.dashboard'))->assertRedirect(route('login'));
    }

    public function test_logout_requires_a_customer_session(): void
    {
        $this->post(route('customer.logout'))->assertRedirect(route('login'));
        $this->get(route('customer.logout'))->assertStatus(405);
    }

    public function test_intended_customer_url_is_honoured_but_admin_urls_are_not(): void
    {
        $this->customer();

        $this->get(route('customer.profile'))->assertRedirect(route('login'));
        $this->otpLogin(self::MOBILE)->assertRedirect(route('customer.profile'));

        $this->post(route('customer.logout'));
        $this->withSession(['url.intended' => route('admin.dashboard')]);
        $this->otpLogin(self::MOBILE)->assertRedirect(route('customer.dashboard'));
    }

    public function test_customer_forms_are_csrf_protected(): void
    {
        // Laravel skips CSRF verification in tests, so verify the wiring.
        $this->assertContains(ValidateCsrfToken::class, app(HttpKernel::class)->getMiddlewareGroups()['web']);

        foreach (['login.store', 'login.verify', 'login.resend', 'login.cancel', 'register.store', 'register.verify', 'register.resend', 'customer.logout', 'customer.plans.join'] as $name) {
            $this->assertContains('web', app('router')->getRoutes()->getByName($name)->gatherMiddleware(), $name);
        }

        $this->get(route('login'))->assertSee('name="_token"', false);
    }

    public function test_otps_are_never_written_to_the_log(): void
    {
        Log::spy();
        $this->customer();

        $this->requestLoginOtp(self::MOBILE);
        $code = $this->developmentOtp();
        $this->submitLoginOtp($code === '999999' ? '999998' : '999999');
        $this->submitLoginOtp($code)->assertRedirect(route('customer.dashboard'));

        Log::shouldNotHaveReceived('info');
        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('error');
    }

    // Admin / customer separation -----------------------------------------------------

    public function test_admin_session_cannot_use_the_customer_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'web')->get(route('customer.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest('customer');
    }

    public function test_admin_account_on_the_customer_guard_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'customer')->get(route('customer.dashboard'))->assertForbidden();
        $this->assertGuest('customer');
    }

    public function test_customer_cannot_access_admin_pages(): void
    {
        $this->actingAs($this->customer(), 'customer');

        foreach (['admin.dashboard', 'admin.plans.index', 'admin.gold-rates.index'] as $route) {
            $this->get(route($route))->assertRedirect(route('admin.login'));
        }

        // Even if a customer account somehow had a web-guard session, it is not an admin.
        $this->actingAs($this->customer(['email' => 'other@example.com', 'mobile_number' => '9811111111']), 'web')->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_admin_and_customer_sessions_stay_independent(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'admin@example.test', 'password' => 'Admin-Pass-123']);
        $customer = $this->customer();

        $this->post(route('admin.login.store'), ['email' => 'admin@example.test', 'password' => 'Admin-Pass-123'])->assertRedirect(route('admin.dashboard'));
        $this->otpLogin(self::MOBILE)->assertRedirect(route('customer.dashboard'));

        $this->assertAuthenticatedAs($admin, 'web');
        $this->assertAuthenticatedAs($customer, 'customer');

        $this->get(route('admin.dashboard'))->assertOk()->assertSee($admin->name);
        $this->get(route('customer.dashboard'))->assertOk()->assertSee('Welcome, ');
    }

    public function test_admin_password_login_and_dashboard_still_work(): void
    {
        User::factory()->admin()->create(['email' => 'admin@example.test', 'password' => 'Admin-Pass-123']);

        $this->get(route('admin.login'))->assertOk()->assertSee('name="password"', false);
        $this->post(route('admin.login.store'), ['email' => 'admin@example.test', 'password' => 'wrong-password'])->assertSessionHasErrors();
        $this->assertGuest('web');
        $this->post(route('admin.login.store'), ['email' => 'admin@example.test', 'password' => 'Admin-Pass-123'])->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertOk();
        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }
}
