<?php

namespace Tests\Feature\Admin;

use App\Enums\KycAuditAction;
use App\Enums\KycStatus;
use App\Models\CustomerPlan;
use App\Models\KycAuditLog;
use App\Models\KycProfile;
use App\Models\Payment;
use App\Models\User;
use App\Services\KycService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Customer\CustomerKycTest;
use Tests\TestCase;

/**
 * Admin entry of a customer's KYC from the customer detail page. Submission
 * moves KYC to Pending Review and is never an approval; approval stays a
 * separate review step.
 */
class AdminCustomerKycTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(KycService::DISK);
        $this->admin = User::factory()->admin()->create(['name' => 'Office Admin']);
        $this->customer = User::factory()->create(['name' => 'Priya Sundar']);
    }

    private function save(array $data, string $intent = 'draft', array $documents = []): TestResponse
    {
        return $this->actingAs($this->admin, 'web')
            ->from(route('admin.customers.kyc.edit', $this->customer))
            ->put(route('admin.customers.kyc.update', $this->customer), $data + ['intent' => $intent, 'documents' => $documents]);
    }

    private function submitComplete(): TestResponse
    {
        return $this->save(CustomerKycTest::details(), 'submit', ['aadhaar' => CustomerKycTest::pdf(), 'pan' => CustomerKycTest::png()]);
    }

    private function profile(): KycProfile
    {
        return $this->customer->kycProfile()->with('documents')->firstOrFail();
    }

    public function test_customer_detail_shows_add_kyc_beside_edit_customer_when_not_submitted(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.customers.show', $this->customer))->assertOk()
            ->assertSeeInOrder(['Edit Customer', 'Add KYC'])
            ->assertSee('href="'.route('admin.customers.kyc.edit', $this->customer).'"', false)
            ->assertSee('data-kyc-action="add"', false)
            ->assertDontSee('data-kyc-action="view"', false) // nothing to view yet
            ->assertSee('Not uploaded')
            ->assertSee('data-kyc-not-submitted', false)
            ->assertSee('KYC Not Submitted')
            ->assertSee('The customer can submit KYC through the customer portal');
    }

    public function test_kyc_links_for_every_status_point_to_the_right_screen(): void
    {
        $this->actingAs($this->admin, 'web');

        // Draft → View KYC (review screen, read-only for drafts) + Continue KYC.
        $this->save(['full_name' => 'Priya Sundar']);
        $profile = $this->profile();
        $this->get(route('admin.customers.show', $this->customer))
            ->assertSee('data-kyc-action="view"', false)->assertSee('href="'.route('admin.kyc.show', $profile).'"', false)
            ->assertSee('Continue KYC')->assertSee('KYC Not Submitted (draft saved)');
        $this->get(route('admin.kyc.show', $profile))->assertOk()->assertSee('data-kyc-draft-notice', false);

        // Submitted → View KYC + "Review KYC — approve or reject"; no Add/Continue.
        $this->submitComplete()->assertSessionHasNoErrors();
        $this->get(route('admin.customers.show', $this->customer))
            ->assertSee('data-kyc-action="view"', false)->assertSee('Review KYC — approve or reject')
            ->assertDontSee('data-kyc-action="add"', false)->assertDontSee('data-kyc-not-submitted', false);

        // Approved → View KYC only.
        $this->post(route('admin.kyc.approve', $profile));
        $this->get(route('admin.customers.show', $this->customer))
            ->assertSee('data-kyc-action="view"', false)->assertDontSee('data-kyc-action="add"', false)->assertSee('Approved');
    }

    public function test_approval_and_rejection_stay_on_the_review_screen_only(): void
    {
        $this->submitComplete();
        $profile = $this->profile();

        $page = $this->get(route('admin.customers.show', $this->customer))->assertOk()->getContent();
        $this->assertStringNotContainsString(route('admin.kyc.approve', $profile), $page);
        $this->assertStringNotContainsString(route('admin.kyc.reject', $profile), $page);
        $this->assertStringNotContainsString(route('admin.kyc.approve', $profile), $this->get(route('admin.customers.kyc.edit', $this->customer))->getContent());

        $this->get(route('admin.kyc.show', $profile))->assertOk()
            ->assertSee(route('admin.kyc.approve', $profile), false)->assertSee(route('admin.kyc.reject', $profile), false);
    }

    public function test_review_screen_and_documents_require_an_admin(): void
    {
        $this->submitComplete();
        $profile = $this->profile();
        $document = $profile->documents->first();
        auth('web')->logout();

        $this->get(route('admin.kyc.show', $profile))->assertRedirect(route('admin.login'));
        $this->get(route('admin.kyc.documents.show', $document))->assertRedirect(route('admin.login'));
        $this->actingAs($this->customer, 'web')->get(route('admin.kyc.show', $profile))->assertForbidden();
        $this->actingAs($this->customer, 'web')->get(route('admin.kyc.documents.show', $document))->assertForbidden();
        $this->actingAs($this->customer, 'web')->post(route('admin.kyc.approve', $profile))->assertForbidden();

        $this->assertSame(KycStatus::Submitted, $profile->fresh()->kyc_status);
    }

    public function test_admin_can_open_the_kyc_form(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.customers.kyc.edit', $this->customer))->assertOk()
            ->assertSee('Add KYC — Priya Sundar')
            ->assertSee('Back to Customer')
            ->assertSee('name="pan_number"', false)->assertSee('name="aadhaar_last4"', false)
            ->assertSee('name="documents[aadhaar]"', false)->assertSee('name="documents[pan]"', false)
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('Submit for Review')->assertSee('Save Draft')
            ->assertSee('Submitting does not approve the KYC')
            ->assertDontSee('name="kyc_status"', false);
    }

    public function test_admin_draft_is_saved_and_audited_without_notifying_or_submitting(): void
    {
        $this->save(['full_name' => 'Priya Sundar', 'city' => 'Chennai'])
            ->assertRedirect(route('admin.customers.kyc.edit', $this->customer))->assertSessionHasNoErrors();

        $profile = $this->profile();
        $this->assertSame(KycStatus::Pending, $profile->kyc_status);
        $this->assertNull($profile->submitted_at);
        $log = KycAuditLog::query()->sole();
        $this->assertSame(KycAuditAction::EnteredByAdmin, $log->action);
        $this->assertSame($this->admin->id, $log->actor_id);
        $this->assertSame(0, $this->customer->notifications()->count());

        $this->get(route('admin.customers.show', $this->customer))->assertSee('Continue KYC')->assertSee('data-kyc-action="view"', false);
    }

    public function test_admin_submission_goes_to_pending_review_and_is_never_auto_approved(): void
    {
        $this->submitComplete()->assertSessionHasNoErrors()->assertRedirect(route('admin.kyc.show', $this->profile()));

        $profile = $this->profile();
        $this->assertSame(KycStatus::Submitted, $profile->kyc_status);
        $this->assertNotNull($profile->submitted_at);
        $this->assertNull($profile->verified_at);
        $this->assertNull($profile->reviewed_at);
        $this->assertNull($profile->reviewed_by);

        // Documents in PRIVATE storage under server-generated names.
        $this->assertCount(2, $profile->documents);
        foreach ($profile->documents as $document) {
            Storage::disk(KycService::DISK)->assertExists($document->file_path);
            $this->assertStringStartsWith($this->customer->id.'/', $document->file_path);
            $this->assertStringNotContainsString('scan', $document->file_path);
        }

        // Audit: submitted by the admin, on the customer's behalf; the customer is notified.
        $log = KycAuditLog::query()->sole();
        $this->assertSame(KycAuditAction::Submitted, $log->action);
        $this->assertSame($this->admin->id, $log->actor_id);
        $this->assertSame('Entered by an administrator on behalf of the customer.', $log->note);
        $this->assertSame(1, $this->customer->notifications()->count());

        // Detail page: View/Review KYC, dates and documents.
        $this->get(route('admin.customers.show', $this->customer))
            ->assertSee('data-kyc-action="view"', false)->assertSee('Review KYC')->assertDontSee('data-kyc-action="add"', false)
            ->assertSee('Pending Review')->assertSee(now()->format('d M Y'))
            ->assertSee(route('admin.kyc.documents.show', $profile->documents->first()), false);

        // Approval is a separate, explicit step.
        $this->post(route('admin.kyc.approve', $profile))->assertRedirect(route('admin.kyc.show', $profile));
        $this->assertSame(KycStatus::Verified, $profile->fresh()->kyc_status);
    }

    public function test_submitted_or_approved_kyc_cannot_be_changed_from_the_admin_form(): void
    {
        $this->submitComplete();
        $profile = $this->profile();

        $this->get(route('admin.customers.kyc.edit', $this->customer))->assertRedirect(route('admin.kyc.show', $profile));
        $this->save(CustomerKycTest::details(['city' => 'Madurai']), 'submit')->assertSessionHasErrors('kyc');
        $this->assertSame('Chennai', $profile->fresh()->city);

        $this->post(route('admin.kyc.approve', $profile));
        $this->save(CustomerKycTest::details(['city' => 'Madurai']), 'draft')->assertSessionHasErrors('kyc');
        $this->assertSame(KycStatus::Verified, $profile->fresh()->kyc_status);
        $this->assertSame('Chennai', $profile->fresh()->city);
    }

    public function test_rejected_kyc_can_be_updated_and_resubmitted_by_admin(): void
    {
        $this->submitComplete();
        $profile = $this->profile();
        $this->post(route('admin.kyc.reject', $profile), ['rejection_reason' => 'PAN copy is unclear.']);

        $this->get(route('admin.customers.show', $this->customer))->assertSee('Update KYC')->assertSee('View KYC');
        $this->get(route('admin.customers.kyc.edit', $this->customer))->assertOk()->assertSee('PAN copy is unclear.');

        // As the form does: details pre-filled, identity numbers left blank (kept on file), PAN copy replaced.
        $this->save(CustomerKycTest::details(['aadhaar_last4' => '', 'pan_number' => '']), 'submit', ['pan' => CustomerKycTest::png('clear.png')])->assertSessionHasNoErrors();
        $this->assertSame(KycStatus::Submitted, $profile->fresh()->kyc_status);
        $this->assertNull($profile->fresh()->rejection_reason);
    }

    public function test_status_and_review_fields_in_the_request_are_ignored(): void
    {
        $this->save(CustomerKycTest::details() + ['kyc_status' => 'verified', 'verified_at' => now()->toDateTimeString(), 'reviewed_by' => $this->admin->id, 'user_id' => $this->admin->id],
            'submit', ['aadhaar' => CustomerKycTest::pdf(), 'pan' => CustomerKycTest::png()])->assertSessionHasNoErrors();

        $profile = $this->profile();
        $this->assertSame(KycStatus::Submitted, $profile->kyc_status);
        $this->assertNull($profile->verified_at);
        $this->assertNull($profile->reviewed_by);
        $this->assertSame($this->customer->id, $profile->user_id);
        $this->assertNull($this->admin->kycProfile);
    }

    public function test_validation_matches_the_customer_form(): void
    {
        $this->save(CustomerKycTest::details(['pan_number' => 'BAD']), 'submit', ['aadhaar' => CustomerKycTest::pdf(), 'pan' => CustomerKycTest::png()])
            ->assertSessionHasErrors('pan_number');
        $this->save(CustomerKycTest::details(['aadhaar_last4' => '123456789012']), 'submit')->assertSessionHasErrors('aadhaar_last4');
        $this->save(CustomerKycTest::details(), 'submit')->assertSessionHasErrors(['documents.aadhaar', 'documents.pan']);
        $this->save(CustomerKycTest::details(['date_of_birth' => now()->subYears(10)->toDateString()]), 'draft')
            ->assertSessionHasErrors(['date_of_birth' => 'The customer must be at least 18 years old.']);
        $this->save([], 'draft', ['pan' => CustomerKycTest::realUpload('shell.pdf', '<?php echo 1;')])->assertSessionHasErrors('documents.pan');

        $this->assertSame(0, KycProfile::query()->where('kyc_status', '!=', KycStatus::Pending->value)->count());
        $this->assertSame([], Storage::disk(KycService::DISK)->allFiles());
    }

    public function test_only_admins_can_enter_kyc_and_never_for_admin_accounts(): void
    {
        // Guest / customer / customer on the web guard.
        $this->get(route('admin.customers.kyc.edit', $this->customer))->assertRedirect(route('admin.login'));
        $this->actingAs($this->customer, 'customer')->put(route('admin.customers.kyc.update', $this->customer), CustomerKycTest::details() + ['intent' => 'draft'])
            ->assertRedirect(route('admin.login'));
        $this->actingAs($this->customer, 'web')->get(route('admin.customers.kyc.edit', $this->customer))->assertForbidden();

        // {customer} never resolves to an admin account.
        $this->actingAs($this->admin, 'web')->get(route('admin.customers.kyc.edit', $this->admin->id))->assertNotFound();

        $this->assertDatabaseCount('kyc_profiles', 0);
    }

    public function test_existing_customer_scheme_and_payment_data_is_untouched(): void
    {
        $scheme = CustomerPlan::factory()->for($this->customer)->create();
        Payment::factory()->for($scheme, 'customerPlan')->create(['user_id' => $this->customer->id]);
        $before = [$scheme->fresh()->getAttributes(), Payment::query()->sole()->getAttributes(), $this->customer->fresh()->getAttributes()];

        $this->submitComplete()->assertSessionHasNoErrors();

        $this->assertEquals($before, [$scheme->fresh()->getAttributes(), Payment::query()->sole()->getAttributes(), $this->customer->fresh()->getAttributes()]);
    }
}
