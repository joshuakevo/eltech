<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->insertOrIgnore([
            'key'        => 'membership_fee_module_enabled',
            'value'      => '1',
            'group'      => 'modules',
            'label'      => 'Membership Fee Module',
            'type'       => 'boolean',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')->where('key', 'membership_fee_module_enabled')->delete();
    }
};
