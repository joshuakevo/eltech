<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->insertOrIgnore([
            [
                'key'        => 'mobile_money_enabled',
                'value'      => '0',
                'group'      => 'modules',
                'label'      => 'Mobile Money Deposits/Withdrawals (Client Portal)',
                'type'       => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key'        => 'mobile_money_min_amount',
                'value'      => '1000',
                'group'      => 'modules',
                'label'      => 'Mobile Money Minimum Amount (UGX)',
                'type'       => 'number',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')->whereIn('key', ['mobile_money_enabled', 'mobile_money_min_amount'])->delete();
    }
};
