<?php

namespace Tests\Feature\Database;

use App\Enums\KycDocumentStatus;
use App\Enums\KycDocumentType;
use App\Enums\KycStatus;
use App\Models\KycDocument;
use App\Models\KycProfile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class KycTest extends TestCase
{
    use RefreshDatabase;

    public function test_kyc_profile_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $profile = KycProfile::factory()->for($user)->create();

        $this->assertTrue($profile->user->is($user));
        $this->assertTrue($user->kycProfile->is($profile));
        $this->assertSame(KycStatus::Pending, $profile->fresh()->kyc_status);
    }

    public function test_a_user_can_have_only_one_kyc_profile(): void
    {
        $user = User::factory()->create();
        KycProfile::factory()->for($user)->create();

        $this->expectException(QueryException::class);
        KycProfile::factory()->for($user)->create();
    }

    public function test_kyc_documents_belong_to_the_kyc_profile_and_user(): void
    {
        $profile = KycProfile::factory()->create();

        KycDocument::factory()->for($profile)->type(KycDocumentType::Pan)->create();
        KycDocument::factory()->for($profile)->type(KycDocumentType::Aadhaar)->create();

        $profile->refresh();

        $this->assertCount(2, $profile->documents);
        $this->assertTrue($profile->documents->every(fn (KycDocument $doc) => $doc->kycProfile->is($profile)));
        // user_id is filled from the profile automatically.
        $this->assertTrue($profile->documents->every(fn (KycDocument $doc) => $doc->user_id === $profile->user_id));
        $this->assertCount(2, $profile->user->kycDocuments);
        $this->assertSame(KycDocumentStatus::Pending, $profile->documents->first()->status);
    }

    public function test_document_cannot_belong_to_a_different_user_than_its_profile(): void
    {
        $profile = KycProfile::factory()->create();
        $otherUser = User::factory()->create();

        $this->expectException(LogicException::class);
        KycDocument::factory()->for($profile)->create(['user_id' => $otherUser->id]);
    }

    public function test_full_aadhaar_number_is_never_stored(): void
    {
        $this->assertFalse(Schema::hasColumn('kyc_profiles', 'aadhaar_number'));

        $this->expectException(InvalidArgumentException::class);
        KycProfile::factory()->create(['aadhaar_last4' => '123456789012']);
    }

    public function test_pan_and_document_numbers_are_encrypted_at_rest(): void
    {
        $profile = KycProfile::factory()->create(['pan_number' => 'abcde1234f']);
        $document = KycDocument::factory()->for($profile)->create(['document_number' => 'ABCDE1234F']);

        $this->assertSame('ABCDE1234F', $profile->fresh()->pan_number); // normalised + decrypted via the model
        $this->assertSame('XXXXXX234F', $profile->fresh()->maskedPan());

        $rawPan = DB::table('kyc_profiles')->where('id', $profile->id)->value('pan_number');
        $rawDoc = DB::table('kyc_documents')->where('id', $document->id)->value('document_number');

        $this->assertNotSame('ABCDE1234F', $rawPan);
        $this->assertStringNotContainsString('ABCDE1234F', $rawPan);
        $this->assertStringNotContainsString('ABCDE1234F', $rawDoc);
    }

    public function test_sensitive_kyc_fields_are_hidden_from_serialisation(): void
    {
        $profile = KycProfile::factory()->create(['pan_number' => 'ABCDE1234F', 'aadhaar_last4' => '4321']);
        $document = KycDocument::factory()->for($profile)->create();

        $profileArray = $profile->fresh()->toArray();
        $documentArray = $document->fresh()->toArray();

        foreach (['pan_number', 'aadhaar_last4', 'date_of_birth', 'address_line1', 'address_line2'] as $field) {
            $this->assertArrayNotHasKey($field, $profileArray);
        }
        foreach (['document_number', 'file_path'] as $field) {
            $this->assertArrayNotHasKey($field, $documentArray);
        }
        $this->assertStringNotContainsString('ABCDE1234F', $profile->fresh()->toJson());
    }

    public function test_kyc_statuses(): void
    {
        $this->assertSame(['pending', 'submitted', 'verified', 'rejected'], array_map(fn ($s) => $s->value, KycStatus::cases()));

        $profile = KycProfile::factory()->verified()->create();
        $this->assertTrue($profile->fresh()->isVerified());
        $this->assertNotNull($profile->verified_at);
    }

    public function test_deleting_a_user_removes_their_kyc_data(): void
    {
        $profile = KycProfile::factory()->create();
        KycDocument::factory()->for($profile)->create();

        $profile->user->delete();

        $this->assertDatabaseCount('kyc_profiles', 0);
        $this->assertDatabaseCount('kyc_documents', 0);
    }
}
