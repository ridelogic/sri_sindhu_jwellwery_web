<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customer mobile number, stored normalised as the 10-digit Indian
     * national number (e.g. 9876543210). Nullable so existing users (admins,
     * development accounts) stay valid; unique for customers that have one.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('mobile_number', 15)->nullable()->unique()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['mobile_number']);
            $table->dropColumn('mobile_number');
        });
    }
};
