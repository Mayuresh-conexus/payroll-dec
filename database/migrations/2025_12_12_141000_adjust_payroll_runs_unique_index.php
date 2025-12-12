<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Ensure existing rows have a period_type to avoid unique index issues
        DB::table('payroll_runs')->whereNull('period_type')->update(['period_type' => 'weekly']);

        Schema::table('payroll_runs', function (Blueprint $table) {
            // drop old unique index on year+week_number
            $table->dropUnique(['year', 'week_number']);

            // add new unique index including period_type
            $table->unique(['year', 'week_number', 'period_type']);
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropUnique(['year', 'week_number', 'period_type']);
            $table->unique(['year', 'week_number']);
        });
    }
};
