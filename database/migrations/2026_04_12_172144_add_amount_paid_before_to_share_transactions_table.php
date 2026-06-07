<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('share_transactions', function (Blueprint $table) {
            $table->decimal('amount_paid_before', 15, 2)->nullable()->after('new_value');
        });
    }

    public function down(): void
    {
        Schema::table('share_transactions', function (Blueprint $table) {
            $table->dropColumn('amount_paid_before');
        });
    }
};
