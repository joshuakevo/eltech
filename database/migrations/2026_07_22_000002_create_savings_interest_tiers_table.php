<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('savings_interest_tiers')) {
            return;
        }

        Schema::create('savings_interest_tiers', function (Blueprint $table) {
            $table->id();
            $table->decimal('min_balance', 15, 2);
            $table->decimal('max_balance', 15, 2)->nullable(); // null = "and above"
            $table->decimal('rate', 5, 2); // annual %, applied to the portion of balance within this band
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_interest_tiers');
    }
};
