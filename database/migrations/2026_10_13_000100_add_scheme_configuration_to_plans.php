<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plan configuration (scheme type, tenure unit, amount limits, output, bonus,
     * early exit), on the plan AND snapshotted on each customer scheme.
     *
     * ADDITIVE ONLY. Every existing row is back-filled with values that describe
     * its CURRENT behaviour exactly — nothing is recalculated:
     *
     *   scheme_type      saving
     *   contribution     monthly (one installment per calendar month, as today)
     *   tenure_unit      months, maximum_tenure = duration_months
     *   minimum/maximum  = monthly_amount (a fixed contribution, as today)
     *   output_type      gold (every payment credits gold, as today)
     *   bonus / early exit  off
     *
     * monthly_amount, duration_months, total_amount / total_commitment and
     * maturity_date keep their existing values. duration_months and the totals
     * become nullable only so NEW plans with a non-month or unlimited tenure
     * need not invent a month count.
     */
    public function up(): void
    {
        foreach (['plans', 'customer_plans'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('scheme_type', 20)->default('saving');
                $t->string('contribution_frequency', 20)->default('monthly');
                $t->string('tenure_unit', 20)->default('months');
                $t->unsignedInteger('maximum_tenure')->nullable();
                $t->decimal('minimum_amount', 12, 2)->nullable();
                $t->decimal('maximum_amount', 12, 2)->nullable()->comment('null = no upper limit');
                $t->string('output_type', 20)->default('gold');
                $t->boolean('bonus_applicable')->default(false);
                $t->string('bonus_type', 20)->nullable();
                $t->decimal('bonus_value', 15, 6)->nullable()->comment('% (2 dp), rupees (2 dp) or grams (6 dp) per bonus_type');
                $t->boolean('early_exit_allowed')->default(false);
            });
        }

        Schema::table('customer_plans', function (Blueprint $t) {
            $t->unsignedInteger('installments_total')->nullable()->comment('null = open-ended (unlimited tenure)');
        });

        // Back-fill existing rows from their own current values (no recalculation).
        DB::table('plans')->update([
            'maximum_tenure' => DB::raw('duration_months'),
            'minimum_amount' => DB::raw('monthly_amount'),
            'maximum_amount' => DB::raw('monthly_amount'),
        ]);
        DB::table('customer_plans')->update([
            'maximum_tenure' => DB::raw('duration_months'),
            'minimum_amount' => DB::raw('monthly_amount'),
            'maximum_amount' => DB::raw('monthly_amount'),
            'installments_total' => DB::raw('duration_months'),
        ]);

        // SQLite rebuilds a table to change a column; with existing rows that reference it, foreign
        // keys must be checked at commit (when the rebuilt table holds the same ids), not mid-rebuild.
        // MySQL alters the column in place and needs nothing here.
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA defer_foreign_keys = ON');
        }

        Schema::table('plans', function (Blueprint $t) {
            $t->unsignedSmallInteger('duration_months')->nullable()->change();
            $t->decimal('total_amount', 12, 2)->nullable()->change();
        });
        Schema::table('customer_plans', function (Blueprint $t) {
            $t->unsignedSmallInteger('duration_months')->nullable()->change();
            $t->decimal('total_commitment', 12, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('customer_plans', function (Blueprint $t) {
            $t->dropColumn('installments_total');
        });

        foreach (['plans', 'customer_plans'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['scheme_type', 'contribution_frequency', 'tenure_unit', 'maximum_tenure', 'minimum_amount', 'maximum_amount', 'output_type', 'bonus_applicable', 'bonus_type', 'bonus_value', 'early_exit_allowed']);
            });
        }
        // duration_months / totals stay nullable: reverting could fail for plans created with non-month tenures.
    }
};
