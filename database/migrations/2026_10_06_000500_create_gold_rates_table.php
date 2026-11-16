<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only gold rate history.
     *
     * Rows are never overwritten or deleted (enforced in App\Models\GoldRate):
     * every rate change is a NEW row, because future payment calculations must
     * use the rate that applied at the moment of payment. A mistaken rate is
     * corrected by deactivating it (status = false) and inserting a new row.
     */
    public function up(): void
    {
        Schema::create('gold_rates', function (Blueprint $table) {
            $table->id();
            $table->date('rate_date');
            $table->time('rate_time')->nullable();
            $table->string('purity', 5)->comment('22K|24K');
            $table->decimal('rate_per_gram', 12, 2);
            $table->decimal('rate_per_10_gram', 12, 2);
            $table->string('source', 50)->nullable()->comment('e.g. manual, development-seed');
            $table->boolean('status')->default(true)->comment('true = active');
            $table->timestamps();

            $table->index('rate_date');
            $table->index('purity');
            $table->index('status');
            // Serves "latest active rate for a purity" without a table scan.
            $table->index(['purity', 'status', 'rate_date', 'rate_time'], 'gold_rates_latest_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gold_rates');
    }
};
