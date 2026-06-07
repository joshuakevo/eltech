<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('interest_rate', 8, 4); // annual percentage
            $table->enum('interest_method', ['flat', 'reducing']);
            $table->unsignedInteger('term_months');
            $table->decimal('penalty_rate', 8, 4)->default(0); // per day on overdue
            $table->decimal('min_amount', 15, 2)->default(0);
            $table->decimal('max_amount', 15, 2)->nullable();
            $table->unsignedBigInteger('receivable_account_id')->nullable();
            $table->unsignedBigInteger('interest_income_account_id')->nullable();
            $table->unsignedBigInteger('penalty_income_account_id')->nullable();
            $table->unsignedBigInteger('disbursement_account_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('receivable_account_id')->references('id')->on('accounts')->onDelete('set null');
            $table->foreign('interest_income_account_id')->references('id')->on('accounts')->onDelete('set null');
            $table->foreign('penalty_income_account_id')->references('id')->on('accounts')->onDelete('set null');
            $table->foreign('disbursement_account_id')->references('id')->on('accounts')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_products');
    }
};
