<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One KYC profile per user.
     *
     * Privacy: the full Aadhaar number is NEVER stored — only the last 4 digits.
     * pan_number is a TEXT column because it is encrypted at rest by the model.
     */
    public function up(): void
    {
        Schema::create('kyc_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('pincode', 10)->nullable();
            $table->string('country', 100)->nullable()->default('India');
            $table->text('pan_number')->nullable()->comment('Encrypted');
            $table->char('aadhaar_last4', 4)->nullable();
            $table->string('kyc_status', 20)->default('pending')->comment('pending|submitted|verified|rejected');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index('kyc_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_profiles');
    }
};
