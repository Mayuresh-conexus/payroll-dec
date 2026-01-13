<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            // store weekly base amount (not addons/overtime)
            $table->decimal('weekly_amount', 12, 2)->nullable()->after('overtime_amount');
            // store addons as JSON array of {date, amount}
            $table->json('addons')->nullable()->after('weekly_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn(['weekly_amount', 'addons']);
        });
    }
};
