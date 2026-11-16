<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-loan exception to the 31/07/2026 transfer cut-off: a loan with backdated_interest = 1 charges interest
 * and penalties from its own start (e.g. a top-up back-dated before go-live) instead of from 31/07/2026.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->boolean('backdated_interest')->default(false)->after('installment_amount');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('backdated_interest');
        });
    }
};
