<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->string('transfer_id')->nullable()->after('bank_amount');
            $table->date('transfer_date')->nullable()->after('transfer_id');
            $table->enum('transfer_status', ['pending', 'completed', 'failed'])->default('pending')->after('transfer_date');
            $table->text('note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn(['transfer_id', 'transfer_date', 'transfer_status']);
        });
    }
};
