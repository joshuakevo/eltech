<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('accounts', 'default_institution_charge')) {
            return;
        }

        Schema::table('accounts', function (Blueprint $table) {
            $table->decimal('default_institution_charge', 15, 2)->nullable()->after('default_withdrawal_charge');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('default_institution_charge');
        });
    }
};
