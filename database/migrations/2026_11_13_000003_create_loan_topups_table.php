<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Loan top-ups: more money lent on a running loan, which then restarts on new terms
 * (rate, method, period) with principal = balance + top-up. See LoanTopUpService.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('loan_topups')) {
            return;
        }
        Schema::create('loan_topups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->date('topup_date');
            $table->decimal('amount', 18, 2);                       // new money lent
            $table->decimal('balance_before', 18, 2);               // outstanding principal before
            $table->decimal('interest_owed', 18, 2)->default(0);    // interest owed carried into the new terms
            $table->decimal('penalty_carried', 18, 2)->default(0);  // unpaid charged penalties carried to installment 1
            $table->decimal('new_principal', 18, 2);
            $table->decimal('interest_rate', 9, 4);
            $table->string('interest_method', 10);
            $table->unsignedSmallInteger('term_months');
            $table->decimal('installment_amount', 18, 2)->nullable();
            $table->string('payout', 10);                           // account | savings
            $table->unsignedBigInteger('payout_account_id')->nullable();
            $table->unsignedBigInteger('savings_account_id')->nullable();
            $table->unsignedBigInteger('savings_transaction_id')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable(); // journal (module loan_topup)
            $table->unsignedBigInteger('correction_id')->nullable();  // schedule rebuild
            $table->json('snapshot');
            $table->string('reason', 500);
            $table->string('status', 10)->default('applied');        // applied | reversed
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_topups');
    }
};
