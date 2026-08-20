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
            // The cash side of the bank-holiday premium when an admin has set it
            // by hand instead of letting bh_bank_percent decide. Null means the
            // percentage is still driving the split, which is why the amount is
            // stored rather than a boolean flag beside it — one column says both
            // whether there is an override and what it is.
            //
            // Only the split is ever overridden. The premium itself stays derived
            // from the holidays actually worked, so bh_bank is always the
            // remainder and the two still add up to bh_amount.
            $table->decimal('bh_cash_override', 10, 2)->nullable()->after('bh_bank');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn('bh_cash_override');
        });
    }
};
