<?php

namespace Tests\Feature\Customer;

use App\Enums\CustomerPlanStatus;
use App\Enums\KycStatus;
use App\Models\CustomerPlan;
use App\Models\KycProfile;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CustomerDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::factory()->create(['name' => 'Priya Sundar', 'email' => 'priya@example.com', 'mobile_number' => '9876543210']);
    }

    private function stat(TestResponse $response, string $key): string
    {
        preg_match('/data-stat="'.$key.'".*?data-stat-value>(.*?)<\/p>/s', $response->getContent(), $m);

        return trim($m[1] ?? '');
    }

    public function test_guest_cannot_access_customer_pages(): void
    {
        foreach (['customer.dashboard', 'customer.schemes.index', 'customer.plans.index', 'customer.profile'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
        $this->getJson(route('customer.dashboard'))->assertUnauthorized();
    }

    public function test_customer_can_access_own_dashboard_with_honest_empty_states(): void
    {
        CustomerPlan::factory()->for($this->customer)->for(Plan::factory()->create(['name' => 'Gold Plus']))->pending()->create();

        $response = $this->actingAs($this->customer, 'customer')->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('Welcome, ')
            ->assertSee('Priya Sundar')
            ->assertSee('Join Another Scheme')
            ->assertSee('href="'.route('customer.plans.index').'"', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->assertSame('1', $this->stat($response, 'active-schemes'));
        $this->assertSame('₹0', $this->stat($response, 'total-contributed'));
        $this->assertSame('0.000000 g', $this->stat($response, 'gold-accumulated')); // 6-decimal gold precision since Stage 6
        $this->assertSame('Awaiting first payment', $this->stat($response, 'current-value'));
        $response->assertSee('1 awaiting first payment');
    }

    public function test_customer_sees_own_schemes_with_copied_commitment(): void
    {
        $plan = Plan::factory()->create(['name' => 'Gold Premium', 'monthly_amount' => 5000, 'duration_months' => 11]);
        $scheme = CustomerPlan::factory()->for($this->customer)->for($plan)->status(CustomerPlanStatus::Active)->create(['scheme_number' => 'SSJ-2610-MINE01']);
        $plan->update(['monthly_amount' => 9000]); // later plan change

        $response = $this->actingAs($this->customer, 'customer')->get(route('customer.dashboard'));

        $response->assertSee('SSJ-2610-MINE01')->assertSee('Gold Premium')->assertSee('data-scheme-status="active"', false)
            ->assertSee('₹5,000')->assertSee('11 months')->assertSee('₹55,000')
            ->assertSee($scheme->created_at->format('d M Y'))
            ->assertDontSee('₹9,000');
        $this->assertMatchesRegularExpression('/data-scheme-paid>₹0</', $response->getContent());
        $this->assertMatchesRegularExpression('/data-scheme-gold>0\.000000 g</', $response->getContent()); // 6-decimal precision since Stage 6

        $this->get(route('customer.schemes.index'))->assertOk()->assertSee('SSJ-2610-MINE01');
        $this->get(route('customer.schemes.show', 'SSJ-2610-MINE01'))->assertOk()
            ->assertSee('Your Commitment')->assertSee('₹55,000')->assertSee('0 of 11');
    }

    public function test_dashboard_empty_state_without_schemes(): void
    {
        $response = $this->actingAs($this->customer, 'customer')->get(route('customer.dashboard'))->assertOk();

        $response->assertSee('data-empty-schemes', false)->assertSee('You have not joined a scheme yet.');
        $this->assertSame('0', $this->stat($response, 'active-schemes'));
    }

    public function test_customer_cannot_access_another_customers_data(): void
    {
        $other = User::factory()->create(['name' => 'Other Person']);
        CustomerPlan::factory()->for($other)->create(['scheme_number' => 'SSJ-2610-OTHER1']);
        CustomerPlan::factory()->for($this->customer)->create(['scheme_number' => 'SSJ-2610-MINE02']);

        $this->actingAs($this->customer, 'customer');

        $this->get(route('customer.schemes.show', 'SSJ-2610-OTHER1'))->assertNotFound();
        $this->get(route('customer.dashboard'))->assertDontSee('SSJ-2610-OTHER1')->assertDontSee('Other Person');
        $this->get(route('customer.schemes.index'))->assertDontSee('SSJ-2610-OTHER1')->assertSee('SSJ-2610-MINE02');

        // Sequential ids are not part of customer URLs.
        $this->get('/customer/schemes/'.CustomerPlan::query()->where('scheme_number', 'SSJ-2610-OTHER1')->value('id'))->assertNotFound();
    }

    public function test_profile_only_exposes_safe_fields(): void
    {
        $response = $this->actingAs($this->customer, 'customer')->get(route('customer.profile'))->assertOk();

        $response->assertSee('Priya Sundar')->assertSee('priya@example.com')->assertSee('+91 98765 43210')
            ->assertSee($this->customer->created_at->format('d M Y'));

        $html = $response->getContent();
        $this->assertStringNotContainsString($this->customer->password, $html);
        $this->assertStringNotContainsString('is_admin', $html);
        $this->assertStringNotContainsString('remember_token', $html);
        $this->assertStringNotContainsString('name="name"', $html); // read-only in Stage 4
    }

    public function test_kyc_status_shows_without_sensitive_data(): void
    {
        $this->actingAs($this->customer, 'customer')->get(route('customer.dashboard'))
            ->assertSee('data-kyc-status>Not Submitted', false);

        KycProfile::factory()->for($this->customer)->create([
            'kyc_status' => KycStatus::Submitted, 'pan_number' => 'ABCDE1234F', 'aadhaar_last4' => '9876', 'address_line1' => '12 Temple Street',
        ]);

        foreach (['customer.dashboard', 'customer.profile'] as $route) {
            $this->get(route($route))
                ->assertSee('Pending Review')
                ->assertDontSee('ABCDE1234F')
                ->assertDontSee('XXXXXX234F')
                ->assertDontSee('12 Temple Street');
        }
    }

    public function test_header_shows_customer_links_only_for_customer_sessions(): void
    {
        $guest = $this->get(route('home'))->getContent();
        $this->assertStringNotContainsString('data-customer-nav', $guest);
        $this->assertStringContainsString('href="'.route('login').'"', $guest);

        // An admin session is not a customer session.
        $this->actingAs(User::factory()->admin()->create(), 'web');
        $this->assertStringNotContainsString('data-customer-nav', $this->get(route('home'))->getContent());

        $this->actingAs($this->customer, 'customer');
        $customer = $this->get(route('home'))->getContent();
        $this->assertStringContainsString('data-customer-nav', $customer);
        foreach (['customer.dashboard', 'customer.schemes.index', 'customer.profile', 'customer.logout'] as $route) {
            $this->assertStringContainsString(route($route), $customer);
        }
    }

    public function test_customer_pages_are_not_cached_or_indexed(): void
    {
        $this->actingAs($this->customer, 'customer')->get(route('customer.profile'))
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }
}
