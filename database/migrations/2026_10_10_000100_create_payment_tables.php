<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stage 6 — payments and the permanent gold ledger.
     *
     * Money is stored as integer paise and gold as DECIMAL(14,6) grams, so no
     * value ever passes through floating point. Financial rows are restricted
     * on delete: a payment or gold credit can never disappear with its owner.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique()->comment('Customer-facing payment reference');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_plan_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('installment_number');
            $table->unsignedBigInteger('amount_paise');
            $table->char('currency', 3)->default('INR');
            $table->string('status', 20)->default('created')->comment('created|pending|success|failed|cancelled');
            $table->string('payment_method', 30)->nullable();
            $table->string('gateway', 30);
            $table->string('gateway_order_id', 100)->nullable()->unique();
            $table->string('gateway_payment_id', 100)->nullable()->unique();
            $table->string('gateway_signature', 255)->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('paid_at');
            $table->index(['customer_plan_id', 'status']);
        });

        Schema::create('gold_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_plan_id')->constrained()->restrictOnDelete();
            // One gold credit per payment, enforced by the database.
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('gold_rate_id')->nullable()->constrained()->nullOnDelete()->comment('Reference only — the snapshot below is authoritative');
            $table->string('transaction_type', 20)->default('credit');
            $table->unsignedBigInteger('amount_paise');
            $table->unsignedBigInteger('rate_per_gram_paise')->comment('Rate snapshot used for this credit');
            $table->decimal('gold_grams', 14, 6);
            $table->string('purity', 5);
            $table->date('rate_date');
            $table->time('rate_time')->nullable();
            $table->timestamp('transaction_date');
            $table->string('description', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('transaction_date');
        });

        // Payment audit trail (initiated, succeeded, failed, cancelled…). IDs and events only.
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 30);
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['payment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('gold_transactions');
        Schema::dropIfExists('payments');
    }
};
