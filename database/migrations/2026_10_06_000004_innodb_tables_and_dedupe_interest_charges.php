<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * 1. Converts every non-InnoDB table to InnoDB. Tables created while the server's default
 *    engine was MyISAM ignore transactions, so Run Loans' preview (a run that is rolled back)
 *    left its loan_interest_charges rows behind.
 * 2. Removes those leftover charge rows: keeps exactly one row per installment whose interest
 *    really was charged, and drops rows for installments/loans never actually charged.
 * 3. A unique index on loan_schedule_id so an installment's charge can never be logged twice.
 */
return new class extends Migration {
    public function up() {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $tables = DB::select("SELECT TABLE_NAME AS t FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' AND ENGINE <> 'InnoDB'");
        foreach ($tables as $row) {
            try {
                DB::statement('ALTER TABLE `' . str_replace('`', '', $row->t) . '` ENGINE=InnoDB');
            } catch (\Throwable $e) {
                Log::warning("InnoDB conversion failed for {$row->t}: " . $e->getMessage());
            }
        }

        if (!Schema::hasTable('loan_interest_charges')) {
            return;
        }

        // Rows for loans never run, or installments not actually charged, are preview leftovers.
        DB::statement("DELETE ic FROM loan_interest_charges ic
            LEFT JOIN loans l ON l.id = ic.loan_id
            LEFT JOIN loan_schedules s ON s.id = ic.loan_schedule_id
            WHERE l.id IS NULL OR l.interest_accrued_to IS NULL OR s.id IS NULL OR s.interest_charged = 0
               OR ic.to_date > l.interest_accrued_to");

        // Keep one row per charged installment (the latest -- the real run comes after its previews).
        DB::statement("DELETE ic FROM loan_interest_charges ic
            JOIN (SELECT loan_schedule_id, MAX(id) AS keep_id FROM loan_interest_charges
                  WHERE loan_schedule_id IS NOT NULL GROUP BY loan_schedule_id HAVING COUNT(*) > 1) d
              ON d.loan_schedule_id = ic.loan_schedule_id AND ic.id <> d.keep_id");

        Schema::table('loan_interest_charges', function (Blueprint $table) {
            $table->unique('loan_schedule_id');
        });
    }

    public function down() {
        if (Schema::hasTable('loan_interest_charges')) {
            Schema::table('loan_interest_charges', function (Blueprint $table) {
                $table->dropUnique(['loan_schedule_id']);
            });
        }
    }
};
