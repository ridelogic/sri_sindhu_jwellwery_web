<?php

namespace Tests\Feature\Admin;

use App\Enums\BonusType;
use App\Enums\ContributionFrequency;
use App\Enums\CustomerPlanStatus;
use App\Enums\OutputType;
use App\Enums\PaymentStatus;
use App\Enums\TenureUnit;
use App\Models\CustomerPlan;
use App\Models\GoldTransaction;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\EnrolmentService;
use App\Services\SchemeBonusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\FakesPaymentGateway;
use Tests\TestCase;

/**
 * Plan configuration: tenure unit (days / weeks / months / years / unlimited),
 * output (money / gold / anyone), amount limits, scheme type, bonus, early exit —
 * and proof that existing plans and customer schemes behave exactly as before.
 */
class PlanConfigurationTest extends TestCase
{
    use FakesPaymentGateway;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-10 11:00:00'); // IST
        $this->fakeGateway();
        $this->admin = User::factory()->admin()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Gold Flexi',
            'scheme_type' => 'saving',
            'contribution_frequency' => 'monthly',
            'tenure_unit' => 'months',
            'maximum_tenure' => '11',
            'minimum_amount' => '500',
            'maximum_amount' => '5000',
            'maximum_amount_unlimited' => '0',
            'output_type' => 'money',
            'bonus_applicable' => '0',
            'bonus_type' => '',
            'bonus_value' => '',
            'early_exit_allowed' => '0',
            'status' => '1',
            'slug' => 'gold-flexi',
            'sort_order' => '1',
            'description' => 'A configurable plan.',
            'is_featured' => '0',
        ];
    }

    private function store(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->admin)->from(route('admin.plans.create'))->post(route('admin.plans.store'), $this->payload($overrides));
    }

    private function storedPlan(array $overrides = []): Plan
    {
        $this->store($overrides)->assertSessionHasNoErrors();

        return Plan::query()->where('slug', $overrides['slug'] ?? 'gold-flexi')->firstOrFail();
    }

    private function enrol(Plan $plan, ?User $customer = null): CustomerPlan
    {
        [$scheme] = app(EnrolmentService::class)->enrol($customer ?? $this->approvedCustomer(), $plan);

        return $scheme->fresh();
    }

    private function pay(CustomerPlan $scheme, ?string $amount = null): TestResponse
    {
        $customer = $scheme->user;
        $response = $this->actingAs($customer, 'customer')->post(
            route('customer.schemes.payment.store', $scheme->scheme_number),
            array_filter(['payment_token' => (string) Str::uuid(), 'amount' => $amount], fn ($v) => $v !== null),
        );

        $payment = Payment::query()->where('customer_plan_id', $scheme->id)->latest('id')->first();
        if ($payment && $payment->status === PaymentStatus::Created) {
            $this->actingAs($customer, 'customer')->post(route('customer.payments.callback', $payment->reference), $this->gatewayPays($payment));
        }

        return $response;
    }

    // Admin form -----------------------------------------------------------------------

    public function test_form_offers_exact_tenure_units_and_outputs_with_months_and_money_defaults(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.plans.create'))->assertOk()->getContent();

        foreach (['days' => 'Days', 'weeks' => 'Weeks', 'months' => 'Months', 'years' => 'Years', 'unlimited' => 'Unlimited'] as $value => $label) {
            $this->assertMatchesRegularExpression('/<option value="'.$value.'"[^>]*>\s*'.$label.'\s*<\/option>/', $html);
        }
        foreach (['money' => 'Money', 'gold' => 'Gold', 'anyone' => 'Anyone'] as $value => $label) {
            $this->assertMatchesRegularExpression('/<option value="'.$value.'"[^>]*>\s*'.$label.'\s*<\/option>/', $html);
        }
        $this->assertMatchesRegularExpression('/<option value="months"\s+selected/', $html);
        $this->assertMatchesRegularExpression('/<option value="money"\s+selected/', $html);
        $this->assertStringContainsString('Plan Tenure Unit', $html);
        $this->assertStringContainsString('Maximum Tenure Allowed', $html);
    }

    // Tenure units ------------------------------------------------------------------------

    public static function tenures(): array
    {
        return [
            'days' => ['days', 90, '2027-01-08', 3],
            'weeks' => ['weeks', 12, '2027-01-02', 3],
            'months' => ['months', 11, '2027-09-10', 11],
            'years' => ['years', 5, '2031-10-10', 60],
        ];
    }

    #[DataProvider('tenures')]
    public function test_tenure_unit_is_stored_as_chosen_and_drives_maturity(string $unit, int $tenure, string $maturity, int $installments): void
    {
        $plan = $this->storedPlan(['tenure_unit' => $unit, 'maximum_tenure' => (string) $tenure]);

        $this->assertSame(TenureUnit::from($unit), $plan->tenure_unit);
        $this->assertSame($tenure, $plan->maximum_tenure);                       // never converted into months
        $this->assertSame($unit === 'months' ? $tenure : null, $plan->duration_months);

        $scheme = $this->enrol($plan);
        $this->assertSame(TenureUnit::from($unit), $scheme->tenure_unit);
        $this->assertSame($tenure, $scheme->maximum_tenure);
        $this->assertSame('2026-10-10', $scheme->start_date->toDateString());
        $this->assertSame($maturity, $scheme->maturity_date->toDateString());    // calendar arithmetic per unit
        $this->assertSame($installments, $scheme->installments_total);
    }

    public function test_unlimited_tenure_has_no_maximum_tenure_and_no_maturity_date(): void
    {
        // A tampered maximum tenure is ignored for an unlimited plan.
        $plan = $this->storedPlan(['tenure_unit' => 'unlimited', 'maximum_tenure' => '50']);

        $this->assertSame(TenureUnit::Unlimited, $plan->tenure_unit);
        $this->assertNull($plan->maximum_tenure);
        $this->assertNull($plan->duration_months);
        $this->assertNull($plan->total_amount);
        $this->assertSame('Unlimited', $plan->tenureLabel());

        $scheme = $this->enrol($plan);
        $this->assertNull($scheme->maturity_date);                                // no fake maturity date
        $this->assertNull($scheme->installments_total);
        $this->assertNull($scheme->total_commitment);

        $this->actingAs($scheme->user, 'customer')->get(route('customer.schemes.show', $scheme->scheme_number))
            ->assertOk()->assertSee('Unlimited')->assertSee('Open-ended');
    }

    public function test_invalid_tenure_is_rejected(): void
    {
        $this->store(['tenure_unit' => 'fortnights'])->assertSessionHasErrors('tenure_unit');
        $this->store(['tenure_unit' => 'days', 'maximum_tenure' => ''])->assertSessionHasErrors(['maximum_tenure' => 'Please enter the maximum tenure.']);
        $this->store(['tenure_unit' => 'weeks', 'maximum_tenure' => '0'])->assertSessionHasErrors(['maximum_tenure' => 'The maximum tenure must be greater than 0.']);
        $this->store(['tenure_unit' => 'years', 'maximum_tenure' => '2.5'])->assertSessionHasErrors('maximum_tenure');
        $this->store(['tenure_unit' => 'years', 'maximum_tenure' => '11'])->assertSessionHasErrors('maximum_tenure'); // > 10 years

        $this->assertSame(0, Plan::query()->count());
    }

    // Output --------------------------------------------------------------------------------

    public function test_each_output_is_stored(): void
    {
        foreach (['money', 'gold', 'anyone'] as $output) {
            $plan = $this->storedPlan(['output_type' => $output, 'slug' => 'plan-'.$output]);
            $this->assertSame(OutputType::from($output), $plan->output_type);
            $this->assertSame(OutputType::from($output), $this->enrol($plan)->output_type);
        }
    }

    public function test_invalid_output_is_rejected(): void
    {
        $this->store(['output_type' => 'silver'])->assertSessionHasErrors(['output_type' => 'Please choose Money, Gold or Anyone.']);
        $this->store(['output_type' => ''])->assertSessionHasErrors('output_type');

        $this->assertSame(0, Plan::query()->count());
    }

    public function test_money_payment_is_recorded_without_any_gold_transaction(): void
    {
        $this->rateToday(12500);
        $scheme = $this->enrol($this->storedPlan(['output_type' => 'money', 'minimum_amount' => '5000', 'maximum_amount' => '5000']));

        $this->pay($scheme)->assertRedirect();

        $payment = Payment::query()->where('customer_plan_id', $scheme->id)->sole();
        $this->assertSame(PaymentStatus::Success, $payment->status);
        $this->assertSame(500000, $payment->amount_paise);
        $this->assertSame(0, GoldTransaction::query()->count());
        $this->assertSame(CustomerPlanStatus::Active, $scheme->fresh()->status);
    }

    public function test_money_payment_does_not_need_todays_gold_rate(): void
    {
        $scheme = $this->enrol($this->storedPlan(['output_type' => 'money']));   // no rate published

        $this->pay($scheme, '1000');

        $this->assertSame(PaymentStatus::Success, Payment::query()->sole()->status);
        $this->assertSame(0, GoldTransaction::query()->count());
    }

    public function test_gold_payment_creates_a_gold_transaction_at_the_historical_rate(): void
    {
        $this->rateToday(12500);
        $scheme = $this->enrol($this->storedPlan(['output_type' => 'gold', 'minimum_amount' => '5000', 'maximum_amount' => '5000']));

        $this->pay($scheme);

        $gold = GoldTransaction::query()->sole();
        $this->assertSame($scheme->id, $gold->customer_plan_id);
        $this->assertSame('0.400000', $gold->gold_grams);                         // ₹5,000 ÷ ₹12,500/g, 6 decimals
        $this->assertSame(1250000, $gold->rate_per_gram_paise);
    }

    public function test_anyone_output_creates_no_gold_or_payout_logic(): void
    {
        $this->rateToday(12500);
        $scheme = $this->enrol($this->storedPlan(['output_type' => 'anyone']));

        $this->pay($scheme, '2500');

        $payment = Payment::query()->sole();
        $this->assertSame(PaymentStatus::Success, $payment->status);
        $this->assertSame(250000, $payment->amount_paise);
        $this->assertSame(0, GoldTransaction::query()->count());
        $this->assertSame(OutputType::Anyone, $scheme->fresh()->output_type);
    }

    // Amount limits --------------------------------------------------------------------------

    public function test_payment_respects_minimum_and_maximum_amount(): void
    {
        $scheme = $this->enrol($this->storedPlan(['minimum_amount' => '500', 'maximum_amount' => '5000']));

        $this->pay($scheme, '400')->assertSessionHasErrors(['payment' => 'Please enter an amount between ₹500 and ₹5,000.']);
        $this->pay($scheme, '5000.01')->assertSessionHasErrors('payment');
        $this->assertSame(0, Payment::query()->count());

        $this->pay($scheme, '2500');
        $this->assertSame(250000, Payment::query()->sole()->amount_paise);
    }

    public function test_unlimited_maximum_amount_is_stored_as_null_and_has_no_upper_limit(): void
    {
        $plan = $this->storedPlan(['maximum_amount_unlimited' => '1', 'maximum_amount' => '999']);
        $this->assertNull($plan->maximum_amount);
        $this->assertSame('From ₹500', $plan->amountRangeLabel());

        $scheme = $this->enrol($plan);
        $this->assertNull($scheme->maximum_amount);

        $this->pay($scheme, '250000');
        $this->assertSame(25000000, Payment::query()->sole()->amount_paise);
    }

    public function test_maximum_amount_must_not_be_below_minimum(): void
    {
        $this->store(['minimum_amount' => '5000', 'maximum_amount' => '4999.99'])
            ->assertSessionHasErrors(['maximum_amount' => 'The maximum amount must be greater than or equal to the minimum amount.']);
        $this->store(['maximum_amount' => ''])->assertSessionHasErrors('maximum_amount');
        $this->store(['minimum_amount' => '0'])->assertSessionHasErrors('minimum_amount');
    }

    public function test_scheme_type_bonus_and_early_exit_are_stored_and_validated(): void
    {
        $plan = $this->storedPlan(['scheme_type' => 'exchange', 'bonus_applicable' => '1', 'bonus_type' => 'percentage', 'bonus_value' => '5', 'early_exit_allowed' => '1']);
        $this->assertSame('exchange', $plan->scheme_type->value);
        $this->assertTrue($plan->bonus_applicable);
        $this->assertTrue($plan->early_exit_allowed);

        $this->store(['slug' => 'x1', 'scheme_type' => 'loan'])->assertSessionHasErrors('scheme_type');
        $this->store(['slug' => 'x2', 'bonus_applicable' => 'maybe'])->assertSessionHasErrors('bonus_applicable');
        $this->store(['slug' => 'x3', 'early_exit_allowed' => ''])->assertSessionHasErrors('early_exit_allowed');
    }

    // Snapshots ---------------------------------------------------------------------------------

    public function test_plan_edit_does_not_alter_an_existing_customer_snapshot(): void
    {
        $plan = $this->storedPlan(['tenure_unit' => 'days', 'maximum_tenure' => '90', 'output_type' => 'money']);
        $scheme = $this->enrol($plan);
        $before = $scheme->only(['tenure_unit', 'maximum_tenure', 'minimum_amount', 'maximum_amount', 'output_type', 'installments_total', 'maturity_date', 'total_commitment', 'monthly_amount']);

        $this->actingAs($this->admin)->put(route('admin.plans.update', $plan), $this->payload([
            'tenure_unit' => 'years', 'maximum_tenure' => '5', 'output_type' => 'gold', 'minimum_amount' => '9000', 'maximum_amount' => '9000',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(TenureUnit::Years, $plan->fresh()->tenure_unit);
        $this->assertEquals($before, $scheme->fresh()->only(array_keys($before)));
    }

    public function test_customer_cannot_change_plan_configuration(): void
    {
        $plan = $this->storedPlan();
        $customer = $this->approvedCustomer();

        $this->actingAs($customer)->put(route('admin.plans.update', $plan), $this->payload(['output_type' => 'gold']))->assertForbidden();
        $this->actingAs($customer)->post(route('admin.plans.store'), $this->payload(['slug' => 'sneaky']))->assertForbidden();

        $this->assertSame(OutputType::Money, $plan->fresh()->output_type);
        $this->assertDatabaseMissing('plans', ['slug' => 'sneaky']);
    }

    // Contribution frequency ------------------------------------------------------------------------

    public static function frequencies(): array
    {
        return [
            'daily for 90 days' => ['daily', 'days', 90, 90],
            'weekly for 52 weeks' => ['weekly', 'weeks', 52, 52],
            'monthly for 12 months' => ['monthly', 'months', 12, 12],
            'yearly for 5 years' => ['yearly', 'years', 5, 5],
            'monthly for 5 years' => ['monthly', 'years', 5, 60],
            'weekly for 90 days' => ['weekly', 'days', 90, 13],
            'unlimited for 12 months' => ['unlimited', 'months', 12, null],
        ];
    }

    #[DataProvider('frequencies')]
    public function test_contribution_frequency_is_stored_separately_from_tenure(string $frequency, string $unit, int $tenure, ?int $installments): void
    {
        $plan = $this->storedPlan(['contribution_frequency' => $frequency, 'tenure_unit' => $unit, 'maximum_tenure' => (string) $tenure]);
        $this->assertSame(ContributionFrequency::from($frequency), $plan->contribution_frequency);
        $this->assertSame(TenureUnit::from($unit), $plan->tenure_unit);

        $scheme = $this->enrol($plan);
        $this->assertSame(ContributionFrequency::from($frequency), $scheme->contribution_frequency);
        $this->assertSame($installments, $scheme->installments_total);
        $this->assertNotNull($scheme->maturity_date);
    }

    public function test_invalid_frequency_and_frequency_longer_than_tenure_are_rejected(): void
    {
        $this->store(['contribution_frequency' => 'fortnightly'])->assertSessionHasErrors(['contribution_frequency' => 'Please choose Daily, Weekly, Monthly, Yearly or Unlimited.']);
        $this->store(['contribution_frequency' => 'yearly', 'tenure_unit' => 'days', 'maximum_tenure' => '90'])
            ->assertSessionHasErrors(['contribution_frequency' => 'A yearly contribution does not fit within a tenure of 90 days.']);
        $this->store(['contribution_frequency' => 'monthly', 'tenure_unit' => 'weeks', 'maximum_tenure' => '2'])->assertSessionHasErrors('contribution_frequency');

        $this->assertSame(0, Plan::query()->count());
    }

    public function test_daily_frequency_allows_one_installment_per_day(): void
    {
        $scheme = $this->enrol($this->storedPlan(['contribution_frequency' => 'daily', 'tenure_unit' => 'days', 'maximum_tenure' => '30', 'minimum_amount' => '100', 'maximum_amount' => '100']));
        $this->pay($scheme);
        $this->assertSame(1, Payment::query()->successful()->count());

        $this->actingAs($scheme->user, 'customer')->get(route('customer.schemes.payment', $scheme->scheme_number))
            ->assertSee('data-payment-blocked="not_due"', false)->assertSee("This day's installment has been paid. Installment 2 is due from 11 Oct 2026.");
        $this->pay($scheme)->assertSessionHasErrors('payment');
        $this->assertSame(1, Payment::query()->count());

        Carbon::setTestNow('2026-10-11 09:00:00');
        $this->pay($scheme);
        $this->assertSame(2, Payment::query()->successful()->count());
    }

    public function test_weekly_frequency_follows_seven_day_periods(): void
    {
        $scheme = $this->enrol($this->storedPlan(['contribution_frequency' => 'weekly', 'tenure_unit' => 'weeks', 'maximum_tenure' => '10']));
        $this->pay($scheme, '500');

        Carbon::setTestNow('2026-10-16 18:00:00'); // day 7 of the first week
        $this->pay($scheme, '500')->assertSessionHasErrors('payment');

        Carbon::setTestNow('2026-10-17 09:00:00'); // second week begins
        $this->pay($scheme, '500');
        $this->assertSame(2, Payment::query()->successful()->count());
    }

    public function test_yearly_frequency_follows_calendar_years(): void
    {
        $scheme = $this->enrol($this->storedPlan(['contribution_frequency' => 'yearly', 'tenure_unit' => 'years', 'maximum_tenure' => '3']));
        $this->pay($scheme, '5000');

        $this->actingAs($scheme->user, 'customer')->get(route('customer.schemes.payment', $scheme->scheme_number))
            ->assertSee("This year's installment has been paid. Installment 2 is due from 01 Jan 2027.");

        Carbon::setTestNow('2027-01-01 10:00:00');
        $this->pay($scheme, '5000');
        $this->assertSame(2, Payment::query()->successful()->count());
    }

    public function test_unlimited_frequency_accepts_contributions_any_time_until_maturity(): void
    {
        $scheme = $this->enrol($this->storedPlan(['contribution_frequency' => 'unlimited', 'tenure_unit' => 'months', 'maximum_tenure' => '1']));
        $this->pay($scheme, '500');
        $this->pay($scheme, '700');
        $this->assertSame(2, Payment::query()->successful()->count());
        $this->assertSame(CustomerPlanStatus::Active, $scheme->fresh()->status); // never auto-completes

        Carbon::setTestNow('2026-11-11 10:00:00'); // after the 10 Nov 2026 maturity
        $this->pay($scheme, '500')->assertSessionHasErrors(['payment' => 'This scheme reached its maturity date on 10 Nov 2026 and no longer accepts contributions.']);
        $this->assertSame(2, Payment::query()->count());
    }

    // Bonus --------------------------------------------------------------------------------------------

    public function test_bonus_disabled_ignores_bonus_type_and_value(): void
    {
        $plan = $this->storedPlan(['bonus_applicable' => '0', 'bonus_type' => 'percentage', 'bonus_value' => '50']);

        $this->assertFalse($plan->bonus_applicable);
        $this->assertNull($plan->bonus_type);
        $this->assertNull($plan->bonus_value);
        $this->assertNull(app(SchemeBonusService::class)->projected($this->enrol($plan), 5500000));
    }

    public function test_each_bonus_type_is_stored_and_projected_exactly(): void
    {
        $cases = [
            'percentage' => ['5.25', '5.25%', ['amount_paise' => 288750, 'gold_micrograms' => null]],   // 5.25% of ₹55,000
            'fixed_amount' => ['1000', '₹1,000', ['amount_paise' => 100000, 'gold_micrograms' => null]],
            'gold' => ['1.5', '1.5 g', ['amount_paise' => null, 'gold_micrograms' => 1500000]],
        ];

        foreach ($cases as $type => [$value, $label, $expected]) {
            $plan = $this->storedPlan(['slug' => 'bonus-'.str_replace('_', '-', $type), 'bonus_applicable' => '1', 'bonus_type' => $type, 'bonus_value' => $value]);
            $this->assertSame(BonusType::from($type), $plan->bonus_type);
            $this->assertSame($label, $plan->bonus_type->format((string) $plan->bonus_value));

            $scheme = $this->enrol($plan);
            $this->assertSame(BonusType::from($type), $scheme->bonus_type); // snapshotted
            $projection = app(SchemeBonusService::class)->projected($scheme, 5500000);
            $this->assertSame($expected, ['amount_paise' => $projection['amount_paise'], 'gold_micrograms' => $projection['gold_micrograms']]);
        }
    }

    public function test_bonus_validation(): void
    {
        $this->store(['bonus_applicable' => '1', 'bonus_type' => '', 'bonus_value' => '5'])->assertSessionHasErrors(['bonus_type' => 'Please choose the bonus type.']);
        $this->store(['bonus_applicable' => '1', 'bonus_type' => 'cashback', 'bonus_value' => '5'])->assertSessionHasErrors('bonus_type');
        $this->store(['bonus_applicable' => '1', 'bonus_type' => 'percentage', 'bonus_value' => ''])->assertSessionHasErrors(['bonus_value' => 'Please enter the bonus value.']);
        $this->store(['bonus_applicable' => '1', 'bonus_type' => 'percentage', 'bonus_value' => '0'])->assertSessionHasErrors('bonus_value');
        $this->store(['bonus_applicable' => '1', 'bonus_type' => 'percentage', 'bonus_value' => '100.01'])->assertSessionHasErrors(['bonus_value' => 'A percentage bonus cannot be more than 100%.']);
        $this->store(['bonus_applicable' => '1', 'bonus_type' => 'fixed_amount', 'bonus_value' => '10.555'])->assertSessionHasErrors('bonus_value');
        $this->store(['bonus_applicable' => '1', 'bonus_type' => 'gold', 'bonus_value' => '1.1234567'])->assertSessionHasErrors('bonus_value');
        $this->store(['bonus_applicable' => '1', 'bonus_type' => 'gold', 'bonus_value' => '1001'])->assertSessionHasErrors('bonus_value');

        $this->assertSame(0, Plan::query()->count());
        $this->store(['bonus_applicable' => '1', 'bonus_type' => 'gold', 'bonus_value' => '1.123456'])->assertSessionHasNoErrors(); // 6 decimals allowed for gold
    }

    public function test_bonus_is_never_credited_on_an_installment_payment(): void
    {
        $this->rateToday(12500);
        $scheme = $this->enrol($this->storedPlan(['output_type' => 'gold', 'minimum_amount' => '5000', 'maximum_amount' => '5000', 'bonus_applicable' => '1', 'bonus_type' => 'gold', 'bonus_value' => '2']));

        $this->pay($scheme);

        $this->assertSame(1, GoldTransaction::query()->count());
        $this->assertSame('0.400000', GoldTransaction::query()->sole()->gold_grams); // the payment's gold only — no bonus
    }

    // Existing plans & schemes: no behaviour change -------------------------------------------------

    /**
     * Re-runs the real migration over rows written the way they existed before
     * this feature (only the original columns), then checks every value.
     */
    public function test_migration_preserves_existing_plans_and_customer_schemes_exactly(): void
    {
        $migration = require database_path('migrations/2026_10_13_000100_add_scheme_configuration_to_plans.php');
        $migration->down();

        $customer = $this->approvedCustomer();
        $planId = DB::table('plans')->insertGetId([
            'name' => 'Gold Premium', 'slug' => 'gold-premium', 'description' => 'Existing plan.', 'monthly_amount' => '5000.00',
            'duration_months' => 11, 'total_amount' => '55000.00', 'is_featured' => false, 'status' => true, 'sort_order' => 3,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $schemeId = DB::table('customer_plans')->insertGetId([
            'user_id' => $customer->id, 'plan_id' => $planId, 'scheme_number' => 'SSJ-2609-OLD001', 'start_date' => '2026-09-15',
            'maturity_date' => '2027-08-15', 'monthly_amount' => '5000.00', 'duration_months' => 11, 'total_commitment' => '55000.00',
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $plansBefore = DB::table('plans')->get(['id', 'monthly_amount', 'duration_months', 'total_amount', 'status'])->map(fn ($r) => (array) $r)->all();
        $schemesBefore = DB::table('customer_plans')->get(['id', 'monthly_amount', 'duration_months', 'total_commitment', 'status', 'start_date', 'maturity_date'])->map(fn ($r) => (array) $r)->all();

        $migration->up();

        // Same rows, same financial values, same statuses.
        $this->assertEquals($plansBefore, DB::table('plans')->get(['id', 'monthly_amount', 'duration_months', 'total_amount', 'status'])->map(fn ($r) => (array) $r)->all());
        $this->assertEquals($schemesBefore, DB::table('customer_plans')->get(['id', 'monthly_amount', 'duration_months', 'total_commitment', 'status', 'start_date', 'maturity_date'])->map(fn ($r) => (array) $r)->all());

        // Back-filled configuration describes the existing behaviour exactly.
        $plan = Plan::query()->findOrFail($planId);
        $scheme = CustomerPlan::query()->findOrFail($schemeId);
        foreach ([$plan, $scheme] as $model) {
            $this->assertSame(ContributionFrequency::Monthly, $model->contribution_frequency);
            $this->assertNull($model->bonus_type);
            $this->assertNull($model->bonus_value);
            $this->assertSame('Monthly Contribution', $model->contributionLabel());
            $this->assertSame(TenureUnit::Months, $model->tenure_unit);
            $this->assertSame(11, $model->maximum_tenure);
            $this->assertSame('5000.00', $model->minimum_amount);
            $this->assertSame('5000.00', $model->maximum_amount);
            $this->assertSame(OutputType::Gold, $model->output_type);
            $this->assertFalse($model->bonus_applicable);
            $this->assertFalse($model->early_exit_allowed);
            $this->assertTrue($model->isFixedAmount());
            $this->assertSame('11 months', $model->tenureLabel());
        }
        $this->assertSame(11, $scheme->installments_total);

        // Behaviour: a ₹5,000 installment, gold credited at the historical rate, "1 of 11".
        $this->rateToday(12500);
        $this->actingAs($customer, 'customer')->get(route('customer.schemes.payment', $scheme->scheme_number))
            ->assertOk()->assertSee('Pay ₹5,000 Securely')->assertSee('Installment 1 of 11')->assertDontSee('name="amount"', false);
        $this->pay($scheme, '999'); // a tampered amount is ignored for a fixed scheme

        $payment = Payment::query()->sole();
        $this->assertSame(500000, $payment->amount_paise);
        $this->assertSame('0.400000', GoldTransaction::query()->sole()->gold_grams);

        $scheme = $scheme->fresh();
        $this->assertSame('55000.00', $scheme->total_commitment);
        $this->assertSame('2027-08-15', $scheme->maturity_date->toDateString());
        $this->assertSame(CustomerPlanStatus::Active, $scheme->status);
        $this->assertSame('55000.00', $plan->fresh()->total_amount);
    }

    public function test_existing_style_plans_keep_fixed_monthly_behaviour(): void
    {
        // Plans and schemes written the original way (factories / original admin payload).
        $plan = Plan::factory()->create(['monthly_amount' => 5000, 'duration_months' => 11]);
        $this->assertSame('55000.00', $plan->total_amount);
        $this->assertSame(OutputType::Gold, $plan->output_type);
        $this->assertSame('₹5,000', $plan->amountRangeLabel());

        $plan->update(['monthly_amount' => 6000]);                                // original-style edit
        $this->assertSame('6000.00', $plan->fresh()->minimum_amount);
        $this->assertSame('6000.00', $plan->fresh()->maximum_amount);
        $this->assertSame('66000.00', $plan->fresh()->total_amount);

        $scheme = $this->enrol($plan->fresh());
        $this->assertSame('6000.00', $scheme->monthly_amount);
        $this->assertSame(11, $scheme->duration_months);
        $this->assertSame('66000.00', $scheme->total_commitment);
        $this->assertSame('2027-09-10', $scheme->maturity_date->toDateString());
        $this->assertTrue($scheme->creditsGold());
    }
}
