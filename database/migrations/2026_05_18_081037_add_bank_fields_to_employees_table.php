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
        Schema::table('employees', function (Blueprint $table) {
            $table->string('bank_name', 100)->nullable()->after('weekly_active_days');
            $table->string('bank_account', 50)->nullable()->after('bank_name');
            $table->string('bank_ifsc', 20)->nullable()->after('bank_account');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'bank_account', 'bank_ifsc']);
        });
    }
};
