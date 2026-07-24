<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('savings_transactions', 'charge_amount')) {
            return;
        }

        Schema::table('savings_transactions', function (Blueprint $table) {
            $table->decimal('charge_amount', 15, 2)->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('savings_transactions', function (Blueprint $table) {
            $table->dropColumn('charge_amount');
        });
    }
};
