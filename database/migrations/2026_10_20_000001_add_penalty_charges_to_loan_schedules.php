<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Charged penalties: an installment not fully paid on its due date (Run Loans short) or paid after it is charged its
 * flat tier penalty once; it stays owed until paid (penalty_paid), even after the installment itself is paid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('loan_schedules', 'penalty_charged')) {
                $table->decimal('penalty_charged', 18, 2)->default(0)->after('interest_paid');
                $table->decimal('penalty_paid', 18, 2)->default(0)->after('penalty_charged');
                $table->date('penalty_charged_on')->nullable()->after('penalty_paid');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_schedules', fn (Blueprint $t) => $t->dropColumn(['penalty_charged', 'penalty_paid', 'penalty_charged_on']));
    }
};
