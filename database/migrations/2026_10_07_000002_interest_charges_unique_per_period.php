<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A matured (or still-overdue) loan keeps being charged interest after its last installment, so
 * one installment can carry several charges -- one per period. Unique per installment + period end
 * still blocks a period from being logged twice.
 */
return new class extends Migration {
    public function up() {
        Schema::table('loan_interest_charges', function (Blueprint $table) {
            $table->unique(['loan_schedule_id', 'to_date'], 'lic_schedule_period_unique');
        });
        Schema::table('loan_interest_charges', function (Blueprint $table) {
            $table->dropUnique('loan_interest_charges_loan_schedule_id_unique');
        });
    }
    public function down() {
        Schema::table('loan_interest_charges', function (Blueprint $table) {
            $table->unique('loan_schedule_id');
            $table->dropUnique('lic_schedule_period_unique');
        });
    }
};
