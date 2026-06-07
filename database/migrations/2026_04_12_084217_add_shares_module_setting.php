<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->insertOrIgnore([
            'key'        => 'shares_module_enabled',
            'value'      => '1',
            'group'      => 'modules',
            'label'      => 'Member Shares Module',
            'type'       => 'boolean',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')->where('key', 'shares_module_enabled')->delete();
    }
};
