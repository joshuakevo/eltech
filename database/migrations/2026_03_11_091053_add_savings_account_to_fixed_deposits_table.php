<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('fixed_deposits', function (Blueprint $table) {
            $table->unsignedBigInteger('savings_account_id')->nullable()->after('client_id');
            $table->foreign('savings_account_id')->references('id')->on('savings_accounts')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('fixed_deposits', function (Blueprint $table) {
            $table->dropForeign(['savings_account_id']);
            $table->dropColumn('savings_account_id');
        });
    }
};
