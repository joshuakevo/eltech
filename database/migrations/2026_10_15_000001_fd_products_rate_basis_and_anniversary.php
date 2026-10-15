<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Investment placements (e.g. Venture Capital Studio) on the fixed deposit engine:
 *  - rate_basis: how rates are entered/shown — 'annual' (% p.a., default) or 'monthly' (% per month).
 *    Rates are always STORED as % p.a. so P × R × T stays one formula.
 *  - accrual_method: 'prorata' (default, by days, monthly run) or 'anniversary' (one month's
 *    interest booked on each monthly anniversary of the value date).
 *  - asset_class: shown on the investment placement certificate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_deposit_products', function (Blueprint $table) {
            $table->string('rate_basis', 10)->default('annual')->after('interest_rate');
            $table->string('accrual_method', 15)->default('prorata')->after('rate_basis');
            $table->string('asset_class')->nullable()->after('accrual_method');
        });
    }

    public function down(): void
    {
        Schema::table('fixed_deposit_products', function (Blueprint $table) {
            $table->dropColumn(['rate_basis', 'accrual_method', 'asset_class']);
        });
    }
};
