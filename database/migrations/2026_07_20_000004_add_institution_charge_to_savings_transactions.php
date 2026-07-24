<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('savings_transactions', 'institution_charge')) {
            return;
        }

        Schema::table('savings_transactions', function (Blueprint $table) {
            $table->decimal('institution_charge', 15, 2)->default(0)->after('charge_amount');
        });
    }

    public function down(): void
    {
        Schema::table('savings_transactions', function (Blueprint $table) {
            $table->dropColumn('institution_charge');
        });
    }
};
