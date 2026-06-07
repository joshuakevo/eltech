<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_deposit_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('interest_rate', 8, 4); // annual
            $table->unsignedInteger('term_months');
            $table->decimal('min_amount', 15, 2)->default(0);
            $table->decimal('max_amount', 15, 2)->nullable();
            $table->unsignedBigInteger('deposit_liability_account_id')->nullable();
            $table->unsignedBigInteger('interest_expense_account_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('deposit_liability_account_id')->references('id')->on('accounts')->onDelete('set null');
            $table->foreign('interest_expense_account_id')->references('id')->on('accounts')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_deposit_products');
    }
};
