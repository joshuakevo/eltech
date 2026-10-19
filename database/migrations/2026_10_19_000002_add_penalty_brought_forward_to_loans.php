<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penalties owed at the 31/07/2026 transfer (old system), kept off the books: loans.penalty_brought_forward is what is
 * still owed (collected before newer penalties, credited to penalty income when paid); penalty_bf_imported is the
 * imported figure. loan_repayments.penalty_bf_paid = the part of penalty_paid that went to it (restored on reversal).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            if (!Schema::hasColumn('loans', 'penalty_brought_forward')) {
                $table->decimal('penalty_brought_forward', 18, 2)->default(0)->after('outstanding_penalty');
            }
            if (!Schema::hasColumn('loans', 'penalty_bf_imported')) {
                $table->decimal('penalty_bf_imported', 18, 2)->nullable()->after('penalty_brought_forward');
            }
        });
        Schema::table('loan_repayments', function (Blueprint $table) {
            if (!Schema::hasColumn('loan_repayments', 'penalty_bf_paid')) {
                $table->decimal('penalty_bf_paid', 18, 2)->default(0)->after('penalty_paid');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loans', fn (Blueprint $t) => $t->dropColumn(['penalty_brought_forward', 'penalty_bf_imported']));
        Schema::table('loan_repayments', fn (Blueprint $t) => $t->dropColumn('penalty_bf_paid'));
    }
};
