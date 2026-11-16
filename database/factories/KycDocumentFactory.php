<?php

namespace Database\Factories;

use App\Enums\KycDocumentStatus;
use App\Enums\KycDocumentType;
use App\Models\KycDocument;
use App\Models\KycProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * user_id is copied from the KYC profile by the model, keeping both in sync.
 *
 * @extends Factory<KycDocument>
 */
class KycDocumentFactory extends Factory
{
    protected $model = KycDocument::class;

    public function definition(): array
    {
        return [
            'kyc_profile_id' => KycProfile::factory(),
            'document_type' => KycDocumentType::Pan,
            'document_number' => fake()->bothify('?????####?'),
            'file_path' => 'kyc/'.fake()->uuid().'.pdf',
            'status' => KycDocumentStatus::Pending,
            'verified_at' => null,
        ];
    }

    public function type(KycDocumentType $type): static
    {
        return $this->state(['document_type' => $type]);
    }

    public function verified(): static
    {
        return $this->state(['status' => KycDocumentStatus::Verified, 'verified_at' => now()]);
    }
}
