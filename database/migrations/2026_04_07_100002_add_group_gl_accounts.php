<?php

use App\Models\Account;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $parentLiab = Account::where('account_code', '2000')->first();
        $parentExp  = Account::where('account_code', '5000')->first();

        Account::firstOrCreate(
            ['account_code' => '2005'],
            [
                'account_name' => 'Group Member Savings',
                'account_type' => 'liability',
                'parent_id'    => $parentLiab?->id,
                'is_active'    => true,
                'description'  => 'Liability for group member sub-balances',
            ]
        );

        Account::firstOrCreate(
            ['account_code' => '5010'],
            [
                'account_name' => 'Interest Expense — Groups',
                'account_type' => 'expense',
                'parent_id'    => $parentExp?->id,
                'is_active'    => true,
                'description'  => 'Interest credited to group member balances',
            ]
        );
    }

    public function down(): void
    {
        Account::whereIn('account_code', ['2005', '5010'])->delete();
    }
};
