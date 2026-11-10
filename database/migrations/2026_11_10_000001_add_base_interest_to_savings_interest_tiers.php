<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ordinary Savings month-end interest follows the old system's SaveRates table:
 * monthly interest = base_interest + (month-end balance − tier min) × rate ÷ 12.
 * Bases from that table: 5,000,000 → 16,667, 10,000,000 → 50,000 (lower tiers 0).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('savings_interest_tiers', 'base_interest')) {
            Schema::table('savings_interest_tiers', function (Blueprint $table) {
                $table->decimal('base_interest', 15, 2)->default(0)->after('rate');
            });
        }
        DB::table('savings_interest_tiers')->where('min_balance', 5000000)->where('base_interest', 0)->update(['base_interest' => 16667]);
        DB::table('savings_interest_tiers')->where('min_balance', 10000000)->where('base_interest', 0)->update(['base_interest' => 50000]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('savings_interest_tiers', 'base_interest')) {
            Schema::table('savings_interest_tiers', fn (Blueprint $table) => $table->dropColumn('base_interest'));
        }
    }
};
