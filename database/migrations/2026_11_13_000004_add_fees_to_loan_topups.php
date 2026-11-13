<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Top-ups charge the same fees as a new loan (application, management, insurance), from the loan or from savings. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('loan_topups', 'application_fee')) {
            return;
        }
        Schema::table('loan_topups', function (Blueprint $table) {
            $table->decimal('application_fee', 18, 2)->default(0)->after('installment_amount');
            $table->string('application_fee_method', 10)->default('loan')->after('application_fee');
            $table->decimal('management_fee_rate', 8, 4)->default(0)->after('application_fee_method');
            $table->decimal('management_fee', 18, 2)->default(0)->after('management_fee_rate');
            $table->string('management_fee_method', 10)->default('loan')->after('management_fee');
            $table->decimal('insurance_fee_rate', 8, 4)->default(0)->after('management_fee_method');
            $table->decimal('insurance_fee', 18, 2)->default(0)->after('insurance_fee_rate');
            $table->string('insurance_fee_method', 10)->default('loan')->after('insurance_fee');
            $table->decimal('net_payout', 18, 2)->nullable()->after('insurance_fee_method');          // amount − fees taken from the loan
            $table->unsignedBigInteger('fee_transaction_id')->nullable()->after('transaction_id');     // journal for fees from savings
            $table->unsignedBigInteger('fee_savings_transaction_id')->nullable()->after('savings_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('loan_topups', fn (Blueprint $t) => $t->dropColumn(['application_fee', 'application_fee_method', 'management_fee_rate',
            'management_fee', 'management_fee_method', 'insurance_fee_rate', 'insurance_fee', 'insurance_fee_method', 'net_payout',
            'fee_transaction_id', 'fee_savings_transaction_id']));
    }
};
