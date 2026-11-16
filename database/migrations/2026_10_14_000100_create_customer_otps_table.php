<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One-time passwords for customer sign-in and registration (additive).
     *
     * The OTP itself is never stored: only an HMAC-SHA256 of it (keyed with the
     * application key and bound to the mobile number and purpose). A row is
     * usable once — consumed_at / invalidated_at end it — and only until
     * expires_at, with a limited number of verification attempts.
     */
    public function up(): void
    {
        Schema::create('customer_otps', function (Blueprint $table) {
            $table->id();
            $table->string('mobile_number', 10);
            $table->string('purpose', 20)->comment('login | register');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->char('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['mobile_number', 'purpose', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_otps');
    }
};
