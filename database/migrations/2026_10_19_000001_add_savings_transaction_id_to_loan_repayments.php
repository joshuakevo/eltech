<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Links a repayment paid from savings to the savings withdrawal that funded it, so removing one removes the other. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('loan_repayments', 'savings_transaction_id')) {
            Schema::table('loan_repayments', function (Blueprint $table) {
                $table->unsignedBigInteger('savings_transaction_id')->nullable()->after('transaction_id')->index();
            });
        }
    }

    public function down(): void
    {
        Schema::table('loan_repayments', fn (Blueprint $t) => $t->dropColumn('savings_transaction_id'));
    }
};
