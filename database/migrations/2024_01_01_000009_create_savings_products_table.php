<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('minimum_balance', 15, 2)->default(0);
            $table->decimal('withdrawal_fee', 15, 2)->default(0);
            $table->decimal('interest_rate', 8, 4)->default(0); // annual
            $table->enum('interest_frequency', ['daily', 'monthly', 'quarterly', 'annually'])->default('monthly');
            $table->unsignedBigInteger('savings_liability_account_id')->nullable();
            $table->unsignedBigInteger('interest_expense_account_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('savings_liability_account_id')->references('id')->on('accounts')->onDelete('set null');
            $table->foreign('interest_expense_account_id')->references('id')->on('accounts')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_products');
    }
};
