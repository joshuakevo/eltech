<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Installment chosen on a balance correction (NULL = fit the corrected balance to the
 * existing maturity). Run Loans recovers this amount; the last installment takes the rest.
 */
return new class extends Migration {
    public function up() {
        Schema::table('loan_corrections', function (Blueprint $table) {
            $table->decimal('installment_amount', 15, 2)->nullable()->after('interest_at_date');
        });
    }

    public function down() {
        Schema::table('loan_corrections', function (Blueprint $table) {
            $table->dropColumn('installment_amount');
        });
    }
};
