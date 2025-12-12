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
        Schema::create('daily_rate_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->integer('year');
            $table->integer('week_number');
            $table->json('days_map')->nullable();
            $table->json('overtime_map')->nullable()->comment('overtime per day map (mon..sun)');
            $table->integer('total_working_days')->default(6);
            $table->integer('present_days')->default(6);
            $table->decimal('overtime_amount', 8, 2)->default(0)->comment('sum of overtime_map');
            $table->boolean('locked')->default(false);
            $table->timestamps();

            $table->unique(['employee_id', 'year', 'week_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_rate_attendances');
    }
};
