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
        Schema::table('groups', function (Blueprint $table) {
            $table->enum('group_type', ['savings', 'pooled'])->default('savings')->after('name');
            $table->decimal('expected_contribution', 15, 2)->nullable()->after('membership_fee');
            $table->enum('contribution_cycle', ['weekly', 'biweekly', 'monthly', 'quarterly'])->nullable()->after('expected_contribution');
            $table->decimal('pool_balance', 15, 2)->default(0)->after('contribution_cycle');
        });
    }

    public function down()
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn(['group_type', 'expected_contribution', 'contribution_cycle', 'pool_balance']);
        });
    }
};
