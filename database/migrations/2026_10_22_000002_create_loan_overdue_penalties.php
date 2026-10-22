<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Loans past maturity and still owing are penalised once per period (each monthly due day after maturity) when less
 * than one installment was paid since the previous due day. The amount is added to the last installment's
 * penalty_charged; this table records which periods were charged (one per loan + period) for idempotency and undo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('loan_overdue_penalties')) {
            Schema::create('loan_overdue_penalties', function (Blueprint $table) {
                $table->id();
                $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
                $table->unsignedBigInteger('loan_schedule_id')->nullable();
                $table->date('period_date');
                $table->decimal('amount', 18, 2);
                $table->decimal('paid_in_period', 18, 2)->default(0);
                $table->string('source', 10);            // run | backfill
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['loan_id', 'period_date']);
            });
        }
        if (!Schema::hasColumn('loan_runs', 'overdue_penalty_id')) {
            Schema::table('loan_runs', fn (Blueprint $t) => $t->unsignedBigInteger('overdue_penalty_id')->nullable()->after('interest_charge_id'));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_overdue_penalties');
        if (Schema::hasColumn('loan_runs', 'overdue_penalty_id')) {
            Schema::table('loan_runs', fn (Blueprint $t) => $t->dropColumn('overdue_penalty_id'));
        }
    }
};
