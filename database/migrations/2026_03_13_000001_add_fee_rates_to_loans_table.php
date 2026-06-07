<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('management_fee_rate', 5, 2)->default(1.50)->after('management_fee');
            $table->decimal('insurance_fee_rate', 5, 2)->default(1.50)->after('insurance_fee');
        });
    }

    public function down()
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['management_fee_rate', 'insurance_fee_rate']);
        });
    }
};
