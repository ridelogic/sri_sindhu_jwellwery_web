<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stage 5 (KYC workflow). Adds only the fields the existing KYC tables lack.
     * The existing status values are kept: pending (not submitted / draft),
     * submitted (pending review), verified (approved), rejected.
     *
     * Document metadata deliberately excludes the customer's original filename:
     * filenames often contain identity numbers.
     */
    public function up(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table) {
            $table->string('full_name', 100)->nullable()->after('user_id')->comment('Name as on identity documents');
            $table->timestamp('submitted_at')->nullable()->after('kyc_status');
            $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->string('rejection_reason', 500)->nullable()->after('reviewed_by');
        });

        Schema::table('kyc_documents', function (Blueprint $table) {
            $table->string('mime_type', 100)->nullable()->after('file_path');
            $table->unsignedInteger('size_bytes')->nullable()->after('mime_type');
        });

        // Minimal audit trail: who did what, when. IDs only — never identity data.
        Schema::create('kyc_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kyc_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 20)->comment('submitted|approved|rejected');
            $table->string('note', 500)->nullable()->comment('Rejection reason shown to the customer');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['kyc_profile_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_audit_logs');

        Schema::table('kyc_documents', function (Blueprint $table) {
            $table->dropColumn(['mime_type', 'size_bytes']);
        });

        Schema::table('kyc_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['full_name', 'submitted_at', 'reviewed_at', 'rejection_reason']);
        });
    }
};
