<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_deposits', function (Blueprint $table) {
            $table->string('payment_source')->nullable()->after('savings_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('fixed_deposits', function (Blueprint $table) {
            $table->dropColumn('payment_source');
        });
    }
};
