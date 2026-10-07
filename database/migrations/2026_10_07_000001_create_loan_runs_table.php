<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per Run Loans step (charge interest for an installment + recover from savings).
 * Records everything the step created and a snapshot of the loan + schedule from before it,
 * so Undo can remove it completely.
 */
return new class extends Migration {
    public function up() {
        Schema::create('loan_runs', function (Blueprint $table) {
            $table->id();
            $table->string('batch', 40)->index();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->date('run_date');
            $table->date('due_date');
            $table->unsignedBigInteger('loan_schedule_id')->nullable();
            $table->unsignedBigInteger('interest_charge_id')->nullable();
            $table->unsignedBigInteger('repayment_id')->nullable();
            $table->unsignedBigInteger('repayment_transaction_id')->nullable();
            $table->unsignedBigInteger('savings_transaction_id')->nullable();
            $table->unsignedBigInteger('withdrawal_transaction_id')->nullable();
            $table->unsignedBigInteger('savings_account_id')->nullable();
            $table->decimal('accrued', 15, 2)->default(0);
            $table->decimal('recovered', 15, 2)->default(0);
            $table->json('snapshot');
            $table->enum('status', ['applied', 'undone'])->default('applied');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('undone_at')->nullable();
            $table->foreignId('undone_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['loan_id', 'status']);
        });
    }
    public function down() {
        Schema::dropIfExists('loan_runs');
    }
};
