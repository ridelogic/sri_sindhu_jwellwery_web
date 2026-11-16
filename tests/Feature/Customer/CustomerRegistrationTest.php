<?php

namespace Tests\Feature\Customer;

use App\Enums\AccountStatus;
use App\Enums\CustomerPlanStatus;
use App\Http\Controllers\Auth\LoginController;
use App\Models\CustomerPlan;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\SignsInWithOtp;
use Tests\TestCase;

class CustomerRegistrationTest extends TestCase
{
    use RefreshDatabase;
    use SignsInWithOtp;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useDevelopmentSms();
        $this->plan = Plan::factory()->create(['name' => 'Gold Basic', 'slug' => 'gold-basic', 'monthly_amount' => 5000, 'duration_months' => 11]);
    }

    /** Step 1: details + plan → OTP sent (no account yet). */
    private function submitDetails(array $overrides = []): TestResponse
    {
        return $this->from(route('register'))->post(route('register.store'), $overrides + [
            'name' => 'Lakshmi Raman',
            'mobile' => '+91 98765 43210',
            'email' => 'Lakshmi.Raman@Example.com',
            'plan' => 'gold-basic',
            'terms' => '1',
        ]);
    }

    /** Full registration: details, then the OTP verifies the mobile and creates the account. */
    private function register(array $overrides = []): TestResponse
    {
        $details = $this->submitDetails($overrides);
        $code = session('otp_development_code');

        return $code === null ? $details : $this->from(route('register'))->post(route('register.verify'), ['otp' => $code]);
    }

    public function test_guest_can_view_register_page_with_active_plans(): void
    {
        Plan::factory()->inactive()->create(['name' => 'Retired Plan', 'slug' => 'retired']);

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Create Your')
            ->assertSee('Gold Basic')->assertSee('₹5,000')->assertSee('11 months')->assertSee('₹55,000')
            ->assertDontSee('Retired Plan')
            ->assertSee('name="_token"', false)
            ->assertSee('name="mobile"', false)
            ->assertDontSee('name="password"', false)
            ->assertDontSee('name="password_confirmation"', false)
            ->assertDontSee('name="monthly_amount"', false)
            ->assertDontSee('name="total_commitment"', false);
    }

    public function test_customer_can_register_and_is_signed_in(): void
    {
        $this->register()->assertRedirect(route('customer.dashboard'));

        $customer = User::query()->where('email', 'lakshmi.raman@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertGuest('web'); // never signed in as admin
        $this->assertFalse($customer->isAdmin());
        $this->assertSame('9876543210', $customer->mobile_number); // normalised
        $this->assertSame('Lakshmi Raman', $customer->name);
        $this->assertSame(AccountStatus::Active, $customer->status); // self-registered customers start Active

        $this->get(route('customer.dashboard'))->assertOk()->assertSee('Welcome, ');
    }

    public function test_no_account_exists_until_the_mobile_is_verified(): void
    {
        $this->submitDetails()->assertRedirect(route('register'))->assertSessionHas('status');
        $code = session('otp_development_code');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('customer_plans', 0);
        $this->get(route('register'))->assertSee('name="otp"', false)->assertSee('+91 98XXX XX210')->assertDontSee('name="email"', false);

        // A wrong code creates nothing.
        $this->from(route('register'))->post(route('register.verify'), ['otp' => $code === '000000' ? '111111' : '000000'])
            ->assertSessionHasErrors(['otp' => LoginController::INVALID_OTP_MESSAGE]);
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest('customer');

        // Abandoning the step clears the pending registration.
        $this->post(route('register.cancel'))->assertRedirect(route('register'));
        $this->from(route('register'))->post(route('register.verify'), ['otp' => $code])->assertRedirect(route('register'));
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_otp_cannot_be_reused_and_duplicates_are_rechecked(): void
    {
        $this->submitDetails();
        $code = session('otp_development_code');

        // Someone else takes the number while the OTP is outstanding.
        User::factory()->create(['mobile_number' => '9876543210']);
        $this->from(route('register'))->post(route('register.verify'), ['otp' => $code])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors(['mobile_number' => 'An account with this mobile number already exists. Please log in instead.']);

        $this->assertSame(1, User::query()->count());
        $this->assertGuest('customer');
    }

    public function test_registration_needs_no_password_and_stores_an_unusable_one(): void
    {
        $this->register(['password' => 'chosen-by-attacker-1', 'password_confirmation' => 'chosen-by-attacker-1'])->assertRedirect(route('customer.dashboard'));

        $stored = User::query()->where('email', 'lakshmi.raman@example.com')->value('password');
        $this->assertTrue(Hash::isHashed($stored));
        $this->assertFalse(Hash::check('chosen-by-attacker-1', $stored)); // submitted passwords are ignored
    }

    public function test_registration_creates_customer_plan_with_scheme_number_and_copied_commitment(): void
    {
        $this->register();

        $customer = User::query()->where('email', 'lakshmi.raman@example.com')->firstOrFail();
        $scheme = $customer->customerPlans()->sole();

        $this->assertTrue($scheme->plan->is($this->plan));
        $this->assertMatchesRegularExpression('/^SSJ-\d{4}-[A-Z0-9]{6}$/', $scheme->scheme_number);
        $this->assertSame('5000.00', $scheme->monthly_amount);
        $this->assertSame(11, $scheme->duration_months);
        $this->assertSame('55000.00', $scheme->total_commitment);
        $this->assertSame(CustomerPlanStatus::Pending, $scheme->status);
    }

    public function test_later_plan_change_does_not_alter_customer_commitment(): void
    {
        $this->register();
        $this->plan->update(['monthly_amount' => 6000, 'duration_months' => 12]);

        $scheme = CustomerPlan::query()->sole();
        $this->assertSame('5000.00', $scheme->monthly_amount);
        $this->assertSame(11, $scheme->duration_months);
        $this->assertSame('55000.00', $scheme->total_commitment);
        $this->assertSame('72000.00', $this->plan->fresh()->total_amount);
    }

    public function test_submitted_financial_values_are_ignored(): void
    {
        $this->register([
            'monthly_amount' => '1', 'duration_months' => '1', 'total_commitment' => '1', 'total_amount' => '1',
            'scheme_number' => 'HACKED-1', 'status' => 'completed', 'is_admin' => '1', 'plan_id' => '999',
        ])->assertRedirect(route('customer.dashboard'));

        $scheme = CustomerPlan::query()->sole();
        $this->assertSame('5000.00', $scheme->monthly_amount);
        $this->assertSame('55000.00', $scheme->total_commitment);
        $this->assertNotSame('HACKED-1', $scheme->scheme_number);
        $this->assertSame(CustomerPlanStatus::Pending, $scheme->status);
        $this->assertTrue($scheme->plan->is($this->plan));
        $this->assertFalse(User::query()->where('email', 'lakshmi.raman@example.com')->firstOrFail()->isAdmin());
    }

    public function test_selected_plan_is_required_must_exist_and_be_active(): void
    {
        Plan::factory()->inactive()->create(['slug' => 'retired']);

        $this->register(['plan' => ''])->assertSessionHasErrors('plan');
        $this->register(['plan' => 'does-not-exist'])->assertSessionHasErrors(['plan' => 'Please choose one of the available plans.']);
        $this->register(['plan' => 'retired'])->assertSessionHasErrors('plan');
        $this->register(['plan' => (string) $this->plan->id])->assertSessionHasErrors('plan'); // ids are not accepted

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('customer_plans', 0);
        $this->assertGuest('customer');
    }

    public function test_identity_fields_are_validated(): void
    {
        $this->register(['name' => ''])->assertSessionHasErrors('name');
        $this->register(['mobile' => '12345'])->assertSessionHasErrors(['mobile' => 'Please enter a valid 10-digit Indian mobile number.']);
        $this->register(['mobile' => '5876543210'])->assertSessionHasErrors('mobile'); // must start 6–9
        $this->register(['email' => ''])->assertSessionHasErrors('email');
        $this->register(['email' => 'not-an-email'])->assertSessionHasErrors('email');
        $this->register(['terms' => null])->assertSessionHasErrors('terms');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_email_and_mobile_must_be_unique_case_insensitively(): void
    {
        User::factory()->create(['email' => 'lakshmi.raman@example.com', 'mobile_number' => '9000000001']);

        $this->register(['email' => 'LAKSHMI.RAMAN@example.com'])->assertSessionHasErrors('email');
        $this->register(['email' => 'new@example.com', 'mobile' => '09000000001'])
            ->assertSessionHasErrors(['mobile' => 'An account with this mobile number already exists. Please log in instead.']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_admin_email_cannot_register_as_a_customer(): void
    {
        User::factory()->admin()->create(['email' => 'admin@example.test']);

        $this->register(['email' => 'admin@example.test'])->assertSessionHasErrors('email');
        $this->assertSame(1, User::query()->count());
        $this->assertGuest('customer');
    }

    public function test_signed_in_customer_is_redirected_away_from_register_and_login(): void
    {
        $this->actingAs(User::factory()->create(), 'customer');

        $this->get(route('register'))->assertRedirect(route('customer.dashboard'));
        $this->get(route('login'))->assertRedirect(route('customer.dashboard'));
    }

    public function test_register_page_without_database_plans_does_not_offer_registration(): void
    {
        Plan::query()->delete();

        $this->get(route('register'))->assertOk()->assertSee('data-no-plans', false)->assertDontSee('name="plan"', false);
    }

    public function test_registration_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->register(['email' => "bad{$i}"]);
        }

        $this->register()->assertStatus(429);
    }
}
