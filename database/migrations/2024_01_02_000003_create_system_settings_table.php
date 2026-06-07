<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group')->default('general');
            $table->string('label')->nullable();
            $table->string('type')->default('text'); // text, number, boolean, select, textarea
            $table->timestamps();
        });

        // Insert default settings
        $defaults = [
            ['key' => 'org_name',        'value' => 'ElTech Finance Ltd',      'group' => 'general',   'label' => 'Organisation Name',    'type' => 'text'],
            ['key' => 'org_address',     'value' => '123 Finance Street',      'group' => 'general',   'label' => 'Address',              'type' => 'textarea'],
            ['key' => 'org_phone',       'value' => '+254 700 000 000',        'group' => 'general',   'label' => 'Phone',                'type' => 'text'],
            ['key' => 'org_email',       'value' => 'info@eltechfinance.com',  'group' => 'general',   'label' => 'Email',                'type' => 'text'],
            ['key' => 'currency',        'value' => 'KES',                     'group' => 'general',   'label' => 'Currency Code',        'type' => 'text'],
            ['key' => 'currency_symbol', 'value' => 'KES',                     'group' => 'general',   'label' => 'Currency Symbol',      'type' => 'text'],
            ['key' => 'decimal_places',  'value' => '2',                       'group' => 'general',   'label' => 'Decimal Places',       'type' => 'number'],
            ['key' => 'financial_year_start', 'value' => '01-01',             'group' => 'general',   'label' => 'Financial Year Start (MM-DD)', 'type' => 'text'],
            ['key' => 'penalty_grace_days', 'value' => '0',                   'group' => 'loans',     'label' => 'Penalty Grace Days',   'type' => 'number'],
            ['key' => 'backup_path',     'value' => 'backups',                 'group' => 'system',    'label' => 'Backup Storage Path',  'type' => 'text'],
            ['key' => 'max_loan_per_client', 'value' => '3',                  'group' => 'loans',     'label' => 'Max Active Loans Per Client', 'type' => 'number'],
        ];

        foreach ($defaults as $s) {
            \DB::table('system_settings')->insert(array_merge($s, [
                'created_at' => now(), 'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
