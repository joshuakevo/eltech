<?php

use App\Models\Account;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        $liabilityParentId = Account::where('account_code', '2000')->value('id');

        Account::updateOrCreate(
            ['account_code' => '2010'],
            [
                'account_name' => 'Loan Insurance Payable',
                'account_type' => 'liability',
                'parent_id'    => $liabilityParentId,
                'is_active'    => true,
            ]
        );
    }

    public function down()
    {
        Account::where('account_code', '2010')->delete();
    }
};
