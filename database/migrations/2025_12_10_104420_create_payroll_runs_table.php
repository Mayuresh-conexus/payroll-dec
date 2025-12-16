<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();

            $table->enum('period_type', ['weekly', 'monthly'])->default('weekly');

            $table->integer('year');
            $table->integer('week_number')->default(0); // weekly uses 1..53, monthly can stay 0
            $table->string('month', 7)->nullable()->comment('YYYY-MM for monthly runs');

            $table->enum('status', ['draft', 'final'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            // weekly uniqueness
            $table->unique(['period_type', 'year', 'week_number'], 'payroll_runs_weekly_unique');

            // monthly uniqueness
            $table->unique(['period_type', 'month'], 'payroll_runs_monthly_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_runs');
    }
};
