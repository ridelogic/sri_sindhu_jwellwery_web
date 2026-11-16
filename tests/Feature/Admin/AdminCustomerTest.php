<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountStatus;
use App\Enums\CustomerAuditAction;
use App\Enums\KycStatus;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Auth\LoginController;
use App\Models\CustomerAuditLog;
use App\Models\CustomerPlan;
use App\Models\GoldTransaction;
use App\Models\KycProfile;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\Concerns\FakesPaymentGateway;
use Tests\Concerns\SignsInWithOtp;
use Tests\TestCase;

class AdminCustomerTest extends TestCase
{
    use FakesPaymentGateway;
    use RefreshDatabase;
    use SignsInWithOtp;

    private const PASSWORD = 'Str0ng!Passw0rd';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-07 11:00:00');
        $this->useDevelopmentSms();
        $this->admin = User::factory()->admin()->create(['name' => 'Office Admin']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function asAdmin(): static
    {
        return $this->actingAs($this->admin, 'web');
    }

    private function newCustomer(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Lakshmi Narayanan',
            'email' => 'lakshmi@example.com',
            'mobile' => '+91 98765 43210',
        ];
    }

    /** Customer sign-in by mobile number + OTP (development sender). */
    private function customerLogin(string $mobile)
    {
        return $this->otpLogin($mobile);
    }

    // List ----------------------------------------------------------------------------------

    public function test_admin_can_view_customers_without_sensitive_kyc_data(): void
    {
        $customer = User::factory()->create(['name' => 'Priya Sundar', 'email' => 'priya@example.com', 'mobile_number' => '9876543210']);
        KycProfile::factory()->for($customer)->create(['kyc_status' => KycStatus::Verified, 'pan_number' => 'BKLPS7319M', 'aadhaar_last4' => '4821', 'address_line1' => '12 Temple Street']);
        CustomerPlan::factory()->for($customer)->count(2)->create();
        CustomerPlan::factory()->for($customer)->status(\App\Enums\CustomerPlanStatus::Cancelled)->create();

        $response = $this->asAdmin()->get(route('admin.customers.index'))->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Priya Sundar')->assertSee('priya@example.com')->assertSee('+91 98765 43210')
            ->assertSee('Approved')->assertSee('Active')->assertSee('07 Oct 2026')
            ->assertSee(route('admin.customers.show', $customer), false)
            ->assertDontSee('data-customer-row="'.$this->admin->id.'"', false); // admins are not customers

        $this->assertMatchesRegularExpression('/data-customer-row="'.$customer->id.'".*?data-open-schemes>2</s', $response->getContent());
        foreach (['BKLPS7319M', 'XXXXXX319M', '4821', '12 Temple Street'] as $secret) {
            $response->assertDontSee($secret);
        }
    }

    public function test_non_admins_cannot_access_customer_management(): void
    {
        $customer = User::factory()->create();

        // Guest: always the admin login — including for ids that don't exist (no probing).
        foreach ([route('admin.customers.index'), route('admin.customers.create'), route('admin.customers.show', $customer), route('admin.customers.edit', $customer), '/admin/customers/999999'] as $url) {
            $this->get($url)->assertRedirect(route('admin.login'));
        }
        $this->post(route('admin.customers.store'), $this->newCustomer())->assertRedirect(route('admin.login'));
        $this->post(route('admin.customers.block', $customer))->assertRedirect(route('admin.login'));

        // Signed-in customer (customer guard).
        $this->actingAs($customer, 'customer');
        foreach ([route('admin.customers.index'), route('admin.customers.show', $customer), '/admin/customers/999999'] as $url) {
            $this->get($url)->assertRedirect(route('admin.login'));
        }
        $this->put(route('admin.customers.update', $customer), ['name' => 'Hacked', 'email' => 'h@x.com', 'mobile' => '9123456780'])->assertRedirect(route('admin.login'));
        $this->post(route('admin.customers.block', $customer))->assertRedirect(route('admin.login'));

        // A customer account on the web guard is still not an admin.
        $this->actingAs($customer, 'web')->get(route('admin.customers.index'))->assertForbidden();

        $this->assertSame(AccountStatus::Active, $customer->fresh()->status);
        $this->assertNotSame('Hacked', $customer->fresh()->name);
        $this->assertSame(1, User::query()->customers()->count());
    }

    public function test_pagination_works_newest_first(): void
    {
        foreach (range(1, CustomerController::PER_PAGE + 5) as $i) {
            Carbon::setTestNow(Carbon::parse('2026-10-01 10:00')->addMinutes($i));
            User::factory()->create(['name' => sprintf('Customer %02d', $i)]);
        }

        $this->asAdmin()->get(route('admin.customers.index'))->assertOk()
            ->assertSeeInOrder(['Customer 25', 'Customer 24'])->assertSee('Customer 06')->assertDontSee('Customer 05');
        $this->get(route('admin.customers.index', ['page' => 2]))->assertOk()
            ->assertSee('Customer 05')->assertSee('Customer 01')->assertDontSee('Customer 06');
    }

    public function test_search_by_name_email_and_mobile(): void
    {
        User::factory()->create(['name' => 'Priya Sundar', 'email' => 'priya@example.com', 'mobile_number' => '9876543210']);
        User::factory()->create(['name' => 'Arjun Ramesh', 'email' => 'arjun@sample.org', 'mobile_number' => '9123456780']);
        $this->asAdmin();

        $this->get(route('admin.customers.index', ['q' => 'priya']))->assertSee('Priya Sundar')->assertDontSee('Arjun Ramesh');
        $this->get(route('admin.customers.index', ['q' => 'sample.org']))->assertSee('Arjun Ramesh')->assertDontSee('Priya Sundar');
        $this->get(route('admin.customers.index', ['q' => '+91 98765 43210']))->assertSee('Priya Sundar')->assertDontSee('Arjun Ramesh');
        $this->get(route('admin.customers.index', ['q' => '45678']))->assertSee('Arjun Ramesh')->assertDontSee('Priya Sundar');
        $this->get(route('admin.customers.index', ['q' => 'nobody-here']))->assertSee('No customers match these filters.');
    }

    public function test_registered_users_table_has_the_expected_columns_and_view_action(): void
    {
        $customer = User::factory()->create(['name' => 'Priya Sundar', 'email' => 'priya@example.com', 'mobile_number' => '9876543210']);

        $response = $this->asAdmin()->get(route('admin.customers.index'))->assertOk()
            ->assertSee('Registered Users')
            ->assertSeeInOrder(['ID', 'Profile', 'Name', 'Email', 'Mobile', 'Registered At', 'KYC Status', 'User Status', 'Active Schemes', 'Nominee KYC', 'Nominee Status', 'View User'])
            ->assertSeeInOrder(['07 Oct 2026', '11:00'])
            ->assertSee('data-kyc-badge="not_submitted"', false)
            ->assertSee('data-account-status="active"', false);

        $this->assertMatchesRegularExpression('/data-customer-row="'.$customer->id.'".*?href="'.preg_quote(route('admin.customers.show', $customer), '/').'"[^>]*>View</s', $response->getContent());
    }

    public function test_filters_by_mobile_number_server_side(): void
    {
        User::factory()->create(['name' => 'Priya Sundar', 'mobile_number' => '9876543210']);
        User::factory()->create(['name' => 'Arjun Ramesh', 'mobile_number' => '9123456780']);
        $this->asAdmin();

        $this->get(route('admin.customers.index', ['mobile' => '98765']))->assertSee('Priya Sundar')->assertDontSee('Arjun Ramesh');            // partial
        $this->get(route('admin.customers.index', ['mobile' => '+91 91234 56780']))->assertSee('Arjun Ramesh')->assertDontSee('Priya Sundar'); // exact, formatted
        $this->get(route('admin.customers.index', ['mobile' => 'abc']))->assertSee('No customers match these filters.');
        $this->get(route('admin.customers.index', ['mobile' => '98765', 'q' => 'arjun']))->assertSee('No customers match these filters.');  // filters combine
    }

    public function test_filters_by_inactive_status(): void
    {
        User::factory()->create(['name' => 'Waiting Person', 'status' => AccountStatus::Inactive]);
        User::factory()->create(['name' => 'Active Person']);

        $this->asAdmin()->get(route('admin.customers.index', ['status' => 'inactive']))
            ->assertSee('Waiting Person')->assertDontSee('Active Person')->assertSee('data-account-status="inactive"', false);
    }

    public function test_filters_by_kyc_and_account_status(): void
    {
        $approved = User::factory()->create(['name' => 'Approved Person']);
        KycProfile::factory()->for($approved)->create(['kyc_status' => KycStatus::Verified]);
        $pending = User::factory()->create(['name' => 'Pending Person']);
        KycProfile::factory()->for($pending)->create(['kyc_status' => KycStatus::Submitted]);
        $rejected = User::factory()->create(['name' => 'Rejected Person']);
        KycProfile::factory()->for($rejected)->create(['kyc_status' => KycStatus::Rejected]);
        User::factory()->create(['name' => 'Fresh Person']); // no KYC at all
        $blocked = User::factory()->create(['name' => 'Blocked Person']);
        $blocked->forceFill(['status' => AccountStatus::Blocked])->save();

        $everyone = ['Approved Person', 'Pending Person', 'Rejected Person', 'Fresh Person', 'Blocked Person'];
        $this->asAdmin();
        foreach ([
            [['kyc' => 'approved'], ['Approved Person']],
            [['kyc' => 'pending_review'], ['Pending Person']],
            [['kyc' => 'rejected'], ['Rejected Person']],
            [['kyc' => 'not_submitted'], ['Fresh Person', 'Blocked Person']],
            [['status' => 'blocked'], ['Blocked Person']],
            [['status' => 'active'], ['Approved Person', 'Pending Person', 'Rejected Person', 'Fresh Person']],
            [['status' => 'blocked', 'kyc' => 'not_submitted'], ['Blocked Person']],
        ] as [$query, $visible]) {
            $response = $this->get(route('admin.customers.index', $query))->assertOk();
            foreach ($everyone as $name) {
                in_array($name, $visible, true) ? $response->assertSee($name) : $response->assertDontSee($name);
            }
        }

        $this->get(route('admin.customers.index', ['status' => 'deleted']))->assertSessionHasErrors('status');
    }

    // Create ---------------------------------------------------------------------------------

    public function test_admin_can_create_customer_who_can_log_in_once_activated(): void
    {
        $response = $this->asAdmin()->post(route('admin.customers.store'), $this->newCustomer() + [
            'is_admin' => '1', 'status' => 'blocked', 'id' => 999, // ignored
        ]);

        $customer = User::query()->where('email', 'lakshmi@example.com')->sole();
        $response->assertRedirect(route('admin.customers.show', $customer))->assertSessionHas('status');

        $this->assertSame('Lakshmi Narayanan', $customer->name);
        $this->assertSame('9876543210', $customer->mobile_number);
        $this->assertFalse($customer->isAdmin());
        $this->assertSame(AccountStatus::Inactive, $customer->status);           // always created Inactive
        $this->assertNotSame(999, $customer->id);
        $this->assertTrue(Hash::isHashed($customer->getRawOriginal('password'))); // random, never disclosed

        // Nothing else is created on their behalf.
        $this->assertNull($customer->kycProfile);
        $this->assertSame(0, $customer->customerPlans()->count());
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, GoldTransaction::query()->count());

        $log = CustomerAuditLog::query()->sole();
        $this->assertSame(CustomerAuditAction::Created, $log->action);
        $this->assertSame($this->admin->id, $log->actor_id);

        // Inactive: even a correct OTP gives no session until the account is activated.
        auth('web')->logout();
        $this->customerLogin('9876543210')
            ->assertRedirect(route('login'))->assertSessionHasErrors(['mobile' => LoginController::INACTIVE_MESSAGE]);
        $this->assertGuest('customer');

        // Activated by an admin (audited), the customer signs in through the normal customer login.
        $this->asAdmin()->post(route('admin.customers.activate', $customer))->assertRedirect(route('admin.customers.show', $customer));
        $this->assertSame(AccountStatus::Active, $customer->fresh()->status);
        $this->assertSame(CustomerAuditAction::Activated, CustomerAuditLog::query()->latest('id')->first()->action);

        auth('web')->logout();
        $this->customerLogin('9876543210')->assertRedirect(route('customer.dashboard'));
        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_new_customer_is_always_inactive_even_when_active_is_forged(): void
    {
        $this->asAdmin()->get(route('admin.customers.create'))->assertOk()
            ->assertSee('Account Status')->assertSeeInOrder(['data-account-status-display', 'Inactive'], false)
            ->assertDontSee('name="status"', false)->assertDontSee('<option>Active</option>', false);

        foreach (['active', 'Active', 'ACTIVE'] as $i => $forged) {
            $this->post(route('admin.customers.store'), $this->newCustomer([
                'email' => "forged{$i}@example.com", 'mobile' => '987654321'.$i, 'status' => $forged, 'account_status' => $forged,
            ]))->assertSessionHasNoErrors();

            $this->assertSame(AccountStatus::Inactive, User::query()->where('email', "forged{$i}@example.com")->sole()->status);
        }
    }

    public function test_inactive_customer_session_is_ended_by_the_portal(): void
    {
        $customer = User::factory()->create(['status' => AccountStatus::Inactive]);

        $this->actingAs($customer, 'customer')->get(route('customer.dashboard'))
            ->assertRedirect(route('login'))->assertSessionHasErrors(['mobile' => LoginController::INACTIVE_MESSAGE]);
    }

    public function test_activation_only_changes_inactive_accounts(): void
    {
        $blocked = User::factory()->create(['status' => AccountStatus::Blocked]);
        $active = User::factory()->create();

        $this->asAdmin()->post(route('admin.customers.activate', $blocked))->assertRedirect();
        $this->asAdmin()->post(route('admin.customers.activate', $active))->assertRedirect();

        $this->assertSame(AccountStatus::Blocked, $blocked->fresh()->status);   // blocked stays blocked (use Unblock)
        $this->assertSame(AccountStatus::Active, $active->fresh()->status);
        $this->assertSame(0, CustomerAuditLog::query()->count());
    }

    public function test_admin_creates_a_customer_without_any_password(): void
    {
        $this->asAdmin()->get(route('admin.customers.create'))->assertOk()
            ->assertSee('name="name"', false)->assertSee('name="email"', false)->assertSee('name="mobile"', false)
            ->assertSee('Account Status')->assertSee('Inactive')
            ->assertDontSee('name="password"', false)->assertDontSee('name="password_confirmation"', false);

        // A submitted password is ignored: no admin-chosen password is ever stored.
        $this->post(route('admin.customers.store'), $this->newCustomer(['password' => 'Gold@123', 'password_confirmation' => 'Gold@123']))
            ->assertSessionHasNoErrors();

        $customer = User::query()->where('email', 'lakshmi@example.com')->sole();
        $this->assertTrue(Hash::isHashed($customer->getRawOriginal('password')));
        $this->assertFalse(Hash::check('Gold@123', $customer->getRawOriginal('password')));
        $this->assertSame(AccountStatus::Inactive, $customer->status);
    }

    public function test_mobile_number_is_required_valid_and_unique(): void
    {
        User::factory()->create(['mobile_number' => '9876543210']);
        $this->asAdmin()->from(route('admin.customers.create'));

        foreach (['', '12345', '5876543210', '98765432101'] as $mobile) {
            $this->post(route('admin.customers.store'), $this->newCustomer(['mobile' => $mobile, 'email' => 'x'.md5($mobile).'@example.com']))->assertSessionHasErrors('mobile');
        }
        $this->post(route('admin.customers.store'), $this->newCustomer(['mobile' => '+91 98765 43210']))->assertSessionHasErrors(['mobile' => 'Another account already uses this mobile number.']);

        $this->assertSame(1, User::query()->customers()->count());
    }

    public function test_create_rejects_duplicates_and_invalid_input(): void
    {
        User::factory()->create(['email' => 'taken@example.com', 'mobile_number' => '9876543210']);
        $this->asAdmin()->from(route('admin.customers.create'));

        $this->post(route('admin.customers.store'), $this->newCustomer(['email' => 'TAKEN@example.com']))->assertSessionHasErrors(['email' => 'Another account already uses this email address.']);
        $this->post(route('admin.customers.store'), $this->newCustomer(['mobile' => '098765 43210']))->assertSessionHasErrors(['mobile' => 'Another account already uses this mobile number.']);
        $this->post(route('admin.customers.store'), $this->newCustomer(['mobile' => '12345']))->assertSessionHasErrors('mobile');
        $this->post(route('admin.customers.store'), $this->newCustomer(['email' => 'not-an-email']))->assertSessionHasErrors('email');
        $this->post(route('admin.customers.store'), $this->newCustomer(['name' => '']))->assertSessionHasErrors('name');

        $this->assertSame(1, User::query()->customers()->count());
    }

    // Edit ------------------------------------------------------------------------------------

    public function test_admin_can_edit_customer_details_only(): void
    {
        $customer = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com', 'mobile_number' => '9876543210']);
        $hash = $customer->password;

        $this->asAdmin()->get(route('admin.customers.edit', $customer))->assertOk()
            ->assertSee('value="Old Name"', false)->assertDontSee('name="password"', false);

        $this->put(route('admin.customers.update', $customer), [
            'name' => 'New Name', 'email' => 'NEW@example.com', 'mobile' => '9123456780',
            'is_admin' => '1', 'status' => 'blocked', 'password' => 'Changed!Pass123', 'id' => 777,
        ])->assertRedirect(route('admin.customers.show', $customer))->assertSessionHas('status');

        $customer->refresh();
        $this->assertSame('New Name', $customer->name);
        $this->assertSame('new@example.com', $customer->email);
        $this->assertSame('9123456780', $customer->mobile_number);
        $this->assertFalse($customer->isAdmin());
        $this->assertSame(AccountStatus::Active, $customer->status);
        $this->assertSame($hash, $customer->password);

        $log = CustomerAuditLog::query()->sole();
        $this->assertSame(CustomerAuditAction::Updated, $log->action);
        $this->assertEqualsCanonicalizing(['name', 'email', 'mobile_number'], $log->fields);
        $this->assertStringNotContainsString('New Name', json_encode($log->toArray())); // field names only, never values

        // Keeping its own email / mobile is not a "duplicate".
        $this->put(route('admin.customers.update', $customer), ['name' => 'New Name', 'email' => 'new@example.com', 'mobile' => '9123456780'])->assertSessionHasNoErrors();
    }

    public function test_edit_rejects_another_customers_email_or_mobile(): void
    {
        User::factory()->create(['email' => 'taken@example.com', 'mobile_number' => '9000000001']);
        $customer = User::factory()->create(['email' => 'mine@example.com', 'mobile_number' => '9000000002']);
        $this->asAdmin()->from(route('admin.customers.edit', $customer));

        $this->put(route('admin.customers.update', $customer), ['name' => 'Me', 'email' => 'taken@example.com', 'mobile' => '9000000002'])->assertSessionHasErrors('email');
        $this->put(route('admin.customers.update', $customer), ['name' => 'Me', 'email' => 'mine@example.com', 'mobile' => '9000000001'])->assertSessionHasErrors('mobile');

        $this->assertSame('mine@example.com', $customer->fresh()->email);
        $this->assertSame('9000000002', $customer->fresh()->mobile_number);
    }

    public function test_edit_cannot_change_scheme_financial_snapshots_or_history(): void
    {
        $this->fakeGateway();
        $customer = $this->approvedCustomer();
        $scheme = $this->schemeFor($customer); // ₹5,000 × 11
        $this->rateToday(12500);
        $payments = app(PaymentService::class);
        $payment = $payments->initiate($customer, $scheme, (string) Str::uuid());
        $payments->handleCallback($payment, $this->gatewayPays($payment), $customer);

        $this->asAdmin()->put(route('admin.customers.update', $customer), [
            'name' => 'Renamed', 'email' => $customer->email, 'mobile' => $customer->mobile_number,
            'monthly_amount' => '1', 'total_commitment' => '1', 'scheme_number' => 'HACK', 'kyc_status' => 'rejected',
            'amount_paise' => '1', 'gold_grams' => '99',
        ])->assertSessionHasNoErrors();

        $scheme->refresh();
        $this->assertSame('5000.00', $scheme->monthly_amount);
        $this->assertSame('55000.00', $scheme->total_commitment);
        $this->assertNotSame('HACK', $scheme->scheme_number);
        $this->assertSame(KycStatus::Verified, $customer->kycProfile()->value('kyc_status'));
        $this->assertSame(500000, $payment->fresh()->amount_paise);
        $this->assertSame('0.400000', GoldTransaction::query()->sole()->gold_grams);
        $this->assertSame('Renamed', $customer->fresh()->name);
    }

    // Block / unblock -------------------------------------------------------------------------

    public function test_block_stops_login_but_keeps_data_and_unblock_restores_it(): void
    {
        $this->fakeGateway();
        $customer = $this->approvedCustomer(['email' => 'priya@example.com', 'mobile_number' => '9811122233']);
        $scheme = $this->schemeFor($customer);
        $this->rateToday(12500);
        $payments = app(PaymentService::class);
        $payment = $payments->initiate($customer, $scheme, (string) Str::uuid());
        $payments->handleCallback($payment, $this->gatewayPays($payment), $customer);
        $before = [$scheme->fresh()->toArray(), $payment->fresh()->toArray(), GoldTransaction::query()->sole()->toArray(), $customer->kycProfile->fresh()->toArray()];

        $this->asAdmin()->post(route('admin.customers.block', $customer), ['reason' => 'Requested by customer'])
            ->assertRedirect(route('admin.customers.show', $customer))->assertSessionHas('status');
        $this->assertSame(AccountStatus::Blocked, $customer->fresh()->status);

        // Correct OTP, but blocked.
        auth('web')->logout();
        $this->customerLogin('9811122233')
            ->assertRedirect(route('login'))->assertSessionHasErrors(['mobile' => LoginController::BLOCKED_MESSAGE]);
        $this->assertGuest('customer');

        // Data is untouched.
        $this->assertSame($before, [$scheme->fresh()->toArray(), $payment->fresh()->toArray(), GoldTransaction::query()->sole()->toArray(), $customer->kycProfile->fresh()->toArray()]);

        // Unblock → can sign in again.
        $this->asAdmin()->post(route('admin.customers.unblock', $customer))->assertSessionHas('status');
        $this->assertSame(AccountStatus::Active, $customer->fresh()->status);
        auth('web')->logout();
        $this->customerLogin('9811122233')->assertRedirect(route('customer.dashboard'));
        $this->assertAuthenticatedAs($customer->fresh(), 'customer');

        $this->assertSame(
            ['unblocked', 'blocked'],
            $customer->customerAuditLogs()->pluck('action')->map->value->all(),
        );
        $this->assertSame('Requested by customer', $customer->customerAuditLogs()->where('action', 'blocked')->value('note'));
    }

    public function test_blocked_customer_is_signed_out_of_an_existing_session(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer, 'customer')->get(route('customer.dashboard'))->assertOk();

        $customer->forceFill(['status' => AccountStatus::Blocked])->save();

        $this->get(route('customer.dashboard'))->assertRedirect(route('login'))->assertSessionHasErrors(['mobile' => LoginController::BLOCKED_MESSAGE]);
        $this->assertGuest('customer');
        $this->getJson(route('customer.dashboard'))->assertUnauthorized();
    }

    public function test_admin_accounts_cannot_be_managed_or_blocked_here(): void
    {
        $otherAdmin = User::factory()->admin()->create();
        $this->asAdmin();

        foreach ([$this->admin, $otherAdmin] as $admin) {
            $this->get(route('admin.customers.show', $admin->id))->assertNotFound();
            $this->get(route('admin.customers.edit', $admin->id))->assertNotFound();
            $this->put(route('admin.customers.update', $admin->id), ['name' => 'X', 'email' => 'x@example.com', 'mobile' => '9123456789'])->assertNotFound();
            $this->post(route('admin.customers.block', $admin->id))->assertNotFound();
            $this->assertSame(AccountStatus::Active, $admin->fresh()->status);
        }

        // The signed-in admin still works normally.
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_block_and_unblock_are_idempotent_and_post_only(): void
    {
        $customer = User::factory()->create();
        $this->asAdmin();

        $this->post(route('admin.customers.block', $customer));
        $this->post(route('admin.customers.block', $customer));
        $this->assertSame(1, $customer->customerAuditLogs()->count());

        $this->get(route('admin.customers.show', $customer).'/block')->assertMethodNotAllowed();
    }

    // Detail -------------------------------------------------------------------------------------

    public function test_detail_shows_profile_schemes_kyc_payments_and_gold_without_identity_numbers(): void
    {
        $this->fakeGateway();
        $customer = $this->approvedCustomer(['name' => 'Priya Sundar', 'mobile_number' => '9876543210']);
        $customer->kycProfile->update(['pan_number' => 'BKLPS7319M', 'aadhaar_last4' => '4821', 'address_line1' => '12 Temple Street', 'submitted_at' => now(), 'reviewed_at' => now()]);
        $scheme = $this->schemeFor($customer);
        $this->rateToday(12500);
        $payments = app(PaymentService::class);
        $payment = $payments->initiate($customer, $scheme, (string) Str::uuid());
        $payments->handleCallback($payment, $this->gatewayPays($payment), $customer);

        $html = $this->asAdmin()->get(route('admin.customers.show', $customer))->assertOk()
            ->assertSee('Priya Sundar')->assertSee('+91 98765 43210')->assertSee('07 Oct 2026')
            ->assertSee($scheme->scheme_number)->assertSee('Gold Premium')->assertSee('₹5,000')->assertSee('11 months')->assertSee('₹55,000')
            ->assertSee('Approved')->assertSee(route('admin.kyc.show', $customer->kycProfile), false)
            ->assertSee($payment->reference)->assertSee('0.400000 g')
            ->assertSee('Block Customer')
            ->getContent();

        $this->assertMatchesRegularExpression('/data-stat="payment-count".*?data-stat-value>1</s', $html);
        $this->assertMatchesRegularExpression('/data-stat="payment-total".*?data-stat-value>₹5,000</s', $html);
        $this->assertMatchesRegularExpression('/data-customer-gold>0\.400000 g</', $html);
        foreach (['BKLPS7319M', 'XXXXXX319M', '4821', '12 Temple Street', $payment->fresh()->gateway_signature, self::GATEWAY_SECRET] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }
    }

    public function test_rejected_kyc_shows_reason_on_detail(): void
    {
        $customer = User::factory()->create();
        KycProfile::factory()->for($customer)->create(['kyc_status' => KycStatus::Rejected, 'rejection_reason' => 'PAN document is unclear.', 'submitted_at' => now()]);

        $this->asAdmin()->get(route('admin.customers.show', $customer))->assertSee('Rejected')->assertSee('PAN document is unclear.');
    }

    // Wiring / regression -----------------------------------------------------------------------

    public function test_routes_use_admin_middleware_and_csrf(): void
    {
        $this->assertContains(ValidateCsrfToken::class, app(HttpKernel::class)->getMiddlewareGroups()['web']);

        foreach (['index', 'create', 'store', 'show', 'edit', 'update', 'block', 'unblock'] as $action) {
            $middleware = app('router')->getRoutes()->getByName('admin.customers.'.$action)->gatherMiddleware();
            $this->assertContains('admin', $middleware, $action);
            $this->assertContains('web', $middleware, $action);
        }
    }

    public function test_sidebar_and_dashboard_link_to_customers(): void
    {
        User::factory()->count(2)->create();
        User::factory()->create()->forceFill(['status' => AccountStatus::Blocked])->save();

        $html = $this->asAdmin()->get(route('admin.dashboard'))->assertOk()
            ->assertSee('href="'.route('admin.customers.index').'"', false)
            ->assertSee('href="'.route('admin.customers.create').'"', false)
            ->assertSee('View Customers')->assertSee('2 active · 1 blocked')
            ->getContent();

        $this->assertMatchesRegularExpression('/data-stat="customers".*?data-stat-value>3</s', $html);
    }

    public function test_customer_portal_still_works(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer, 'customer');

        foreach (['customer.dashboard', 'customer.schemes.index', 'customer.profile', 'customer.kyc'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_nothing_sensitive_is_logged(): void
    {
        Log::spy();
        $this->asAdmin()->post(route('admin.customers.store'), $this->newCustomer());
        $customer = User::query()->where('email', 'lakshmi@example.com')->sole();
        $this->post(route('admin.customers.block', $customer));

        foreach (['info', 'warning', 'error', 'debug'] as $level) {
            Log::shouldNotHaveReceived($level);
        }
        $this->assertStringNotContainsString(self::PASSWORD, CustomerAuditLog::query()->get()->toJson());
    }
}
