<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('management_fee', 15, 2)->default(0)->after('principal');
            $table->decimal('insurance_fee', 15, 2)->default(0)->after('management_fee');
            $table->enum('fee_deduction_method', ['loan', 'savings'])->nullable()->after('insurance_fee');
            $table->foreignId('fee_savings_account_id')->nullable()->constrained('savings_accounts')->nullOnDelete()->after('fee_deduction_method');
        });
    }

    public function down()
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropForeign(['fee_savings_account_id']);
            $table->dropColumn(['management_fee', 'insurance_fee', 'fee_deduction_method', 'fee_savings_account_id']);
        });
    }
};
