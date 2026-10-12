<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('from_product_id')->nullable();
            $table->unsignedBigInteger('to_product_id');
            $table->date('start_date');
            $table->decimal('interest_rate', 8, 4);
            $table->string('interest_method', 20);
            $table->unsignedInteger('term_months');
            $table->decimal('installment_amount', 15, 2)->nullable();
            $table->decimal('principal', 15, 2);
            $table->decimal('interest', 15, 2);
            $table->unsignedBigInteger('transaction_id')->nullable();   // DR new receivable / CR Locked-Up receivable
            $table->unsignedBigInteger('correction_id')->nullable();    // schedule built through LoanCorrectionService
            $table->json('snapshot');                                   // raw loan attributes + repayment ids before activation
            $table->string('reason', 500)->nullable();
            $table->string('status', 20)->default('applied');          // applied | reversed
            $table->timestamp('reversed_at')->nullable();
            $table->unsignedBigInteger('reversed_by')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_activations');
    }
};
