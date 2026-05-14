<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            // How much bank transfer exceeded earnings this week (employer advance)
            $table->decimal('advance_given',     10, 2)->default(0)->after('bank_amount');
            // How much of a prior advance was recovered this week
            $table->decimal('advance_recovered', 10, 2)->default(0)->after('advance_given');
            // Cumulative running balance: positive = outstanding advance owed back
            $table->decimal('advance_balance',   10, 2)->default(0)->after('advance_recovered');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn(['advance_given', 'advance_recovered', 'advance_balance']);
        });
    }
};
