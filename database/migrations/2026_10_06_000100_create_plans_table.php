<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gold saving plans offered to customers (the catalogue, not enrolments).
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->string('tagline', 160)->nullable();
            $table->text('description')->nullable();
            $table->decimal('monthly_amount', 12, 2);
            $table->unsignedSmallInteger('duration_months');
            $table->decimal('total_amount', 12, 2);
            $table->json('benefits')->nullable();
            $table->text('terms')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('status')->default(true)->comment('true = active / offered publicly');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
