<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A customer's enrolment in a plan. One user may hold many customer plans
     * (including the same plan more than once), so plan_id is never stored on users.
     *
     * Amounts are snapshotted from the plan at enrolment so later plan edits
     * never change an existing customer's commitment.
     */
    public function up(): void
    {
        Schema::create('customer_plans', function (Blueprint $table) {
            $table->id();
            // restrict: financial records must never disappear because a user or plan was deleted.
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('scheme_number', 30)->unique();
            $table->date('start_date');
            $table->date('maturity_date')->nullable();
            $table->decimal('monthly_amount', 12, 2);
            $table->unsignedSmallInteger('duration_months');
            $table->decimal('total_commitment', 12, 2);
            $table->string('status', 20)->default('pending')->comment('pending|active|completed|cancelled|matured');
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_plans');
    }
};
