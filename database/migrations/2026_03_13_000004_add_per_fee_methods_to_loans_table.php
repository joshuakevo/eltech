<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->enum('application_fee_method', ['loan', 'savings'])->default('loan')->after('application_fee_rate');
            $table->enum('management_fee_method', ['loan', 'savings'])->default('loan')->after('management_fee_rate');
            $table->enum('insurance_fee_method', ['loan', 'savings'])->default('loan')->after('insurance_fee_rate');
        });
    }

    public function down()
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['application_fee_method', 'management_fee_method', 'insurance_fee_method']);
        });
    }
};
