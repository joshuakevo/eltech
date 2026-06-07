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
        Schema::table('clients', function (Blueprint $table) {
            $table->decimal('membership_fee', 15, 2)->default(50000)->after('loan_interest');
            $table->decimal('membership_fee_paid', 15, 2)->default(0)->after('membership_fee');
            $table->enum('membership_fee_status', ['unpaid', 'partial', 'paid'])->default('unpaid')->after('membership_fee_paid');
        });
    }

    public function down()
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['membership_fee', 'membership_fee_paid', 'membership_fee_status']);
        });
    }
};
