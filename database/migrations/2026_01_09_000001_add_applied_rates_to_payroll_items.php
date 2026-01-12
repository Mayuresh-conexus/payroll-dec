<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->decimal('applied_daily_rate', 12, 4)->nullable()->after('bank_amount');
            $table->decimal('applied_hourly_rate', 12, 4)->nullable()->after('applied_daily_rate');
            $table->decimal('applied_hours_per_day', 8, 2)->nullable()->after('applied_hourly_rate');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn(['applied_daily_rate', 'applied_hourly_rate', 'applied_hours_per_day']);
        });
    }
};
