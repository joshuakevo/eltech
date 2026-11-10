<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ordinary Savings interest is accrued to 2006 during the year and credited into savings on 31 December,
 * so a run line's interest and the amount put on the member's statement can differ.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('savings_interest_run_lines', 'credited_to_savings')) {
            Schema::table('savings_interest_run_lines', function (Blueprint $table) {
                $table->decimal('credited_to_savings', 18, 2)->default(0)->after('interest');
            });
            // Earlier runs credited their interest straight into savings
            DB::table('savings_interest_run_lines')->update(['credited_to_savings' => DB::raw('interest')]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('savings_interest_run_lines', 'credited_to_savings')) {
            Schema::table('savings_interest_run_lines', fn (Blueprint $table) => $table->dropColumn('credited_to_savings'));
        }
    }
};
