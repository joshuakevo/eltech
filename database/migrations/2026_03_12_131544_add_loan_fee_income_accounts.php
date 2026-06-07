<?php

use App\Models\Account;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        $revenueParentId = Account::where('account_code', '4000')->value('id');

        Account::updateOrCreate(
            ['account_code' => '4009'],
            ['account_name' => 'Loan Management Fee Income', 'account_type' => 'revenue', 'parent_id' => $revenueParentId, 'is_active' => true]
        );
        Account::updateOrCreate(
            ['account_code' => '4010'],
            ['account_name' => 'Loan Insurance Fee Income', 'account_type' => 'revenue', 'parent_id' => $revenueParentId, 'is_active' => true]
        );
    }

    public function down()
    {
        Account::whereIn('account_code', ['4009', '4010'])->delete();
    }
};
