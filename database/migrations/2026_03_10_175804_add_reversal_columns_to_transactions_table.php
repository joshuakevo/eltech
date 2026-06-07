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
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('reversal_of')->nullable()->after('created_by');
            $table->unsignedBigInteger('reversed_by')->nullable()->after('reversal_of');
            $table->string('reversal_reason')->nullable()->after('reversed_by');

            $table->foreign('reversal_of')->references('id')->on('transactions')->onDelete('set null');
            $table->foreign('reversed_by')->references('id')->on('transactions')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['reversal_of']);
            $table->dropForeign(['reversed_by']);
            $table->dropColumn(['reversal_of', 'reversed_by', 'reversal_reason']);
        });
    }
};
