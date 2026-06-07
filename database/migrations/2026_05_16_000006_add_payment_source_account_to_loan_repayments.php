<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_source_account_id')->nullable()->after('payment_method');
            $table->foreign('payment_source_account_id')->references('id')->on('accounts')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->dropForeign(['payment_source_account_id']);
            $table->dropColumn('payment_source_account_id');
        });
    }
};
