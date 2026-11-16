<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountStatus;
use App\Enums\KycStatus;
use App\Models\KycProfile;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\FakesPaymentGateway;
use Tests\TestCase;

/**
 * Customer Details page (admin): action placement and visibility, KYC states, account access,
 * history order and the schemes / payments sections. Display only — the workflows themselves
 * are covered by AdminCustomerTest, AdminCustomerKycTest and CustomerAccountStatusTest.
 */
class AdminCustomerDetailPageTest extends TestCase
{
    use FakesPaymentGateway;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-07 11:00:00');
        $this->admin = User::factory()->admin()->create(['name' => 'Office Admin']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function page(User $customer): TestResponse
    {
        return $this->actingAs($this->admin, 'web')->get(route('admin.customers.show', $customer))->assertOk();
    }

    /** HTML of one section, from its marker to the next closing </section>. */
    private function section(string $html, string $marker): string
    {
        $start = strpos($html, $marker);
        $this->assertNotFalse($start, "Section {$marker} not found.");

        return substr($html, $start, strpos($html, '</section>', $start) - $start);
    }

    public function test_header_holds_edit_and_add_scheme_and_kyc_actions_appear_once_on_the_kyc_card(): void
    {
        $customer = User::factory()->create();
        $html = $this->page($customer)->assertSee('Back to Customers')->getContent();

        $header = $this->section($html, 'data-customer-header');
        $this->assertStringContainsString('Edit Customer', $header);
        $this->assertStringContainsString(route('admin.customers.schemes.create', $customer), $header);
        $this->assertStringNotContainsString('data-kyc-action', $header);

        // No duplicates: one Add KYC action (on the KYC card) and one Add Scheme link (in the header).
        $this->assertSame(1, substr_count($html, 'data-kyc-action="add"'));
        $this->assertSame(1, substr_count($html, 'href="'.route('admin.customers.schemes.create', $customer).'"'));
        $this->assertStringContainsString('data-kyc-action="add"', $this->section($html, 'aria-labelledby="kyc-heading"'));
    }

    public function test_kyc_card_actions_follow_the_kyc_status(): void
    {
        $cases = [
            // status => [view label or null, edit label or null, draft chip]
            'none' => [null, 'Add KYC', false],
            KycStatus::Pending->value => ['View KYC', 'Continue KYC', true],
            KycStatus::Submitted->value => ['Review KYC — approve or reject', null, false],
            KycStatus::Verified->value => ['View KYC', null, false],
            KycStatus::Rejected->value => ['View KYC', 'Update KYC', false],
        ];

        foreach ($cases as $status => [$view, $edit, $draft]) {
            $customer = User::factory()->create();
            $profile = $status === 'none' ? null : KycProfile::factory()->for($customer)->create(['kyc_status' => $status]);
            $kyc = $this->section($this->page($customer)->getContent(), 'aria-labelledby="kyc-heading"');

            $this->assertSame($view ? 1 : 0, substr_count($kyc, 'data-kyc-action="view"'), "view action for {$status}");
            $this->assertSame($edit ? 1 : 0, substr_count($kyc, 'data-kyc-action="add"'), "edit action for {$status}");
            if ($view) {
                $this->assertStringContainsString($view, $kyc);
                $this->assertStringContainsString(route('admin.kyc.show', $profile), $kyc);
            }
            if ($edit) {
                $this->assertStringContainsString($edit, $kyc);
            }
            $this->assertSame($draft, str_contains($kyc, 'data-kyc-draft'), "draft chip for {$status}");
            // Approval stays on the review screen: never offered here.
            $this->assertStringNotContainsString('/approve', $kyc);
        }
    }

    public function test_profile_shows_missing_mobile_gracefully_and_kyc_dates_when_available(): void
    {
        $customer = User::factory()->create(['mobile_number' => null]);
        KycProfile::factory()->for($customer)->create(['kyc_status' => KycStatus::Verified, 'submitted_at' => '2026-10-02 10:00:00', 'reviewed_at' => '2026-10-03 12:00:00']);

        $html = $this->page($customer)->getContent();
        $this->assertStringContainsString('Not provided', $this->section($html, 'data-customer-profile'));
        $kyc = $this->section($html, 'aria-labelledby="kyc-heading"');
        $this->assertStringContainsString('02 Oct 2026', $kyc);
        $this->assertStringContainsString('03 Oct 2026', $kyc);

        $fresh = User::factory()->create();
        $kyc = $this->section($this->page($fresh)->getContent(), 'aria-labelledby="kyc-heading"');
        $this->assertStringContainsString('Not submitted yet', $kyc);
        $this->assertStringContainsString('Not reviewed yet', $kyc);

        // Submitted/decided but the date is missing: say so instead of "not submitted".
        $undated = User::factory()->create();
        KycProfile::factory()->for($undated)->create(['kyc_status' => KycStatus::Verified, 'submitted_at' => null, 'reviewed_at' => null]);
        $kyc = $this->section($this->page($undated)->getContent(), 'aria-labelledby="kyc-heading"');
        $this->assertSame(2, substr_count($kyc, 'Date not recorded'));
        $this->assertStringNotContainsString('Not submitted yet', $kyc);
    }

    public function test_schemes_and_payments_sections_stay_white_list_sections_when_empty(): void
    {
        $html = $this->page(User::factory()->create())->getContent();

        $this->assertMatchesRegularExpression('/aria-labelledby="schemes-heading" data-list-section>.*?data-no-schemes/s', $html);
        $this->assertMatchesRegularExpression('/aria-labelledby="payments-heading" data-list-section>.*?data-no-payments/s', $html);
    }

    public function test_account_access_shows_the_right_action_for_each_status(): void
    {
        $active = User::factory()->create(['status' => AccountStatus::Active]);
        $access = $this->section($this->page($active)->getContent(), 'aria-labelledby="account-heading"');
        $this->assertStringContainsString('data-deactivate-form', $access);
        $this->assertStringNotContainsString('data-activate-form', $access);
        $this->assertMatchesRegularExpression('/class="btn btn-danger[^"]*"[^>]*>Block Customer</', $access); // destructive styling
        $this->assertStringContainsString('This customer can sign in.', $access);

        $inactive = User::factory()->create(['status' => AccountStatus::Inactive]);
        $access = $this->section($this->page($inactive)->getContent(), 'aria-labelledby="account-heading"');
        $this->assertStringContainsString('data-activate-form', $access);
        $this->assertStringContainsString('data-block-form', $access);
        $this->assertStringNotContainsString('data-deactivate-form', $access);

        $blocked = User::factory()->create(['status' => AccountStatus::Blocked]);
        $access = $this->section($this->page($blocked)->assertSee('data-blocked-notice', false)->getContent(), 'aria-labelledby="account-heading"');
        $this->assertStringContainsString('data-unblock-form', $access);
        $this->assertStringNotContainsString('data-block-form', $access);
    }

    public function test_history_is_listed_oldest_first_with_time_and_actor(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($this->admin, 'web')->post(route('admin.customers.block', $customer), ['reason' => 'Hold requested']);
        Carbon::setTestNow('2026-10-07 12:30:00');
        $this->post(route('admin.customers.unblock', $customer));

        $history = $this->section($this->page($customer)->getContent(), 'aria-labelledby="history-heading"');
        $blockedAt = strpos($history, 'Account blocked');
        $unblockedAt = strpos($history, 'Account unblocked');
        $this->assertNotFalse($blockedAt);
        $this->assertNotFalse($unblockedAt);
        $this->assertLessThan($unblockedAt, $blockedAt); // oldest first
        $this->assertStringContainsString('07 Oct 2026, 11:00 AM', $history);
        $this->assertStringContainsString('by Office Admin', $history);
        $this->assertStringContainsString('Hold requested', $history);
    }

    public function test_schemes_and_payments_sections_with_and_without_data(): void
    {
        $empty = User::factory()->create();
        $this->page($empty)
            ->assertSee('data-no-schemes', false)->assertSee('data-no-payments', false)
            ->assertDontSee('data-customer-scheme-list', false);

        $this->fakeGateway();
        $customer = $this->approvedCustomer();
        $scheme = $this->schemeFor($customer);
        $this->rateToday(12500);
        $payments = app(PaymentService::class);
        $payment = $payments->initiate($customer, $scheme, (string) Str::uuid());
        $payments->handleCallback($payment, $this->gatewayPays($payment), $customer);

        $html = $this->page($customer)->getContent();
        $this->assertStringContainsString('href="'.route('admin.schemes.index', ['user' => $customer->id]).'"', $html);
        $this->assertMatchesRegularExpression('/data-scheme-row="'.$scheme->scheme_number.'".*?₹5,000.*?0\.400000 g/s', $html);
        $this->assertMatchesRegularExpression('/data-payment-row="'.$payment->reference.'".*?₹5,000.*?0\.400000 g/s', $html);
        $this->assertStringContainsString('href="'.route('admin.payments.show', $payment).'"', $html);
        $this->assertMatchesRegularExpression('/data-customer-gold>0\.400000 g</', $html);
        $this->assertMatchesRegularExpression('/data-customer-gold-count>1 gold transaction</', $html);
    }
}
