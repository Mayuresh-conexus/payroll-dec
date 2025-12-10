<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('employee_code')->unique();
            $table->string('name');
            $table->date('joining_date')->nullable();
            $table->string('department')->nullable();
            $table->enum('type', ['daily_rate', 'hourly'])->default('daily_rate');
            $table->decimal('daily_rate', 10, 2)->nullable()->comment('For CTC employees');
            $table->decimal('hourly_rate', 10, 2)->nullable()->comment('For hourly employees');
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};