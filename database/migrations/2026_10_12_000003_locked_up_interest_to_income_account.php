<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Loan products whose interest income account was set to a non-income account (the Locked-Up
 * Loans product pointed at its receivable 1104) now use 4001 Interest Income, so recovered
 * interest is income instead of reducing the receivable. Penalty income likewise → 4004.
 */
return new class extends Migration
{
    public function up(): void
    {
        $revenue = DB::table('accounts')->where('account_type', 'revenue')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $interest = DB::table('accounts')->where('account_code', '4001')->value('id');
        $penalty  = DB::table('accounts')->where('account_code', '4004')->value('id');

        foreach (DB::table('loan_products')->get(['id', 'interest_income_account_id', 'penalty_income_account_id']) as $p) {
            $set = [];
            if ($interest && $p->interest_income_account_id && !in_array((int) $p->interest_income_account_id, $revenue, true)) {
                $set['interest_income_account_id'] = $interest;
            }
            if ($penalty && $p->penalty_income_account_id && !in_array((int) $p->penalty_income_account_id, $revenue, true)) {
                $set['penalty_income_account_id'] = $penalty;
            }
            if ($set) {
                DB::table('loan_products')->where('id', $p->id)->update($set);
            }
        }
    }

    public function down(): void
    {
        // Not reversible: the previous mapping was wrong.
    }
};
