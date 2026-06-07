<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('savings_accounts', function (Blueprint $table) {
            $table->date('last_interest_date')->nullable()->after('opened_date');
        });
    }

    public function down()
    {
        Schema::table('savings_accounts', function (Blueprint $table) {
            $table->dropColumn('last_interest_date');
        });
    }
};
