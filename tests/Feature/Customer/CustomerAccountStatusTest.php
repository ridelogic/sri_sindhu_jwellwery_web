<?php

namespace Tests\Feature\Customer;

use App\Enums\AccountStatus;
use App\Enums\CustomerAuditAction;
use App\Http\Controllers\Auth\LoginController;
use App\Models\CustomerAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\SignsInWithOtp;
use Tests\TestCase;

/**
 * Inactive customers cannot sign in until an admin activates them.
 * Active ↔ Inactive is changed only by an admin; Blocked is unchanged.
 */
class CustomerAccountStatusTest extends TestCase
{
    use RefreshDatabase;
    use SignsInWithOtp;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useDevelopmentSms();
        $this->admin = User::factory()->admin()->create();
    }

    private function customer(AccountStatus $status, string $email): User
    {
        return User::factory()->create(['email' => $email, 'status' => $status]);
    }

    /** Sign in by mobile number + OTP as the customer with this email. */
    private function login(string $email): TestResponse
    {
        return $this->otpLogin((string) User::query()->where('email', $email)->value('mobile_number'));
    }

    public function test_inactive_customer_login_is_rejected_with_a_clear_message(): void
    {
        $this->customer(AccountStatus::Inactive, 'inactive@example.com');

        $this->login('inactive@example.com')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['mobile' => 'Your account is inactive. Please contact the administrator to activate your account.']);
        $this->assertGuest('customer');
    }

    public function test_active_customer_login_succeeds(): void
    {
        $customer = $this->customer(AccountStatus::Active, 'active@example.com');

        $this->login('active@example.com')->assertRedirect(route('customer.dashboard'));
        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_blocked_customer_behaviour_is_unchanged(): void
    {
        $this->customer(AccountStatus::Blocked, 'blocked@example.com');

        $this->login('blocked@example.com')->assertSessionHasErrors(['mobile' => LoginController::BLOCKED_MESSAGE]);
        $this->assertGuest('customer');
    }

    public function test_inactive_customer_cannot_reach_the_portal_by_direct_url(): void
    {
        $customer = $this->customer(AccountStatus::Inactive, 'inactive@example.com');

        foreach (['customer.dashboard', 'customer.schemes.index', 'customer.payments.index', 'customer.kyc', 'customer.plans.index'] as $route) {
            // A stale session (e.g. deactivated while signed in) is ended on the next request.
            $this->actingAs($customer, 'customer')->get(route($route))
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors(['mobile' => LoginController::INACTIVE_MESSAGE]);
            $this->assertGuest('customer');
        }

        $this->actingAs($customer, 'customer')->getJson(route('customer.dashboard'))->assertForbidden();
    }

    public function test_admin_can_activate_an_inactive_customer_who_can_then_log_in(): void
    {
        $customer = $this->customer(AccountStatus::Inactive, 'new@example.com');

        $this->actingAs($this->admin, 'web')->post(route('admin.customers.activate', $customer))
            ->assertRedirect(route('admin.customers.show', $customer))->assertSessionHas('status');

        $this->assertSame(AccountStatus::Active, $customer->fresh()->status);
        $log = CustomerAuditLog::query()->sole();
        $this->assertSame(CustomerAuditAction::Activated, $log->action);
        $this->assertSame($this->admin->id, $log->actor_id);

        auth('web')->logout();
        $this->login('new@example.com')->assertRedirect(route('customer.dashboard'));
        $this->assertAuthenticatedAs($customer->fresh(), 'customer');
    }

    public function test_admin_can_deactivate_an_active_customer(): void
    {
        $customer = $this->customer(AccountStatus::Active, 'active@example.com');

        $this->actingAs($this->admin, 'web')->get(route('admin.customers.show', $customer))->assertSee('Deactivate Customer')->assertDontSee('Activate Customer</button>', false);
        $this->post(route('admin.customers.deactivate', $customer))->assertRedirect(route('admin.customers.show', $customer));

        $this->assertSame(AccountStatus::Inactive, $customer->fresh()->status);
        $this->assertSame(CustomerAuditAction::Deactivated, CustomerAuditLog::query()->sole()->action);
        $this->get(route('admin.customers.show', $customer))->assertSee('Activate Customer');

        auth('web')->logout();
        $this->login('active@example.com')->assertSessionHasErrors(['mobile' => LoginController::INACTIVE_MESSAGE]);
        $this->assertGuest('customer');
    }

    public function test_activate_and_deactivate_only_apply_to_the_expected_status(): void
    {
        $blocked = $this->customer(AccountStatus::Blocked, 'blocked@example.com');
        $active = $this->customer(AccountStatus::Active, 'active@example.com');
        $inactive = $this->customer(AccountStatus::Inactive, 'inactive@example.com');

        $this->actingAs($this->admin, 'web');
        $this->post(route('admin.customers.activate', $blocked));     // blocked → use Unblock
        $this->post(route('admin.customers.deactivate', $blocked));
        $this->post(route('admin.customers.activate', $active));      // already active
        $this->post(route('admin.customers.deactivate', $inactive));  // already inactive

        $this->assertSame(AccountStatus::Blocked, $blocked->fresh()->status);
        $this->assertSame(AccountStatus::Active, $active->fresh()->status);
        $this->assertSame(AccountStatus::Inactive, $inactive->fresh()->status);
        $this->assertSame(0, CustomerAuditLog::query()->count());
    }

    public function test_only_admins_can_change_account_status(): void
    {
        $target = $this->customer(AccountStatus::Inactive, 'target@example.com');
        $other = $this->customer(AccountStatus::Active, 'other@example.com');

        $this->post(route('admin.customers.activate', $target))->assertRedirect(route('admin.login'));
        $this->actingAs($other)->post(route('admin.customers.activate', $target))->assertForbidden();
        $this->actingAs($other)->post(route('admin.customers.deactivate', $other))->assertForbidden();

        $this->assertSame(AccountStatus::Inactive, $target->fresh()->status);
        $this->assertSame(AccountStatus::Active, $other->fresh()->status);
    }

    public function test_existing_customer_statuses_are_not_changed_automatically(): void
    {
        $active = $this->customer(AccountStatus::Active, 'active@example.com');
        $blocked = $this->customer(AccountStatus::Blocked, 'blocked@example.com');

        // Creating a new (inactive) customer, activating it, and existing customers signing in
        // never touch anyone else's status.
        $this->actingAs($this->admin, 'web')->post(route('admin.customers.store'), [
            'name' => 'New Customer', 'email' => 'new@example.com', 'mobile' => '9876543210',
        ])->assertSessionHasNoErrors();
        $new = User::query()->where('email', 'new@example.com')->sole();
        $this->assertSame(AccountStatus::Inactive, $new->status);
        $this->post(route('admin.customers.activate', $new));

        auth('web')->logout();
        $this->login('active@example.com');
        $this->login('blocked@example.com');

        $this->assertSame(AccountStatus::Active, $active->fresh()->status);
        $this->assertSame(AccountStatus::Blocked, $blocked->fresh()->status);
        $this->assertSame($active->updated_at->toDateTimeString(), $active->fresh()->updated_at->toDateTimeString());
    }
}
