<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');

            $table->enum('type', ['daily_rate', 'hourly']);

            $table->integer('present_days')->nullable();
            $table->integer('total_days')->nullable();

            $table->decimal('total_hours', 8, 2)->nullable();
            $table->decimal('overtime_hours', 8, 2)->nullable();

            $table->decimal('gross_amount', 10, 2)->default(0);
            $table->decimal('cash_amount', 10, 2)->default(0);
            $table->decimal('bank_amount', 10, 2)->default(0);

            $table->boolean('is_paid')->default(false);

            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};