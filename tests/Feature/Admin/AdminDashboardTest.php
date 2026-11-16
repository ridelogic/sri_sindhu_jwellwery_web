<?php

namespace Tests\Feature\Admin;

use App\Enums\CustomerPlanStatus;
use App\Enums\GoldPurity;
use App\Models\CustomerPlan;
use App\Models\GoldRate;
use App\Models\KycProfile;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create(['name' => 'Dashboard Admin']);
    }

    private function dashboard(): TestResponse
    {
        return $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
    }

    /**
     * Value shown on a summary card.
     */
    private function stat(TestResponse $response, string $key): string
    {
        preg_match('/data-stat="'.preg_quote($key, '/').'".*?data-stat-value>(.*?)<\/p>/s', $response->getContent(), $match);

        return trim($match[1] ?? '');
    }

    public function test_empty_database_shows_zero_not_fake_statistics(): void
    {
        $response = $this->dashboard();

        $this->assertSame('0', $this->stat($response, 'customers'));
        $this->assertSame('0', $this->stat($response, 'active-schemes'));
        $this->assertSame('0', $this->stat($response, 'pending-kyc'));
        $this->assertSame('0', $this->stat($response, 'active-plans'));
        $this->assertSame('Not recorded', $this->stat($response, 'rate-22k'));
        $this->assertSame('Not recorded', $this->stat($response, 'rate-24k'));
        $response->assertSee('No customers yet.')
            ->assertSee('No scheme enrolments yet.')
            ->assertSee('No rates recorded yet')
            ->assertDontSee('₹7,000'); // the public website's demo fallback must never appear here
    }

    public function test_customer_count_excludes_admins(): void
    {
        User::factory()->count(4)->create();
        User::factory()->admin()->create();

        $this->assertSame('4', $this->stat($this->dashboard(), 'customers'));
    }

    public function test_active_scheme_count_only_counts_active_enrolments(): void
    {
        CustomerPlan::factory()->count(3)->status(CustomerPlanStatus::Active)->create();
        CustomerPlan::factory()->status(CustomerPlanStatus::Pending)->create();
        CustomerPlan::factory()->status(CustomerPlanStatus::Completed)->create();
        CustomerPlan::factory()->status(CustomerPlanStatus::Cancelled)->create();

        $this->assertSame('3', $this->stat($this->dashboard(), 'active-schemes'));
    }

    public function test_pending_kyc_counts_only_submitted_profiles_awaiting_review(): void
    {
        // Since Stage 5, "pending" means not yet submitted (draft) — nothing to review.
        KycProfile::factory()->count(2)->create();            // not submitted — excluded
        KycProfile::factory()->submitted()->count(2)->create(); // pending review
        KycProfile::factory()->verified()->create();          // excluded
        KycProfile::factory()->create(['kyc_status' => 'rejected']); // excluded

        $response = $this->dashboard();
        $this->assertSame('2', $this->stat($response, 'pending-kyc'));
        $response->assertSee(route('admin.kyc.index', ['status' => 'pending_review']), false)->assertSee('Manage KYC');
    }

    public function test_active_plan_count(): void
    {
        Plan::factory()->count(2)->create();
        Plan::factory()->inactive()->create();

        $this->assertSame('2', $this->stat($this->dashboard(), 'active-plans'));
    }

    public function test_latest_22k_and_24k_rates_are_shown_from_the_database(): void
    {
        Carbon::setTestNow('2026-10-06 12:00:00');

        GoldRate::factory()->rate(6950)->on('2026-10-05')->create();
        GoldRate::factory()->rate(7015)->on('2026-10-06', '10:15:00')->create();
        GoldRate::factory()->rate(9999)->on('2026-10-06', '11:00:00')->inactive()->create();
        GoldRate::factory()->purity(GoldPurity::K24)->rate(7655)->on('2026-10-06', '09:30:00')->create();

        $response = $this->dashboard();

        $this->assertSame('₹7,015', $this->stat($response, 'rate-22k'));
        $this->assertSame('₹7,655', $this->stat($response, 'rate-24k'));
        $response->assertSee('06 Oct 2026, 10:15 AM')
            ->assertDontSee('₹9,999')
            ->assertDontSee('Development sample');

        Carbon::setTestNow();
    }

    public function test_development_seed_rates_are_flagged(): void
    {
        GoldRate::factory()->development()->rate(7000)->create();

        $this->dashboard()->assertSee('Development sample');
    }

    public function test_recent_customers_lists_latest_five_non_admins(): void
    {
        foreach (range(1, 7) as $i) {
            User::factory()->create(['name' => "Customer {$i}", 'created_at' => now()->subDays(10 - $i)]);
        }
        User::factory()->admin()->create(['name' => 'Hidden Admin', 'created_at' => now()]);

        $response = $this->dashboard();

        $response->assertSeeInOrder(['Customer 7', 'Customer 6', 'Customer 5', 'Customer 4', 'Customer 3'])
            ->assertDontSee('Customer 2')
            ->assertDontSee('Hidden Admin');

        $this->assertSame(5, substr_count($this->tableBody($response, 'recent-customers'), '<tr'));
    }

    public function test_recent_scheme_enrolments_show_details(): void
    {
        $customer = User::factory()->create(['name' => 'Lakshmi Raman']);
        $plan = Plan::factory()->create(['name' => 'Gold Plus']);

        foreach (range(1, 6) as $i) {
            CustomerPlan::factory()->for(User::factory())->for(Plan::factory())->create(['created_at' => now()->subDays(10 + $i)]);
        }

        $latest = CustomerPlan::factory()->for($customer)->for($plan)->status(CustomerPlanStatus::Active)->create([
            'scheme_number' => 'SSJ-2610-LATEST',
            'created_at' => now(),
        ]);

        $response = $this->dashboard();

        $body = $this->tableBody($response, 'recent-enrolments');
        $this->assertSame(5, substr_count($body, '<tr'));
        $this->assertStringContainsString('SSJ-2610-LATEST', $body);
        $response->assertSeeInOrder(['SSJ-2610-LATEST', 'Lakshmi Raman', 'Gold Plus', 'Active', $latest->created_at->format('d M Y')]);
    }

    public function test_sidebar_lists_working_pages_in_order_without_coming_soon_items(): void
    {
        $response = $this->dashboard();
        $html = $response->getContent();
        $sidebar = substr($html, strpos($html, 'id="admin-sidebar"'), strpos($html, '</aside>') - strpos($html, 'id="admin-sidebar"'));

        // Top-level order, then the account links at the bottom.
        $order = ['Dashboard', 'Customers', 'Gold Rates', 'Scheme Management', 'All Plans', 'Payments', 'User Details', 'KYC Verification', 'Push Notification', 'Settings', 'View Website', 'Logout'];
        $position = 0;
        foreach ($order as $label) {
            $found = strpos($sidebar, '>'.$label, $position) ?: strpos($sidebar, $label, $position);
            $this->assertNotFalse($found, "{$label} missing or out of order in the sidebar");
            $position = $found + strlen($label);
        }

        foreach (['Current Rates', 'Rate History', "Update Today's Rate", 'Add Plan', 'Registered Users', 'Customer Schemes List', 'User Payment History', 'Add Customer'] as $label) {
            $this->assertStringContainsString(e($label), $sidebar);
        }

        // Every item links to an existing route; nothing is marked "coming soon".
        foreach (['admin.dashboard', 'admin.customers.index', 'admin.gold-rates.index', 'admin.gold-rates.history', 'admin.gold-rates.create', 'admin.schemes.index', 'admin.plans.create', 'admin.plans.index', 'admin.payments.index', 'admin.customers.create', 'admin.kyc.index', 'admin.notifications.index', 'admin.settings.edit'] as $route) {
            $this->assertStringContainsString('href="'.route($route).'"', $sidebar, $route);
        }
        $this->assertStringNotContainsString('aria-disabled="true"', $sidebar);
        $this->assertStringNotContainsString('>Soon<', $sidebar);
        $this->assertStringNotContainsString('Exchange Scheme Approval', $html);
    }

    public function test_header_shows_page_title_and_banner_greets_the_administrator(): void
    {
        Carbon::setTestNow('2026-10-09 10:00:00');

        $this->dashboard()
            ->assertSee('data-header-title>Dashboard<', false)
            ->assertSee('data-welcome-banner', false)
            ->assertSeeText('Welcome Back, Administrator')
            ->assertSee("Here's what's happening with your jewellery savings today.", false)
            ->assertSee('Fri, 09 Oct 2026')
            ->assertSee(asset('images/home/cta-gold-earrings.jpg'), false)
            ->assertSee('href="'.route('admin.customers.create').'"', false)
            ->assertSee('href="'.route('admin.gold-rates.create').'"', false);

        $this->assertFileExists(public_path('images/home/cta-gold-earrings.jpg'));
        Carbon::setTestNow();
    }

    public function test_metric_cards_are_pastel_and_link_only_to_existing_pages(): void
    {
        $html = $this->dashboard()->getContent();

        foreach (['customers', 'active-schemes', 'pending-kyc', 'today-payments', 'today-amount', 'total-payments', 'rate-22k', 'rate-24k', 'active-plans'] as $key) {
            $this->assertMatchesRegularExpression('/class="admin-stat-card[^"]*"[^>]*data-stat="'.$key.'"/', $html, $key);
        }
        $this->assertStringContainsString('href="'.route('admin.schemes.index', ['status' => 'active']).'"', $html);
        $this->assertStringContainsString('href="'.route('admin.payments.index', ['status' => 'success']).'"', $html);
        $this->assertStringContainsString('href="'.route('admin.gold-rates.index').'"', $html);
    }

    public function test_summary_panels_show_real_status_counts(): void
    {
        User::factory()->count(3)->create();
        User::factory()->create(['status' => \App\Enums\AccountStatus::Inactive]);
        User::factory()->count(2)->create(['status' => \App\Enums\AccountStatus::Blocked]);
        User::factory()->admin()->create(); // never counted

        CustomerPlan::factory()->count(2)->status(CustomerPlanStatus::Active)->create();
        CustomerPlan::factory()->status(CustomerPlanStatus::Completed)->create();
        CustomerPlan::factory()->status(CustomerPlanStatus::Matured)->create();
        CustomerPlan::factory()->status(CustomerPlanStatus::Pending)->create(); // not charted

        $plan = CustomerPlan::query()->firstOrFail();
        $payment = fn (\App\Enums\PaymentStatus $status) => \App\Models\Payment::factory()->for($plan->user)->for($plan, 'customerPlan')->create(['status' => $status]);
        $payment(\App\Enums\PaymentStatus::Success);
        $payment(\App\Enums\PaymentStatus::Created);   // pending group
        $payment(\App\Enums\PaymentStatus::Pending);   // pending group
        $payment(\App\Enums\PaymentStatus::Failed);
        $payment(\App\Enums\PaymentStatus::Cancelled); // not charted

        $html = $this->dashboard()->getContent();
        $panel = function (string $key) use ($html) {
            $start = strpos($html, 'data-summary-panel="'.$key.'"');

            return substr($html, $start, strpos($html, '</article>', $start) - $start);
        };
        $value = fn (string $panelHtml, string $segment) => preg_match('/data-donut-segment="'.$segment.'".*?data-donut-value>([^<]*)</s', $panelHtml, $m) ? $m[1] : null;
        $total = fn (string $panelHtml) => preg_match('/data-donut-total="([^"]*)"/', $panelHtml, $m) ? $m[1] : null;
        $center = fn (string $panelHtml) => preg_match('/data-donut-center>([^<]*)</', $panelHtml, $m) ? $m[1] : null;

        // Customers: the plans above also created customer accounts (all Active).
        $customers = $panel('customers');
        $this->assertSame((string) User::query()->customers()->where('status', 'active')->count(), $value($customers, 'active'));
        $this->assertSame('1', $value($customers, 'inactive'));
        $this->assertSame('2', $value($customers, 'blocked'));
        $this->assertSame((string) User::query()->customers()->count(), $total($customers));
        $this->assertStringContainsString('href="'.route('admin.customers.index', ['status' => 'blocked']).'"', $customers);

        $schemes = $panel('schemes');
        $this->assertSame('2', $value($schemes, 'active'));
        $this->assertSame('1', $value($schemes, 'completed'));
        $this->assertSame('1', $value($schemes, 'matured'));
        $this->assertSame('4', $total($schemes));
        $this->assertSame('2', $center($schemes)); // centre shows active schemes

        $payments = $panel('payments');
        $this->assertSame('1', $value($payments, 'success'));
        $this->assertSame('2', $value($payments, 'pending'));
        $this->assertSame('1', $value($payments, 'failed'));
        $this->assertSame('4', $total($payments));
        $this->assertSame('0', $center($payments)); // centre shows today's successful payments
        $this->assertStringContainsString('href="'.route('admin.payments.index', ['status' => 'failed']).'"', $payments);
    }

    public function test_empty_database_shows_empty_charts(): void
    {
        $html = $this->dashboard()->getContent();

        $this->assertSame(3, preg_match_all('/data-donut-total="0"/', $html));
    }

    public function test_dashboard_does_not_expose_sensitive_kyc_data(): void
    {
        KycProfile::factory()->create(['pan_number' => 'ABCDE1234F', 'aadhaar_last4' => '9876']);

        $this->dashboard()->assertDontSee('ABCDE1234F')->assertDontSee('9876');
    }

    private function tableBody(TestResponse $response, string $table): string
    {
        preg_match('/data-table="'.$table.'".*?<tbody[^>]*>(.*?)<\/tbody>/s', $response->getContent(), $match);

        return $match[1] ?? '';
    }
}
