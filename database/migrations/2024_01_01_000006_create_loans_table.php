<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_number')->unique();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('loan_product_id');
            $table->decimal('principal', 15, 2);
            $table->decimal('interest_rate', 8, 4);
            $table->enum('interest_method', ['flat', 'reducing']);
            $table->unsignedInteger('term_months');
            $table->date('disbursement_date')->nullable();
            $table->date('maturity_date')->nullable();
            $table->decimal('outstanding_principal', 15, 2)->default(0);
            $table->decimal('outstanding_interest', 15, 2)->default(0);
            $table->decimal('outstanding_penalty', 15, 2)->default(0);
            $table->enum('status', ['pending', 'active', 'closed', 'defaulted'])->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('restrict');
            $table->foreign('loan_product_id')->references('id')->on('loan_products')->onDelete('restrict');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
