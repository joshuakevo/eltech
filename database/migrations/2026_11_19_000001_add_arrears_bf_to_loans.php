<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arrears brought forward from the old system's provision report: the date the loan's arrears started
 * (as-at date − days in arrears) and the amount in arrears on that report. The specific provision counts the
 * loan's arrears from that date until repayments after the report date clear that amount
 * (schedules rebuilt at the transfer lost the old due dates).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->date('arrears_bf_since')->nullable()->after('backdated_interest');
            $table->decimal('arrears_bf_amount', 18, 2)->nullable()->after('arrears_bf_since');
            $table->date('arrears_bf_as_at')->nullable()->after('arrears_bf_amount');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['arrears_bf_since', 'arrears_bf_amount', 'arrears_bf_as_at']);
        });
    }
};
