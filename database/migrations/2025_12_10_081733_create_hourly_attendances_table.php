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
        Schema::create('hourly_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->integer('year');
            $table->integer('week_number');

            // Map storing hours per day keys: mon,tue,wed,thu,fri,sat,sun
            $table->json('hours_map')->nullable()->comment('hours per day map (mon..sun)');

            // Map storing OT per day
            $table->json('ot_map')->nullable()->comment('overtime per day map (mon..sun)');

            // Keep summary columns for convenience
            $table->decimal('total_hours', 8, 2)->default(0);
            $table->decimal('overtime_hours', 8, 2)->default(0);
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
        Schema::dropIfExists('hourly_attendances');
    }
};
