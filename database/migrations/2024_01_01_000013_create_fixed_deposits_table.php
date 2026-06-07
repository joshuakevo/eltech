<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_deposits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('product_id');
            $table->string('deposit_number')->unique();
            $table->decimal('principal', 15, 2);
            $table->decimal('interest_rate', 8, 4);
            $table->unsignedInteger('term_months');
            $table->date('start_date');
            $table->date('maturity_date');
            $table->decimal('interest_amount', 15, 2)->default(0);
            $table->decimal('maturity_amount', 15, 2)->default(0);
            $table->decimal('accrued_interest', 15, 2)->default(0);
            $table->enum('status', ['active', 'matured', 'closed'])->default('active');
            $table->date('closed_date')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('restrict');
            $table->foreign('product_id')->references('id')->on('fixed_deposit_products')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_deposits');
    }
};
