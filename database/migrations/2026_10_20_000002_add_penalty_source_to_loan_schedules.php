<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Where an installment's penalty came from: 'run' (Run Loans short), 'late' (paid after the due date), 'backfill' (31/07/2026 → go-live catch-up, can be undone). */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('loan_schedules', 'penalty_source')) {
            Schema::table('loan_schedules', fn (Blueprint $t) => $t->string('penalty_source', 10)->nullable()->after('penalty_charged_on'));
        }
    }

    public function down(): void
    {
        Schema::table('loan_schedules', fn (Blueprint $t) => $t->dropColumn('penalty_source'));
    }
};
