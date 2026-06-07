<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->boolean('is_payment_source')->default(false)->after('is_active');
        });

        // Mark the default cash/bank/mobile accounts as payment sources
        DB::table('accounts')
            ->whereIn('account_code', ['1001', '1002', '1003'])
            ->update(['is_payment_source' => true]);
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('is_payment_source');
        });
    }
};
