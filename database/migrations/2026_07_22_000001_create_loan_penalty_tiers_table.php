<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('loan_penalty_tiers')) {
            return;
        }

        Schema::create('loan_penalty_tiers', function (Blueprint $table) {
            $table->id();
            $table->decimal('min_installment', 15, 2);
            $table->decimal('max_installment', 15, 2)->nullable(); // null = "and above"
            $table->decimal('penalty_amount', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_penalty_tiers');
    }
};
