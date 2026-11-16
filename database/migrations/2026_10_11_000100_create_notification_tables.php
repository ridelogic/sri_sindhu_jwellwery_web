<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stage 7 — notifications.
     *
     * notifications: Laravel's standard database-notification table (so
     * $user->notifications / unreadNotifications / markAsRead work), plus a
     * dedupe_key. The unique (notifiable, dedupe_key) index means a repeated
     * event (webhook retry, re-run job) can never notify the same user twice,
     * while every new event — with its own key — still notifies.
     *
     * Notification data never contains PAN, Aadhaar, tokens, gateway secrets
     * or signatures.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 50);
            $table->morphs('notifiable');
            $table->text('data');
            $table->string('dedupe_key', 150)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['notifiable_type', 'notifiable_id', 'dedupe_key'], 'notifications_dedupe_unique');
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_unread_lookup');
            $table->index('created_at');
        });

        // One row per user; a missing row means "defaults".
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('gold_rate_updates')->default(true);
            $table->boolean('payment_updates')->default(true);
            $table->boolean('kyc_updates')->default(true);
            $table->boolean('promotional_notifications')->default(false);
            $table->timestamps();
        });

        // Push device tokens. The token is encrypted at rest; token_hash (SHA-256) enforces uniqueness.
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('token')->comment('Encrypted');
            $table->char('token_hash', 64)->unique();
            $table->string('platform', 10)->comment('android|ios|web');
            $table->string('device_name', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });

        // Push delivery tracking per notification per device.
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('notification_id')->nullable()->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_token_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20)->default('push');
            $table->string('provider', 20);
            $table->string('status', 20)->comment('queued|sent|failed|not_configured');
            $table->string('error', 255)->nullable()->comment('Safe summary only — never tokens or credentials');
            $table->timestamp('attempted_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->unique(['notification_id', 'device_token_id'], 'notification_deliveries_once');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('device_tokens');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
    }
};
