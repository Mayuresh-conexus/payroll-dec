<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            // The premium total, when an admin has set it by hand rather than
            // taking what the holidays worked come to. Null means the derived
            // figure still stands.
            //
            // This is a real change of policy: with a total that can be edited,
            // "double pay" is no longer guaranteed by the data, which is why an
            // overridden row has to be able to say so on the payslip.
            $table->decimal('bh_amount_override', 10, 2)->nullable()->after('bh_bank');

            // The bank side of the split when set by hand. Bank is the figure the
            // admin edits now — cash is always the remainder — so the override is
            // stored on the side that was actually typed.
            $table->decimal('bh_bank_override', 10, 2)->nullable()->after('bh_amount_override');
        });

        // Carry across any split an admin had already set. It was stored as the
        // cash side, and the same decision expressed as a bank figure is simply
        // the rest of the premium.
        $this->convert('bh_cash_override', 'bh_bank_override');

        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn('bh_cash_override');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->decimal('bh_cash_override', 10, 2)->nullable()->after('bh_bank');
        });

        $this->convert('bh_bank_override', 'bh_cash_override');

        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn(['bh_amount_override', 'bh_bank_override']);
        });
    }

    /**
     * Flip a stored override from one side of the premium to the other.
     *
     * Done row by row in PHP rather than with GREATEST(), which MySQL has and
     * SQLite does not — and the test suite migrates on SQLite.
     */
    private function convert(string $from, string $to): void
    {
        DB::table('payroll_items')
            ->whereNotNull($from)
            ->orderBy('id')
            ->select('id', 'bh_amount', $from)
            ->chunk(500, function ($rows) use ($from, $to): void {
                foreach ($rows as $row) {
                    DB::table('payroll_items')->where('id', $row->id)->update([
                        $to => max(0, (float) $row->bh_amount - (float) $row->{$from}),
                    ]);
                }
            });
    }
};
