<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which savings account Run Loans recovers installments from, per member.
 * NULL = automatic (the active account with the most available); set = that account only
 * (e.g. Ordinary Savings, so the Interest Wallet is never touched).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('clients', 'loan_recovery_savings_account_id')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->foreignId('loan_recovery_savings_account_id')->nullable()->after('loan_recovery_uses_minimum')
                    ->constrained('savings_accounts')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('clients', 'loan_recovery_savings_account_id')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropConstrainedForeignId('loan_recovery_savings_account_id');
            });
        }
    }
};
