<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Day-based loan interest (charged on each due date by Run Loans: principal × rate × days / 365).
 *
 *  loans.interest_accrued_to  date interest has been charged up to; NULL = loan not yet on day-based interest
 *  loans.interest_carried     charged interest not yet attached to an installment (e.g. interest owing at the
 *                             transfer date, or interest above the fixed installment carried forward)
 *  loans.installment_amount   fixed installment recovered on each due date (interest first, rest principal)
 *  loan_schedules.interest_charged  true once the installment's interest has actually been charged;
 *                             uncharged installments carry projected interest that is not yet owed
 *  loan_interest_charges      one row per charge, for the loan statement / audit
 */
return new class extends Migration {
    public function up() {
        Schema::table('loans', function (Blueprint $table) {
            $table->date('interest_accrued_to')->nullable()->after('outstanding_penalty');
            $table->decimal('interest_carried', 15, 2)->default(0)->after('interest_accrued_to');
            $table->decimal('installment_amount', 15, 2)->nullable()->after('interest_carried');
        });
        Schema::table('loan_schedules', function (Blueprint $table) {
            $table->boolean('interest_charged')->default(false)->after('interest_paid');
        });
        Schema::create('loan_interest_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_schedule_id')->nullable()->constrained('loan_schedules')->nullOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->unsignedInteger('days');
            $table->decimal('principal', 15, 2);
            $table->decimal('rate', 8, 4);
            $table->decimal('amount', 15, 2);
            $table->decimal('carried_before', 15, 2)->default(0);
            $table->decimal('carried_after', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
    public function down() {
        Schema::dropIfExists('loan_interest_charges');
        Schema::table('loan_schedules', function (Blueprint $table) {
            $table->dropColumn('interest_charged');
        });
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['interest_accrued_to', 'interest_carried', 'installment_amount']);
        });
    }
};
