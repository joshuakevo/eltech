<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * repayment_frequency was ENUM('monthly','quarterly'); make it a short string so new
 * intervals (e.g. every_4_months — see Loan::FREQUENCIES) can be stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['loan_products', 'loans'] as $table) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `repayment_frequency` VARCHAR(20) NOT NULL DEFAULT 'monthly'");
        }
    }

    public function down(): void
    {
        foreach (['loan_products', 'loans'] as $table) {
            DB::table($table)->whereNotIn('repayment_frequency', ['monthly', 'quarterly'])->update(['repayment_frequency' => 'monthly']);
            DB::statement("ALTER TABLE `{$table}` MODIFY `repayment_frequency` ENUM('monthly','quarterly') NOT NULL DEFAULT 'monthly'");
        }
    }
};
