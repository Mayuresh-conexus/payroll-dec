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
        Schema::create('leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            // A single day of leave stores the same date in both columns, so every
            // lookup can treat leave as a range without special-casing.
            $table->date('start_date');
            $table->date('end_date');

            // Hours paid for each leave day, for hourly staff only. Snapshotted from
            // the employee's hours_per_day when the leave is entered so a later change
            // to their standard day cannot rewrite leave already taken. Null for
            // daily-rate staff, who are paid whole days.
            $table->decimal('hours_per_day', 5, 2)->nullable();

            $table->string('reason')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['employee_id', 'start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leaves');
    }
};
