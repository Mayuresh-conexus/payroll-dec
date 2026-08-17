<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Share of a bank-holiday premium paid to bank; the remainder is cash.
            // Defaults to 0 so existing staff take the whole premium as cash until
            // someone sets a split for them.
            $table->decimal('bh_bank_percent', 5, 2)->default(0)->after('bank_transfer_fix_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('bh_bank_percent');
        });
    }
};
