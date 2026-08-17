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
            // Paid leave taken in the week, and what it was worth. leave_amount is
            // already contained in weekly_amount/gross_amount — these columns exist
            // to show the breakdown, not to be added on top.
            $table->decimal('leave_days', 5, 2)->default(0)->after('bh_bank');
            $table->decimal('leave_hours', 7, 2)->default(0)->after('leave_days');
            $table->decimal('leave_amount', 10, 2)->default(0)->after('leave_hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn(['leave_days', 'leave_hours', 'leave_amount']);
        });
    }
};
