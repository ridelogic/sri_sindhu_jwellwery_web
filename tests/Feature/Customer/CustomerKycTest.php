<?php

namespace Tests\Feature\Customer;

use App\Enums\KycAuditAction;
use App\Enums\KycDocumentType;
use App\Enums\KycStatus;
use App\Models\KycDocument;
use App\Models\KycProfile;
use App\Models\User;
use App\Services\KycService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CustomerKycTest extends TestCase
{
    use RefreshDatabase;

    private const PAN = 'BKLPS7319M';

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(KycService::DISK);
        $this->customer = User::factory()->create(['name' => 'Priya Sundar']);
    }

    /** A real file on disk, so MIME detection reads the actual content. */
    public static function realUpload(string $clientName, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'kyc');
        file_put_contents($path, $contents);

        return new UploadedFile($path, $clientName, null, null, true);
    }

    public static function pdf(string $name = 'scan.pdf'): UploadedFile
    {
        return self::realUpload($name, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
    }

    public static function png(string $name = 'photo.png'): UploadedFile
    {
        return self::realUpload($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
    }

    public static function details(array $overrides = []): array
    {
        return $overrides + [
            'full_name' => 'Priya Sundar',
            'date_of_birth' => '1990-04-12',
            'gender' => 'female',
            'address_line1' => '12 Temple Street',
            'address_line2' => 'Mylapore',
            'city' => 'Chennai',
            'state' => 'Tamil Nadu',
            'pincode' => '600004',
            'aadhaar_last4' => '4821',
            'pan_number' => self::PAN,
        ];
    }

    private function save(array $data, string $intent = 'draft', array $documents = []): TestResponse
    {
        return $this->actingAs($this->customer, 'customer')
            ->from(route('customer.kyc.edit'))
            ->put(route('customer.kyc.update'), $data + ['intent' => $intent, 'documents' => $documents]);
    }

    private function submitComplete(): TestResponse
    {
        return $this->save(self::details(), 'submit', ['aadhaar' => self::pdf(), 'pan' => self::png()]);
    }

    private function profile(): KycProfile
    {
        return $this->customer->kycProfile()->with('documents')->firstOrFail();
    }

    // Access ---------------------------------------------------------------------------

    public function test_guest_cannot_access_kyc(): void
    {
        $this->get(route('customer.kyc'))->assertRedirect(route('login'));
        $this->get(route('customer.kyc.edit'))->assertRedirect(route('login'));
        $this->put(route('customer.kyc.update'), self::details() + ['intent' => 'draft'])->assertRedirect(route('login'));
        $this->get(route('customer.kyc.documents.show', 'pan'))->assertRedirect(route('login'));
        $this->assertDatabaseCount('kyc_profiles', 0);
    }

    public function test_customer_can_access_own_kyc_page(): void
    {
        $this->actingAs($this->customer, 'customer')->get(route('customer.kyc'))
            ->assertOk()
            ->assertSee('KYC Verification')
            ->assertSee('data-kyc-state="not_submitted"', false)
            ->assertSee('Complete your KYC to continue.')
            ->assertSee('Start KYC')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->get(route('customer.kyc.edit'))->assertOk()
            ->assertSee('Personal Details')->assertSee('Contact &amp; Address', false)->assertSee('Identity')->assertSee('Documents')
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="documents[pan]"', false)
            ->assertSee('Save Draft')->assertSee('Submit for Review');
    }

    public function test_kyc_is_in_customer_navigation(): void
    {
        $html = $this->actingAs($this->customer, 'customer')->get(route('customer.dashboard'))->getContent();

        $this->assertStringContainsString('href="'.route('customer.kyc').'"', $html);
    }

    // Saving details ---------------------------------------------------------------------

    public function test_customer_can_create_kyc_with_a_draft(): void
    {
        $this->save(['full_name' => 'Priya Sundar', 'city' => 'Chennai'])
            ->assertRedirect(route('customer.kyc.edit'))
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'draft has been saved'));

        $profile = $this->profile();
        $this->assertSame('Priya Sundar', $profile->full_name);
        $this->assertSame(KycStatus::Pending, $profile->kyc_status); // drafts stay "not submitted"
        $this->assertNull($profile->submitted_at);
    }

    public function test_customer_can_save_kyc_details(): void
    {
        $this->save(self::details())->assertSessionHasNoErrors();

        $profile = $this->profile();
        $this->assertSame('1990-04-12', $profile->date_of_birth->toDateString());
        $this->assertSame('female', $profile->gender);
        $this->assertSame('12 Temple Street', $profile->address_line1);
        $this->assertSame('Tamil Nadu', $profile->state);
        $this->assertSame('600004', $profile->pincode);
        $this->assertSame(self::PAN, $profile->pan_number);
        $this->assertSame(KycStatus::Pending, $profile->kyc_status);

        // Blank identity fields keep what is already on file.
        $this->save(self::details(['aadhaar_last4' => '', 'pan_number' => '', 'city' => 'Madurai']))->assertSessionHasNoErrors();
        $profile->refresh();
        $this->assertSame(self::PAN, $profile->pan_number);
        $this->assertSame('4821', $profile->aadhaar_last4);
        $this->assertSame('Madurai', $profile->city);
    }

    public function test_aadhaar_stores_only_last_4_digits(): void
    {
        $this->save(self::details(['aadhaar_last4' => '1234 5678 9012']))
            ->assertSessionHasErrors(['aadhaar_last4' => 'Enter only the last 4 digits of your Aadhaar number.']);

        $this->assertDatabaseCount('kyc_profiles', 0);
        $this->assertNull(session()->getOldInput('aadhaar_last4')); // never flashed back

        $this->save(self::details(['aadhaar_last4' => '9012']))->assertSessionHasNoErrors();
        $this->assertSame('9012', DB::table('kyc_profiles')->value('aadhaar_last4'));
    }

    public function test_pan_is_encrypted_at_rest(): void
    {
        $this->save(self::details(['pan_number' => 'bklps 7319m']))->assertSessionHasNoErrors();

        $raw = DB::table('kyc_profiles')->value('pan_number');
        $this->assertStringNotContainsString(self::PAN, $raw);
        $this->assertSame(self::PAN, $this->profile()->pan_number); // normalised + decrypts
    }

    public function test_sensitive_fields_are_not_exposed_in_json_or_pages(): void
    {
        $this->submitComplete();
        $profile = $this->profile();

        $json = $profile->toJson().$profile->documents->toJson();
        foreach ([self::PAN, '4821', '12 Temple Street', '1990-04-12', 'Priya Sundar', '600004'] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
        $this->assertStringNotContainsString($profile->documents->first()->file_path, $json);

        $page = $this->get(route('customer.kyc'))->assertOk()->assertSee('XXXXXX319M')->assertSee('XXXX XXXX 4821')->getContent();
        $this->assertStringNotContainsString(self::PAN, $page);
        foreach ($profile->documents as $document) {
            $this->assertStringNotContainsString($document->file_path, $page);
        }
    }

    public function test_edit_form_never_prefills_identity_numbers(): void
    {
        $this->save(self::details())->assertSessionHasNoErrors();

        $html = $this->get(route('customer.kyc.edit'))->assertOk()->assertSee('On file: XXXXXX319M')->getContent();

        $this->assertStringNotContainsString(self::PAN, $html);
        $this->assertStringNotContainsString('value="4821"', $html);
    }

    // Validation -------------------------------------------------------------------------

    public function test_required_validation_on_submission(): void
    {
        $this->save([], 'submit')->assertSessionHasErrors([
            'full_name', 'date_of_birth', 'gender', 'address_line1', 'city', 'state', 'pincode',
            'aadhaar_last4', 'pan_number', 'documents.aadhaar', 'documents.pan',
        ])->assertSessionDoesntHaveErrors('documents.address_proof'); // optional

        $this->assertDatabaseCount('kyc_profiles', 0);
    }

    public function test_draft_rejects_invalid_values_but_not_missing_ones(): void
    {
        $this->save(['intent' => 'draft'])->assertSessionHasNoErrors();
        $this->save(['date_of_birth' => now()->subYears(10)->toDateString(), 'state' => 'Atlantis', 'gender' => 'x'])
            ->assertSessionHasErrors(['date_of_birth', 'state', 'gender']);
    }

    public function test_invalid_pincode_is_rejected(): void
    {
        foreach (['12345', '1234567', '012345', 'ABCDEF'] as $pincode) {
            $this->save(self::details(['pincode' => $pincode]))->assertSessionHasErrors(['pincode' => 'Enter a valid 6-digit pincode.']);
        }
    }

    public function test_invalid_pan_is_rejected(): void
    {
        foreach (['ABCDE1234', '12345ABCDE', 'ABCD12345F', 'ABCDE1234FF'] as $pan) {
            $this->save(self::details(['pan_number' => $pan]))->assertSessionHasErrors(['pan_number' => 'Enter a valid PAN, e.g. ABCDE1234F.']);
        }
        $this->assertNull(session()->getOldInput('pan_number'));
        $this->assertDatabaseCount('kyc_profiles', 0);
    }

    public function test_invalid_aadhaar_last4_is_rejected(): void
    {
        foreach (['123', '12345', 'abcd', '123456789012'] as $value) {
            $this->save(self::details(['aadhaar_last4' => $value]))->assertSessionHasErrors('aadhaar_last4');
        }
        $this->assertDatabaseCount('kyc_profiles', 0);
    }

    // Documents --------------------------------------------------------------------------

    public function test_customer_can_upload_valid_documents(): void
    {
        $this->save(self::details(), 'draft', ['pan' => self::pdf(), 'aadhaar' => self::png()])->assertSessionHasNoErrors();

        $documents = $this->profile()->documents->keyBy(fn ($d) => $d->document_type->value);
        $this->assertSame('application/pdf', $documents['pan']->mime_type);
        $this->assertSame('image/png', $documents['aadhaar']->mime_type);
        $this->assertGreaterThan(0, $documents['pan']->size_bytes);
        $this->assertSame($this->customer->id, $documents['pan']->user_id);
        Storage::disk(KycService::DISK)->assertExists($documents['pan']->file_path);
    }

    public function test_invalid_file_types_are_rejected(): void
    {
        $uploads = [
            UploadedFile::fake()->create('shell.php', 5, 'application/x-php'),
            UploadedFile::fake()->create('page.html', 5, 'text/html'),
            UploadedFile::fake()->create('icon.svg', 5, 'image/svg+xml'),
            UploadedFile::fake()->create('script.js', 5, 'text/javascript'),
            UploadedFile::fake()->create('setup.exe', 5, 'application/x-msdownload'),
            UploadedFile::fake()->create('files.zip', 5, 'application/zip'),
            // Disguised: a PHP script renamed to .jpg — the content is checked, not just the extension.
            self::realUpload('photo.jpg', '<?php echo "pwned"; ?>'),
            // And a genuine PDF renamed to .php is still refused by the extension check.
            self::pdf('document.php'),
        ];

        foreach ($uploads as $file) {
            $this->save(self::details(), 'draft', ['pan' => $file])->assertSessionHasErrors('documents.pan');
        }

        $this->assertDatabaseCount('kyc_documents', 0);
        $this->assertSame([], Storage::disk(KycService::DISK)->allFiles());
    }

    public function test_oversized_file_is_rejected(): void
    {
        $this->save(self::details(), 'draft', ['pan' => UploadedFile::fake()->create('big.pdf', 5121, 'application/pdf')])
            ->assertSessionHasErrors(['documents.pan' => 'Each file must be 5 MB or smaller.']);

        $this->assertDatabaseCount('kyc_documents', 0);
    }

    public function test_failed_upload_is_rejected(): void
    {
        $broken = new UploadedFile(tempnam(sys_get_temp_dir(), 'kyc'), 'scan.pdf', 'application/pdf', UPLOAD_ERR_PARTIAL, true);

        $this->save(self::details(), 'draft', ['pan' => $broken])->assertSessionHasErrors('documents.pan');
        $this->assertDatabaseCount('kyc_documents', 0);
    }

    public function test_document_is_stored_privately_under_a_generated_name(): void
    {
        $this->save(self::details(), 'draft', ['pan' => self::pdf('BKLPS7319M-pan-card.pdf')])->assertSessionHasNoErrors();
        $document = $this->profile()->documents->sole();

        Storage::disk(KycService::DISK)->assertExists($document->file_path);
        $this->assertMatchesRegularExpression('#^'.$this->customer->id.'/[0-9a-f-]{36}\.pdf$#', $document->file_path);
        $this->assertStringNotContainsString('BKLPS7319M', $document->file_path); // original name never used

        // The disk is private: under storage/, not public/, with no URL and not served.
        $disk = config('filesystems.disks.'.KycService::DISK);
        $this->assertStringStartsWith(storage_path('app/private'), $disk['root']);
        $this->assertStringStartsNotWith(public_path(), $disk['root']);
        $this->assertArrayNotHasKey('url', $disk);
        $this->assertFalse($disk['serve']);
        $this->assertNotContains($disk['root'], array_values(config('filesystems.links')));
    }

    public function test_replacing_a_document_removes_the_old_file(): void
    {
        $this->save(self::details(), 'draft', ['pan' => self::pdf()]);
        $old = $this->profile()->documents->sole()->file_path;

        $this->save(self::details(), 'draft', ['pan' => self::png()]);
        $new = $this->profile()->documents->sole();

        $this->assertNotSame($old, $new->file_path);
        $this->assertSame('image/png', $new->mime_type);
        Storage::disk(KycService::DISK)->assertMissing($old);
        Storage::disk(KycService::DISK)->assertExists($new->file_path);
    }

    public function test_customer_can_view_own_document(): void
    {
        $this->save(self::details(), 'draft', ['pan' => self::pdf()]);

        $response = $this->get(route('customer.kyc.documents.show', 'pan'))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('%PDF-1.4', $response->streamedContent());

        $this->get(route('customer.kyc.documents.show', ['pan', 'download' => 1]))->assertOk()
            ->assertDownload('kyc-pan-card.pdf');
    }

    public function test_customer_cannot_view_another_customers_document(): void
    {
        $other = User::factory()->create();
        $otherProfile = KycProfile::factory()->for($other)->create();
        Storage::disk(KycService::DISK)->put($other->id.'/other.pdf', 'OTHER-CUSTOMER-SECRET');
        $otherDocument = KycDocument::factory()->for($otherProfile)->type(KycDocumentType::Pan)->create(['file_path' => $other->id.'/other.pdf', 'mime_type' => 'application/pdf']);

        $this->actingAs($this->customer, 'customer');
        $this->get(route('customer.kyc.documents.show', 'pan'))->assertNotFound();
        $this->get('/customer/kyc/documents/'.$otherDocument->id)->assertNotFound(); // ids are not accepted
        $this->get(route('admin.kyc.documents.show', $otherDocument))->assertRedirect(route('admin.login'));

        // With a document of their own, the customer still only ever gets their own file.
        $this->save(self::details(), 'draft', ['pan' => self::pdf()]);
        $this->assertStringNotContainsString('OTHER-CUSTOMER-SECRET', $this->get(route('customer.kyc.documents.show', 'pan'))->streamedContent());
    }

    // Submission & workflow --------------------------------------------------------------

    public function test_customer_can_submit_kyc_and_status_becomes_pending_review(): void
    {
        $this->submitComplete()
            ->assertRedirect(route('customer.kyc'))
            ->assertSessionHas('status', 'KYC submitted successfully. Our team will review your documents.');

        $profile = $this->profile();
        $this->assertSame(KycStatus::Submitted, $profile->kyc_status);
        $this->assertSame('pending_review', $profile->kyc_status->key());
        $this->assertNotNull($profile->submitted_at);
        $this->assertCount(2, $profile->documents);
        $this->assertSame(KycAuditAction::Submitted, $profile->auditLogs()->sole()->action);
        $this->assertSame($this->customer->id, $profile->auditLogs()->sole()->actor_id);

        $this->get(route('customer.kyc'))
            ->assertSee('Pending Review')
            ->assertSee('Your KYC is currently under review.')
            ->assertDontSee('data-kyc-cta', false);
    }

    public function test_submission_counts_documents_already_on_file(): void
    {
        $this->save(self::details(), 'draft', ['pan' => self::pdf(), 'aadhaar' => self::pdf()])->assertSessionHasNoErrors();

        $this->save(self::details(['aadhaar_last4' => '', 'pan_number' => '']), 'submit')->assertSessionHasNoErrors();
        $this->assertSame(KycStatus::Submitted, $this->profile()->kyc_status);
    }

    public function test_customer_cannot_approve_own_kyc(): void
    {
        $this->save(self::details() + [
            'kyc_status' => 'verified', 'verified_at' => now()->toDateTimeString(), 'reviewed_by' => (string) $this->customer->id,
            'rejection_reason' => 'x', 'user_id' => '999',
        ], 'submit', ['aadhaar' => self::pdf(), 'pan' => self::pdf()]);

        $profile = $this->profile();
        $this->assertSame(KycStatus::Submitted, $profile->kyc_status);
        $this->assertNull($profile->verified_at);
        $this->assertNull($profile->reviewed_by);
        $this->assertSame($this->customer->id, $profile->user_id);

        // The admin approval route is not reachable with a customer session.
        $this->post(route('admin.kyc.approve', $profile))->assertRedirect(route('admin.login'));
        $this->assertSame(KycStatus::Submitted, $profile->fresh()->kyc_status);
    }

    public function test_customer_cannot_modify_kyc_while_pending_review(): void
    {
        $this->submitComplete();
        $document = $this->profile()->documents->firstWhere('document_type', KycDocumentType::Pan);

        $this->get(route('customer.kyc.edit'))->assertRedirect(route('customer.kyc'));

        $this->save(self::details(['city' => 'Madurai', 'pan_number' => 'ZZZZZ9999Z']), 'draft', ['pan' => self::pdf()])
            ->assertSessionHasErrors(['kyc' => 'Your KYC is currently under review and cannot be changed.']);

        $profile = $this->profile();
        $this->assertSame('Chennai', $profile->city);
        $this->assertSame(self::PAN, $profile->pan_number);
        $this->assertSame($document->file_path, $profile->documents->firstWhere('document_type', KycDocumentType::Pan)->file_path);
        $this->assertCount(2, Storage::disk(KycService::DISK)->allFiles()); // the rejected upload was cleaned up
    }

    public function test_approved_kyc_cannot_be_directly_edited(): void
    {
        $this->submitComplete();
        $this->profile()->forceFill(['kyc_status' => KycStatus::Verified, 'verified_at' => now()])->save();

        $this->get(route('customer.kyc.edit'))->assertRedirect(route('customer.kyc'));
        $this->save(self::details(['city' => 'Salem']), 'submit')->assertSessionHasErrors('kyc');

        $this->assertSame('Chennai', $this->profile()->city);
        $this->assertSame(KycStatus::Verified, $this->profile()->kyc_status);
        $this->get(route('customer.kyc'))->assertSee('Your KYC has been verified.')->assertDontSee('Start KYC');
    }

    public function test_rejected_kyc_shows_reason_and_can_be_edited_and_resubmitted(): void
    {
        $this->submitComplete();
        $this->profile()->forceFill(['kyc_status' => KycStatus::Rejected, 'rejection_reason' => 'PAN document is unclear.', 'reviewed_at' => now()])->save();

        $this->get(route('customer.kyc'))->assertOk()
            ->assertSee('data-kyc-state="rejected"', false)
            ->assertSee('Your KYC needs attention.')
            ->assertSee('PAN document is unclear.')
            ->assertSee('Update &amp; Resubmit', false);

        $this->get(route('customer.kyc.edit'))->assertOk()->assertSee('PAN document is unclear.')->assertSee('Resubmit for Review');

        // Edit: replace the PAN document (draft keeps the rejected status and reason).
        $this->save(self::details(['pan_number' => '', 'aadhaar_last4' => '']), 'draft', ['pan' => self::png()])->assertSessionHasNoErrors();
        $this->assertSame(KycStatus::Rejected, $this->profile()->kyc_status);

        // Resubmit.
        $this->save(self::details(['pan_number' => '', 'aadhaar_last4' => '']), 'submit')->assertRedirect(route('customer.kyc'));
        $profile = $this->profile();
        $this->assertSame(KycStatus::Submitted, $profile->kyc_status);
        $this->assertNull($profile->rejection_reason);
        $this->assertNull($profile->reviewed_at);
        $this->assertSame('image/png', $profile->documents->firstWhere('document_type', KycDocumentType::Pan)->mime_type);
    }

    public function test_dashboard_reflects_kyc_status(): void
    {
        $this->actingAs($this->customer, 'customer');
        $expect = function (string $label, string $cta) {
            $this->get(route('customer.dashboard'))->assertOk()
                ->assertSee('data-kyc-status>'.$label, false)
                ->assertSee($cta)
                ->assertSee('href="'.route('customer.kyc').'"', false);
        };

        $expect('Not Submitted', 'Complete KYC');
        $this->submitComplete();
        $expect('Pending Review', 'View Status');
        $this->profile()->forceFill(['kyc_status' => KycStatus::Rejected, 'rejection_reason' => 'Unclear'])->save();
        $expect('Rejected', 'Update KYC');
        $this->profile()->forceFill(['kyc_status' => KycStatus::Verified])->save();
        $expect('Approved', 'View KYC');

        $this->get(route('customer.dashboard'))->assertDontSee(self::PAN)->assertDontSee('4821');
    }

    public function test_kyc_submission_writes_nothing_sensitive_to_the_log(): void
    {
        Log::spy();

        $this->submitComplete()->assertSessionHasNoErrors();
        $this->save(self::details(['aadhaar_last4' => '1234 5678 9012']));

        Log::shouldNotHaveReceived('info');
        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('error');
        Log::shouldNotHaveReceived('debug');
    }

    public function test_admin_account_has_no_customer_kyc(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'customer')->get(route('customer.kyc'))->assertForbidden();
        $this->actingAs($admin, 'web')->get(route('customer.kyc'))->assertRedirect(route('login'));
    }
}
