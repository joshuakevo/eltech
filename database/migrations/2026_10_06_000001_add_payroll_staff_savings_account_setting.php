<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->insertOrIgnore([
            'key'        => 'payroll_staff_savings_account',
            'value'      => 'SA-SK00037',
            'group'      => 'financial',
            'label'      => 'Payroll — Staff Savings account number (staff savings deducted from pay are deposited here)',
            'type'       => 'text',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')->where('key', 'payroll_staff_savings_account')->delete();
    }
};
