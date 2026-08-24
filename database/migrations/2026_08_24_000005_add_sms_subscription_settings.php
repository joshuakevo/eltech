<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->insertOrIgnore([
            [
                'key'        => 'sms_subscription_price',
                'value'      => '100000',
                'group'      => 'modules',
                'label'      => 'SMS Subscription Price (UGX/month)',
                'type'       => 'number',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key'        => 'sms_free_trial_count',
                'value'      => '5',
                'group'      => 'modules',
                'label'      => 'Free Trial SMS Count',
                'type'       => 'number',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')->whereIn('key', ['sms_subscription_price', 'sms_free_trial_count'])->delete();
    }
};
