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
    $table->integer('total_working_days')->default(6);
    $table->integer('present_days')->default(6);
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
