<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * KYC documents attached to a KYC profile. Upload UI comes in a later stage.
     * document_number is a TEXT column because it is encrypted at rest by the model.
     */
    public function up(): void
    {
        Schema::create('kyc_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kyc_profile_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 30)->comment('pan|aadhaar|passport|voter_id|driving_licence|address_proof|photo');
            $table->text('document_number')->nullable()->comment('Encrypted');
            $table->string('file_path')->nullable()->comment('Private storage path — never publicly served');
            $table->string('status', 20)->default('pending')->comment('pending|verified|rejected');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['kyc_profile_id', 'document_type']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_documents');
    }
};
