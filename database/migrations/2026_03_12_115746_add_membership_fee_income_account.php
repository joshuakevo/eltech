<?php

use App\Models\Account;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        $revenue = Account::where('account_code', '4000')->first();
        Account::updateOrCreate(
            ['account_code' => '4008'],
            [
                'account_name' => 'Membership Fee Income',
                'account_type' => 'revenue',
                'parent_id'    => $revenue?->id,
                'is_active'    => true,
            ]
        );
    }

    public function down()
    {
        Account::where('account_code', '4008')->delete();
    }
};
