<?php

use App\Models\Account;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('application_fee', 15, 2)->default(0)->after('principal');
            $table->decimal('application_fee_rate', 5, 2)->default(0)->after('application_fee');
        });

        $revenueParentId = Account::where('account_code', '4000')->value('id');
        Account::updateOrCreate(
            ['account_code' => '4011'],
            [
                'account_name' => 'Loan Application Fee Income',
                'account_type' => 'revenue',
                'parent_id'    => $revenueParentId,
                'is_active'    => true,
            ]
        );
    }

    public function down()
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['application_fee', 'application_fee_rate']);
        });
        Account::where('account_code', '4011')->delete();
    }
};
