<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->enum('period_type', ['weekly', 'monthly'])->default('weekly')->after('week_number');
            $table->string('month', 7)->nullable()->after('period_type')->comment('YYYY-MM for monthly runs');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropColumn(['period_type', 'month']);
        });
    }
};
