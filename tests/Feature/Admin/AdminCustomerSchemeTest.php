<?php

namespace Tests\Feature\Admin;

use App\Enums\CustomerAuditAction;
use App\Enums\CustomerPlanStatus;
use App\Enums\KycStatus;
use App\Enums\PaymentStatus;
use App\Models\CustomerAuditLog;
use App\Models\CustomerPlan;
use App\Models\GoldTransaction;
use App\Models\KycProfile;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\EnrolmentService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\FakesPaymentGateway;
use Tests\TestCase;

class AdminCustomerSchemeTest extends TestCase
{
    use FakesPaymentGateway;
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-07 11:00:00');
        $this->admin = User::factory()->admin()->create();
        $this->customer = User::factory()->create(['name' => 'Priya Sundar', 'email' => 'priya@example.com', 'mobile_number' => '9876543210']);
        $this->plan = Plan::factory()->create([
            'name' => 'Gold Premium', 'monthly_amount' => 5000, 'duration_months' => 11,
            'benefits' => [['title' => 'Zero making charges', 'text' => 'On redemption']], 'terms' => 'Pay by the 10th of each month.',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function enrol(?Plan $plan = null, array $extra = [], ?User $customer = null): TestResponse
    {
        return $this->actingAs($this->admin, 'web')->post(
            route('admin.customers.schemes.store', $customer ?? $this->customer),
            $extra + ['plan_id' => ($plan ?? $this->plan)->id, 'enrolment_token' => (string) Str::uuid()],
        );
    }

    // Access -------------------------------------------------------------------------------

    public function test_admin_can_open_add_scheme_page_with_active_plans_only(): void
    {
        Plan::factory()->inactive()->create(['name' => 'Retired Plan']);

        $this->actingAs($this->admin, 'web')->get(route('admin.customers.schemes.create', $this->customer))->assertOk()
            ->assertSee('Select Gold Saving Plan')
            ->assertSee('Priya Sundar')->assertSee('priya@example.com')->assertSee('+91 98765 43210')
            ->assertSee('Gold Premium')->assertSee('₹5,000')->assertSee('11 months')->assertSee('₹55,000')
            ->assertSee('Zero making charges')->assertSee('Pay by the 10th of each month.')
            ->assertSee('name="enrolment_token"', false)
            ->assertDontSee('Retired Plan')
            ->assertDontSee('name="monthly_amount"', false);

        $this->get(route('admin.customers.show', $this->customer))->assertSee(route('admin.customers.schemes.create', $this->customer), false)->assertSee('Add Scheme');
    }

    public function test_guests_customers_and_non_admins_cannot_use_scheme_management(): void
    {
        $scheme = CustomerPlan::factory()->for($this->customer)->for($this->plan)->create();
        $urls = [route('admin.customers.schemes.create', $this->customer), route('admin.customers.schemes.show', [$this->customer, $scheme->id])];

        foreach ($urls as $url) {
            $this->get($url)->assertRedirect(route('admin.login'));
        }
        $this->post(route('admin.customers.schemes.store', $this->customer), ['plan_id' => $this->plan->id, 'enrolment_token' => (string) Str::uuid()])->assertRedirect(route('admin.login'));

        $this->actingAs($this->customer, 'customer');
        foreach ($urls as $url) {
            $this->get($url)->assertRedirect(route('admin.login'));
        }
        $this->post(route('admin.customers.schemes.store', $this->customer), ['plan_id' => $this->plan->id, 'enrolment_token' => (string) Str::uuid()])->assertRedirect(route('admin.login'));

        $this->actingAs($this->customer, 'web')->get($urls[0])->assertForbidden();

        $this->assertSame(1, CustomerPlan::query()->count());
    }

    // Enrolment ------------------------------------------------------------------------------

    public function test_admin_can_enrol_customer_with_server_side_snapshot(): void
    {
        $response = $this->enrol(null, [
            // Forged financial values — all ignored.
            'monthly_amount' => '1', 'duration_months' => '99', 'total_commitment' => '5', 'plan_name' => 'Hacked',
            'scheme_number' => 'SSJ-HACK-000001', 'status' => 'completed', 'user_id' => $this->admin->id,
        ]);

        $scheme = CustomerPlan::query()->sole();
        $response->assertRedirect(route('admin.customers.schemes.show', [$this->customer, $scheme->id]))->assertSessionHas('status');

        $this->assertSame($this->customer->id, $scheme->user_id);
        $this->assertTrue($scheme->plan->is($this->plan));
        $this->assertSame('5000.00', $scheme->monthly_amount);
        $this->assertSame(11, $scheme->duration_months);
        $this->assertSame('55000.00', $scheme->total_commitment);
        $this->assertSame(CustomerPlanStatus::Pending, $scheme->status);
        $this->assertMatchesRegularExpression('/^SSJ-\d{4}-[A-Z0-9]{6}$/', $scheme->scheme_number);
        $this->assertNotSame('SSJ-HACK-000001', $scheme->scheme_number);
        $this->assertSame('2026-10-07', $scheme->start_date->toDateString());

        // Same record type as self-enrolment: nothing else is created.
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, GoldTransaction::query()->count());
        $this->assertNull($this->customer->kycProfile);
    }

    public function test_enrolment_does_not_require_kyc_but_payment_still_does(): void
    {
        $this->fakeGateway();
        $this->rateToday(12500);
        $this->enrol()->assertSessionHasNoErrors();

        $scheme = CustomerPlan::query()->sole();
        $eligibility = app(PaymentService::class)->eligibility($this->customer, $scheme);

        $this->assertFalse($eligibility['allowed']);
        $this->assertSame('kyc', $eligibility['reason']);
    }

    public function test_multiple_schemes_and_existing_schemes_unchanged(): void
    {
        $existing = CustomerPlan::factory()->for($this->customer)->for(Plan::factory()->create(['monthly_amount' => 1000]))->status(CustomerPlanStatus::Completed)->create(['scheme_number' => 'SSJ-2601-OLD001']);
        $snapshot = $existing->fresh()->toArray();
        $other = Plan::factory()->create(['name' => 'Gold Plus', 'monthly_amount' => 2000]);

        $this->enrol($this->plan)->assertSessionHasNoErrors();
        $this->enrol($other)->assertSessionHasNoErrors();

        $this->assertSame(3, $this->customer->customerPlans()->count());
        $this->assertCount(3, $this->customer->customerPlans()->pluck('scheme_number')->unique());
        $this->assertSame($snapshot, $existing->fresh()->toArray()); // completed scheme stays historical
    }

    public function test_existing_duplicate_rules_are_preserved(): void
    {
        $token = (string) Str::uuid();

        // Double submission of the same form.
        $this->enrol(null, ['enrolment_token' => $token]);
        $this->enrol(null, ['enrolment_token' => $token])->assertSessionHas('status', fn ($s) => str_contains($s, 'already received'));
        // Rapid repeat with a new token (existing 30-second rule).
        $this->enrol();
        $this->assertSame(1, CustomerPlan::query()->count());

        // Joining the same plan again later is allowed by the existing rules.
        Carbon::setTestNow(now()->addSeconds(EnrolmentService::DUPLICATE_WINDOW_SECONDS + 5));
        $this->enrol();
        $this->assertSame(2, CustomerPlan::query()->count());
    }

    public function test_inactive_or_unknown_plans_are_rejected(): void
    {
        $inactive = Plan::factory()->inactive()->create();

        $this->from(route('admin.customers.schemes.create', $this->customer));
        $this->enrol($inactive)->assertSessionHasErrors(['plan_id' => 'Please choose one of the active plans.']);
        $this->actingAs($this->admin, 'web')->post(route('admin.customers.schemes.store', $this->customer), ['plan_id' => 999999, 'enrolment_token' => (string) Str::uuid()])->assertSessionHasErrors('plan_id');
        $this->post(route('admin.customers.schemes.store', $this->customer), ['enrolment_token' => (string) Str::uuid()])->assertSessionHasErrors('plan_id');
        $this->post(route('admin.customers.schemes.store', $this->customer), ['plan_id' => $this->plan->id])->assertSessionHasErrors('enrolment_token');

        $this->assertSame(0, CustomerPlan::query()->count());
    }

    public function test_plan_deactivated_after_validation_is_still_rejected_by_the_service(): void
    {
        $this->plan->update(['status' => false]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Services\CustomerManagementService::class)->enrol($this->admin, $this->customer, $this->plan, (string) Str::uuid());
    }

    public function test_admin_accounts_cannot_be_enrolled(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.customers.schemes.create', $this->admin->id))->assertNotFound();
        $this->enrol(null, [], $this->admin)->assertNotFound();
        $this->assertSame(0, CustomerPlan::query()->count());
    }

    // Snapshot (mandatory) ----------------------------------------------------------------------

    public function test_plan_price_change_never_alters_an_existing_scheme(): void
    {
        $this->enrol()->assertSessionHasNoErrors(); // ₹5,000 × 11 = ₹55,000
        $scheme = CustomerPlan::query()->sole();

        $this->actingAs($this->admin, 'web')->put(route('admin.plans.update', $this->plan), [
            'name' => 'Gold Premium', 'slug' => $this->plan->slug, 'description' => 'Repriced plan.',
            'monthly_amount' => '6000', 'duration_months' => '11', 'status' => '1', 'sort_order' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame('66000.00', $this->plan->fresh()->total_amount);

        $scheme->refresh();
        $this->assertSame('5000.00', $scheme->monthly_amount);
        $this->assertSame(11, $scheme->duration_months);
        $this->assertSame('55000.00', $scheme->total_commitment);

        $this->get(route('admin.customers.schemes.show', [$this->customer, $scheme->id]))->assertOk()
            ->assertSee('₹5,000')->assertSee('₹55,000')->assertDontSee('₹66,000')->assertDontSee('₹6,000');

        // A NEW enrolment uses the new price.
        Carbon::setTestNow(now()->addMinutes(5));
        $this->enrol()->assertSessionHasNoErrors();
        $this->assertSame('6000.00', CustomerPlan::query()->latest('id')->first()->monthly_amount);
    }

    // Security ------------------------------------------------------------------------------------

    public function test_scheme_must_belong_to_the_customer_in_the_url(): void
    {
        $other = User::factory()->create(['name' => 'Other Customer']);
        $theirs = CustomerPlan::factory()->for($other)->for($this->plan)->create();
        $mine = CustomerPlan::factory()->for($this->customer)->for($this->plan)->create();
        $this->actingAs($this->admin, 'web');

        $this->get(route('admin.customers.schemes.show', [$this->customer, $theirs->id]))->assertNotFound();
        $this->get(route('admin.customers.schemes.show', [$this->customer, 999999]))->assertNotFound();
        $this->get(route('admin.customers.schemes.show', [$this->admin->id, $mine->id]))->assertNotFound();
        $this->get(route('admin.customers.schemes.show', [$other, $theirs->id]))->assertOk()->assertSee('Other Customer');
        $this->get(route('admin.customers.schemes.show', [$this->customer, $mine->id]))->assertOk();
    }

    public function test_module_cannot_change_financial_snapshot(): void
    {
        $scheme = CustomerPlan::factory()->for($this->customer)->for($this->plan)->create();
        $before = $scheme->fresh()->toArray();
        $this->actingAs($this->admin, 'web');

        // There is no edit/update/delete route for schemes in this module.
        foreach (['put', 'patch', 'delete'] as $method) {
            $this->{$method}(route('admin.customers.schemes.show', [$this->customer, $scheme->id]), ['monthly_amount' => '1'])->assertMethodNotAllowed();
        }
        $this->assertFalse(app('router')->getRoutes()->hasNamedRoute('admin.customers.schemes.update'));

        $this->assertSame($before, $scheme->fresh()->toArray());
    }

    // Detail -------------------------------------------------------------------------------------------

    public function test_scheme_detail_shows_values_payments_and_gold_without_secrets(): void
    {
        $this->fakeGateway();
        KycProfile::factory()->for($this->customer)->create(['kyc_status' => KycStatus::Verified, 'pan_number' => 'BKLPS7319M']);
        $this->enrol()->assertSessionHasNoErrors();
        $scheme = CustomerPlan::query()->sole();

        $this->rateToday(12500);
        $payments = app(PaymentService::class);
        $paid = $payments->initiate($this->customer, $scheme, (string) Str::uuid());
        $payments->handleCallback($paid, $this->gatewayPays($paid), $this->customer);
        $paid->refresh();

        Carbon::setTestNow('2026-11-04 10:00:00');
        $this->rateToday(10000);
        $failed = $payments->initiate($this->customer, $scheme->fresh(), (string) Str::uuid());
        $payments->handleCallback($failed, $this->gatewayPays($failed, 'failed'), $this->customer);

        $html = $this->actingAs($this->admin, 'web')->get(route('admin.customers.schemes.show', [$this->customer, $scheme->id]))->assertOk()
            ->assertSee($scheme->scheme_number)->assertSee('Priya Sundar')->assertSee('Gold Premium')->assertSee('Active')
            ->assertSee('₹50,000 remaining')
            ->assertSee($paid->reference)->assertSee($failed->reference)
            ->assertSee('Successful')->assertSee('Failed')
            ->assertSee('₹12,500/g')->assertSee('0.400000 g')
            ->assertSee('Enrolled in a scheme by admin')
            ->getContent();

        $stat = fn ($key) => preg_match('/data-stat="'.$key.'".*?data-stat-value>(.*?)</s', $html, $m) ? trim($m[1]) : null;
        $this->assertSame('₹5,000', $stat('monthly'));
        $this->assertSame('₹55,000', $stat('commitment'));
        $this->assertSame('₹5,000', $stat('paid'));
        $this->assertSame('0.400000 g', $stat('gold'));
        $this->assertSame(1, substr_count($html, 'data-gold-row'));

        foreach ([$paid->gateway_signature, self::GATEWAY_SECRET, 'BKLPS7319M'] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }
        $this->assertSame(PaymentStatus::Failed, $failed->fresh()->status);
    }

    public function test_customer_list_counts_only_open_schemes(): void
    {
        CustomerPlan::factory()->for($this->customer)->for($this->plan)->status(CustomerPlanStatus::Active)->create();
        CustomerPlan::factory()->for($this->customer)->for($this->plan)->pending()->create();
        CustomerPlan::factory()->for($this->customer)->for($this->plan)->status(CustomerPlanStatus::Completed)->create();
        CustomerPlan::factory()->for($this->customer)->for($this->plan)->status(CustomerPlanStatus::Cancelled)->create();

        $html = $this->actingAs($this->admin, 'web')->get(route('admin.customers.index'))->getContent();

        $this->assertMatchesRegularExpression('/data-customer-row="'.$this->customer->id.'".*?data-open-schemes>2</s', $html);
    }

    // Audit -------------------------------------------------------------------------------------------

    public function test_enrolment_is_audited_without_sensitive_values(): void
    {
        Log::spy();
        $this->enrol()->assertSessionHasNoErrors();
        $scheme = CustomerPlan::query()->sole();

        $log = CustomerAuditLog::query()->sole();
        $this->assertSame(CustomerAuditAction::SchemeEnrolled, $log->action);
        $this->assertSame($this->admin->id, $log->actor_id);
        $this->assertSame($this->customer->id, $log->user_id);
        $this->assertStringContainsString($scheme->scheme_number, $log->note);
        $this->assertStringContainsString('plan #'.$this->plan->id, $log->note);
        $this->assertStringContainsString('scheme #'.$scheme->id, $log->note);

        // A repeated submission is not audited twice.
        $this->enrol(null, ['enrolment_token' => (string) Str::uuid()]);
        $this->assertSame(1, CustomerAuditLog::query()->count());

        foreach (['info', 'warning', 'error', 'debug'] as $level) {
            Log::shouldNotHaveReceived($level);
        }
    }
}
