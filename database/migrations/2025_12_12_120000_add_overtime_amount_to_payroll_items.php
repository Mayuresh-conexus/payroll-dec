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
        if (Schema::hasTable('payroll_items')) {
            Schema::table('payroll_items', function (Blueprint $table) {
                if (! Schema::hasColumn('payroll_items', 'overtime_amount')) {
                    $table->decimal('overtime_amount', 8, 2)->nullable()->after('overtime_hours')->comment('overtime amount for daily-rate rows');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('payroll_items')) {
            Schema::table('payroll_items', function (Blueprint $table) {
                if (Schema::hasColumn('payroll_items', 'overtime_amount')) {
                    $table->dropColumn('overtime_amount');
                }
            });
        }
    }
};
