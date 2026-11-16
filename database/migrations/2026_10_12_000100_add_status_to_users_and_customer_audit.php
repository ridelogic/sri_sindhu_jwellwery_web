<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin customer management.
     *
     * users.status: active | blocked (existing users become "active"). Blocking
     * only stops sign-in; schemes, payments and gold records are untouched.
     *
     * customer_audit_logs: who created / updated / blocked / unblocked a
     * customer and when. Changed FIELD NAMES only — never values or passwords.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('is_admin')->comment('active|blocked');
            $table->index('status');
        });

        Schema::create('customer_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 20)->comment('created|updated|blocked|unblocked');
            $table->json('fields')->nullable()->comment('Names of changed fields only');
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_audit_logs');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
