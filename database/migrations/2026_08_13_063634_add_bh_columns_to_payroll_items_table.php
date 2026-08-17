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
        Schema::table('payroll_items', function (Blueprint $table) {
            // The premium earned for working a bank holiday, and how it splits.
            // bh_amount is already contained in weekly_amount/gross_amount — these
            // columns exist to show the breakdown, not to be added on top.
            $table->decimal('bh_amount', 10, 2)->default(0)->after('overtime_amount');
            $table->decimal('bh_cash', 10, 2)->default(0)->after('bh_amount');
            $table->decimal('bh_bank', 10, 2)->default(0)->after('bh_cash');

            // Snapshot of the employee's split at the time the week was calculated,
            // so changing the percentage later cannot rewrite historical payslips.
            $table->decimal('applied_bh_bank_percent', 5, 2)->nullable()->after('applied_hours_per_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn(['bh_amount', 'bh_cash', 'bh_bank', 'applied_bh_bank_percent']);
        });
    }
};
