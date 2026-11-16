<?php

namespace Tests\Feature\Admin;

use App\Enums\KycAuditAction;
use App\Enums\KycDocumentStatus;
use App\Enums\KycDocumentType;
use App\Enums\KycStatus;
use App\Models\KycDocument;
use App\Models\KycProfile;
use App\Models\User;
use App\Services\KycService;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Customer\CustomerKycTest;
use Tests\TestCase;

class AdminKycTest extends TestCase
{
    use RefreshDatabase;

    private const PAN = 'BKLPS7319M';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(KycService::DISK);
        $this->admin = User::factory()->admin()->create(['name' => 'Office Admin']);
    }

    /** A customer with a KYC in the given status and a stored PAN document. */
    private function kyc(KycStatus $status = KycStatus::Submitted, array $customer = [], array $profile = []): KycProfile
    {
        $user = User::factory()->create($customer);
        $kyc = KycProfile::factory()->for($user)->create($profile + [
            'full_name' => $user->name, 'pan_number' => self::PAN, 'aadhaar_last4' => '4821',
            'address_line1' => '12 Temple Street', 'kyc_status' => $status,
            'submitted_at' => $status === KycStatus::Pending ? null : now(),
        ]);

        $path = $user->id.'/'.fake()->uuid().'.pdf';
        Storage::disk(KycService::DISK)->put($path, "%PDF-1.4 customer {$user->id}");
        KycDocument::factory()->for($kyc)->type(KycDocumentType::Pan)->create([
            'file_path' => $path, 'mime_type' => 'application/pdf', 'size_bytes' => 2048, 'document_number' => null,
        ]);

        return $kyc;
    }

    private function asAdmin(): static
    {
        return $this->actingAs($this->admin, 'web');
    }

    // Access ---------------------------------------------------------------------------

    public function test_guest_cannot_access_admin_kyc(): void
    {
        $kyc = $this->kyc();
        $document = $kyc->documents()->first();

        $this->get(route('admin.kyc.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.kyc.show', $kyc))->assertRedirect(route('admin.login'));
        $this->get(route('admin.kyc.documents.show', $document))->assertRedirect(route('admin.login'));
        $this->post(route('admin.kyc.approve', $kyc))->assertRedirect(route('admin.login'));
        $this->post(route('admin.kyc.reject', $kyc), ['rejection_reason' => 'Nope nope'])->assertRedirect(route('admin.login'));

        $this->assertSame(KycStatus::Submitted, $kyc->fresh()->kyc_status);
    }

    public function test_customer_cannot_access_admin_kyc(): void
    {
        $kyc = $this->kyc();
        $customer = $kyc->user;

        $this->actingAs($customer, 'customer');
        $this->get(route('admin.kyc.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.kyc.show', $kyc))->assertRedirect(route('admin.login'));
        $this->post(route('admin.kyc.approve', $kyc))->assertRedirect(route('admin.login'));

        // Even a customer account holding a web-guard session is not an admin.
        $this->actingAs($customer, 'web');
        $this->get(route('admin.kyc.index'))->assertForbidden();
        $this->get(route('admin.kyc.documents.show', $kyc->documents()->first()))->assertForbidden();
        $this->post(route('admin.kyc.approve', $kyc))->assertForbidden();

        $this->assertSame(KycStatus::Submitted, $kyc->fresh()->kyc_status);
    }

    // List -----------------------------------------------------------------------------

    public function test_admin_can_view_kyc_list_without_identity_numbers(): void
    {
        $kyc = $this->kyc(KycStatus::Submitted, ['name' => 'Priya Sundar', 'email' => 'priya@example.com', 'mobile_number' => '9876543210']);
        User::factory()->create(['name' => 'Never Started']);

        $response = $this->asAdmin()->get(route('admin.kyc.index'))->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Priya Sundar')->assertSee('priya@example.com')->assertSee('+91 98765 43210')
            ->assertSee('Pending Review')->assertSee('Never Started')->assertSee('Not started')
            ->assertSee(route('admin.kyc.show', $kyc), false)
            ->assertDontSee($this->admin->email);

        foreach ([self::PAN, 'XXXXXX319M', '4821', '12 Temple Street'] as $secret) {
            $response->assertDontSee($secret);
        }
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_pending_review_is_listed_first(): void
    {
        $this->kyc(KycStatus::Verified, ['name' => 'Approved Person']);
        $this->kyc(KycStatus::Pending, ['name' => 'Draft Person']);
        Carbon::setTestNow(now()->subDay());
        $this->kyc(KycStatus::Submitted, ['name' => 'Older Pending']);
        Carbon::setTestNow();
        $this->kyc(KycStatus::Submitted, ['name' => 'Newer Pending']);
        $this->kyc(KycStatus::Rejected, ['name' => 'Rejected Person']);

        $this->asAdmin()->get(route('admin.kyc.index'))->assertOk()
            ->assertSeeInOrder(['Older Pending', 'Newer Pending', 'Rejected Person', 'Draft Person', 'Approved Person']);
    }

    public function test_admin_can_filter_kyc(): void
    {
        $this->kyc(KycStatus::Submitted, ['name' => 'Pending Person']);
        $this->kyc(KycStatus::Verified, ['name' => 'Approved Person']);
        $this->kyc(KycStatus::Rejected, ['name' => 'Rejected Person']);
        $this->kyc(KycStatus::Pending, ['name' => 'Draft Person']);
        User::factory()->create(['name' => 'No Profile Person']);

        $this->asAdmin();
        $cases = [
            'pending_review' => ['Pending Person'],
            'approved' => ['Approved Person'],
            'rejected' => ['Rejected Person'],
            'not_submitted' => ['Draft Person', 'No Profile Person'],
        ];
        $everyone = ['Pending Person', 'Approved Person', 'Rejected Person', 'Draft Person', 'No Profile Person'];

        foreach ($cases as $status => $visible) {
            $response = $this->get(route('admin.kyc.index', ['status' => $status]))->assertOk();
            foreach ($everyone as $name) {
                in_array($name, $visible, true) ? $response->assertSee($name) : $response->assertDontSee($name);
            }
            $response->assertSee('data-kyc-filter="'.$status.'"', false);
        }

        $all = $this->get(route('admin.kyc.index'))->assertOk();
        foreach ($everyone as $name) {
            $all->assertSee($name);
        }
        // An unknown filter value simply shows everything.
        $this->get(route('admin.kyc.index', ['status' => "x' OR 1=1"]))->assertOk()->assertSee('Approved Person');
    }

    // Detail & documents ---------------------------------------------------------------

    public function test_admin_can_view_kyc_detail(): void
    {
        $kyc = $this->kyc(KycStatus::Submitted, ['name' => 'Priya Sundar', 'email' => 'priya@example.com'], ['city' => 'Chennai', 'pincode' => '600004']);

        $this->asAdmin()->get(route('admin.kyc.show', $kyc))->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Priya Sundar')->assertSee('priya@example.com')
            ->assertSee('12 Temple Street')->assertSee('Chennai')->assertSee('600004')
            ->assertSee(self::PAN)                 // authorised admins can verify the PAN
            ->assertSee('XXXX XXXX 4821')          // only the last 4 of Aadhaar exist
            ->assertSee('PAN Card')->assertSee('Aadhaar / Identity Document')
            ->assertSee('Approve KYC')->assertSee('Reject KYC')->assertSee('name="rejection_reason"', false)
            ->assertDontSee($kyc->documents()->first()->file_path);

        $this->get(route('admin.kyc.show', 999999))->assertNotFound();
    }

    public function test_admin_can_view_submitted_documents_securely(): void
    {
        $kyc = $this->kyc();
        $document = $kyc->documents()->first();

        $response = $this->asAdmin()->get(route('admin.kyc.documents.show', $document))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame("%PDF-1.4 customer {$kyc->user_id}", $response->streamedContent());

        $this->get(route('admin.kyc.documents.show', [$document, 'download' => 1]))->assertDownload('kyc-pan-card.pdf');
        $this->get(route('admin.kyc.documents.show', 999999))->assertNotFound();
    }

    public function test_unauthorised_document_access_is_blocked(): void
    {
        $mine = $this->kyc();
        $theirs = $this->kyc();
        $theirDocument = $theirs->documents()->first();

        // Guests and customers cannot use the admin document route…
        $this->get(route('admin.kyc.documents.show', $theirDocument))->assertRedirect(route('admin.login'));
        $this->actingAs($mine->user, 'customer')->get(route('admin.kyc.documents.show', $theirDocument))->assertRedirect(route('admin.login'));

        // …and the customer route only ever resolves the signed-in customer's own file.
        $own = $this->get(route('customer.kyc.documents.show', 'pan'))->assertOk()->streamedContent();
        $this->assertSame("%PDF-1.4 customer {$mine->user_id}", $own);
        $this->get(route('customer.kyc.documents.show', 'aadhaar'))->assertNotFound();

        // There is no public URL for the files.
        $this->get('/storage/'.$theirDocument->file_path)->assertNotFound();
        $this->get('/storage/app/private/kyc/'.$theirDocument->file_path)->assertNotFound();
        $this->assertFileDoesNotExist(public_path('storage/'.$theirDocument->file_path));
    }

    // Decisions ------------------------------------------------------------------------

    public function test_admin_can_approve_kyc_and_reviewer_is_recorded(): void
    {
        $kyc = $this->kyc();

        $this->asAdmin()->post(route('admin.kyc.approve', $kyc))
            ->assertRedirect(route('admin.kyc.show', $kyc))
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'approved'));

        $kyc->refresh();
        $this->assertSame(KycStatus::Verified, $kyc->kyc_status);
        $this->assertNotNull($kyc->verified_at);
        $this->assertNotNull($kyc->reviewed_at);
        $this->assertSame($this->admin->id, $kyc->reviewed_by);
        $this->assertNull($kyc->rejection_reason);
        $this->assertSame(KycDocumentStatus::Verified, $kyc->documents()->first()->status);

        $log = $kyc->auditLogs()->sole();
        $this->assertSame(KycAuditAction::Approved, $log->action);
        $this->assertSame($this->admin->id, $log->actor_id);

        $this->get(route('admin.kyc.show', $kyc))->assertSee('Office Admin')->assertDontSee('Approve KYC');
    }

    public function test_admin_can_reject_kyc_with_reason_and_reviewer_is_recorded(): void
    {
        $kyc = $this->kyc();

        $this->asAdmin()->post(route('admin.kyc.reject', $kyc), ['rejection_reason' => '  PAN document is   unclear.  '])
            ->assertRedirect(route('admin.kyc.show', $kyc));

        $kyc->refresh();
        $this->assertSame(KycStatus::Rejected, $kyc->kyc_status);
        $this->assertSame('PAN document is unclear.', $kyc->rejection_reason);
        $this->assertNotNull($kyc->reviewed_at);
        $this->assertSame($this->admin->id, $kyc->reviewed_by);
        $this->assertNull($kyc->verified_at);

        $log = $kyc->auditLogs()->sole();
        $this->assertSame(KycAuditAction::Rejected, $log->action);
        $this->assertSame('PAN document is unclear.', $log->note);
    }

    public function test_rejection_requires_a_reason(): void
    {
        $kyc = $this->kyc();
        $this->asAdmin()->from(route('admin.kyc.show', $kyc));

        $this->post(route('admin.kyc.reject', $kyc), [])->assertSessionHasErrors('rejection_reason');
        $this->post(route('admin.kyc.reject', $kyc), ['rejection_reason' => '   '])->assertSessionHasErrors('rejection_reason');
        $this->post(route('admin.kyc.reject', $kyc), ['rejection_reason' => str_repeat('a', 501)])->assertSessionHasErrors('rejection_reason');

        $this->assertSame(KycStatus::Submitted, $kyc->fresh()->kyc_status);
        $this->assertSame(0, $kyc->auditLogs()->count());
    }

    public function test_only_submitted_kyc_can_be_reviewed(): void
    {
        $this->asAdmin();

        foreach ([KycStatus::Pending, KycStatus::Verified, KycStatus::Rejected] as $status) {
            $kyc = $this->kyc($status);
            $this->from(route('admin.kyc.show', $kyc))->post(route('admin.kyc.approve', $kyc))->assertSessionHasErrors('kyc');
            $this->from(route('admin.kyc.show', $kyc))->post(route('admin.kyc.reject', $kyc), ['rejection_reason' => 'Please try again.'])->assertSessionHasErrors('kyc');
            $this->assertSame($status, $kyc->fresh()->kyc_status);
        }

        // A double-submitted approval is applied once.
        $kyc = $this->kyc();
        $this->post(route('admin.kyc.approve', $kyc));
        $this->post(route('admin.kyc.approve', $kyc))->assertSessionHasErrors('kyc');
        $this->assertSame(1, $kyc->auditLogs()->count());
    }

    public function test_customer_sees_approved_status_after_approval(): void
    {
        $kyc = $this->kyc();
        $this->asAdmin()->post(route('admin.kyc.approve', $kyc));

        $customer = $kyc->user->fresh();
        $this->actingAs($customer, 'customer')->get(route('customer.kyc'))->assertOk()
            ->assertSee('data-kyc-state="approved"', false)->assertSee('Your KYC has been verified.')->assertSee('Approved');
        $this->get(route('customer.dashboard'))->assertSee('data-kyc-status>Approved', false);
    }

    public function test_customer_sees_rejected_status_and_reason_after_rejection(): void
    {
        $kyc = $this->kyc();
        $this->asAdmin()->post(route('admin.kyc.reject', $kyc), ['rejection_reason' => 'Address proof is not readable.']);

        $customer = $kyc->user->fresh();
        $this->actingAs($customer, 'customer')->get(route('customer.kyc'))->assertOk()
            ->assertSee('Rejected')->assertSee('Your KYC needs attention.')
            ->assertSee('Address proof is not readable.')
            ->assertDontSee('Office Admin'); // reviewer identity stays internal
        $this->get(route('customer.dashboard'))->assertSee('data-kyc-status>Rejected', false)->assertSee('Update KYC');
    }

    public function test_full_cycle_reject_then_resubmit_then_approve(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer, 'customer')->put(route('customer.kyc.update'), CustomerKycTest::details() + [
            'intent' => 'submit', 'documents' => ['aadhaar' => CustomerKycTest::pdf(), 'pan' => CustomerKycTest::png()],
        ])->assertSessionHasNoErrors();
        $kyc = $customer->kycProfile()->firstOrFail();

        $this->asAdmin()->post(route('admin.kyc.reject', $kyc), ['rejection_reason' => 'Please upload a clearer identity document.']);

        $this->actingAs($customer, 'customer')->put(route('customer.kyc.update'), CustomerKycTest::details(['pan_number' => '', 'aadhaar_last4' => '']) + [
            'intent' => 'submit', 'documents' => ['aadhaar' => CustomerKycTest::png()],
        ])->assertSessionHasNoErrors();
        $this->assertSame(KycStatus::Submitted, $kyc->fresh()->kyc_status);

        $this->asAdmin()->post(route('admin.kyc.approve', $kyc))->assertSessionHasNoErrors();
        $this->assertSame(KycStatus::Verified, $kyc->fresh()->kyc_status);
        $this->assertSame(
            ['approved', 'submitted', 'rejected', 'submitted'],
            $kyc->auditLogs()->pluck('action')->map->value->all(),
        );

        $this->get(route('admin.kyc.show', $kyc))->assertSee('Submitted for review')->assertSee('Please upload a clearer identity document.');
    }

    // Security -------------------------------------------------------------------------

    public function test_kyc_actions_require_csrf(): void
    {
        // Laravel skips CSRF verification while running tests, so verify the wiring.
        $this->assertContains(ValidateCsrfToken::class, app(HttpKernel::class)->getMiddlewareGroups()['web']);

        foreach (['admin.kyc.approve', 'admin.kyc.reject', 'customer.kyc.update'] as $name) {
            $route = app('router')->getRoutes()->getByName($name);
            $this->assertContains('web', $route->gatherMiddleware(), $name);
            $this->assertNotContains('GET', $route->methods(), $name);
        }

        $kyc = $this->kyc();
        $html = $this->asAdmin()->get(route('admin.kyc.show', $kyc))->getContent();
        $this->assertSame(2, substr_count($html, 'name="_token"') - substr_count($this->get(route('admin.kyc.index'))->getContent(), 'name="_token"'));
    }

    public function test_admin_kyc_routes_use_admin_middleware(): void
    {
        foreach (['admin.kyc.index', 'admin.kyc.show', 'admin.kyc.approve', 'admin.kyc.reject', 'admin.kyc.documents.show'] as $name) {
            $this->assertContains('admin', app('router')->getRoutes()->getByName($name)->gatherMiddleware(), $name);
        }
    }

    public function test_kyc_data_does_not_leak_into_logs_or_dashboards(): void
    {
        Log::spy();
        $kyc = $this->kyc();

        $this->asAdmin()->get(route('admin.kyc.show', $kyc));
        $this->post(route('admin.kyc.reject', $kyc), ['rejection_reason' => 'PAN document is unclear.']);

        Log::shouldNotHaveReceived('info');
        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('error');
        Log::shouldNotHaveReceived('debug');

        $dashboard = $this->get(route('admin.dashboard'))->assertOk();
        foreach ([self::PAN, 'XXXXXX319M', '4821', '12 Temple Street'] as $secret) {
            $dashboard->assertDontSee($secret);
        }

        $this->assertStringNotContainsString(self::PAN, $kyc->fresh()->toJson());
        $this->assertStringNotContainsString(self::PAN, $kyc->auditLogs()->get()->toJson());
    }

    public function test_admin_dashboard_links_pending_kyc_and_sidebar_shows_queue(): void
    {
        $this->kyc();
        $this->kyc();
        $this->kyc(KycStatus::Pending);

        $html = $this->asAdmin()->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Manage KYC')
            ->assertSee('KYC Verification')
            ->getContent();

        $this->assertMatchesRegularExpression('/data-kyc-pending-badge>2</', $html);
    }
}
