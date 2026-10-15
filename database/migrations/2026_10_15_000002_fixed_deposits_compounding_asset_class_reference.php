<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-placement terms for investment placements:
 *  - compounding: 'simple' (P × R × T, default) or 'monthly' (P × ((1 + r/12)^months − 1)).
 *  - asset_class: overrides the product's asset class on the certificate.
 *  - certificate_reference: the reference printed on the certificate (e.g. KIL/2026/IVF-094).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_deposits', function (Blueprint $table) {
            $table->string('compounding', 10)->default('simple')->after('interest_rate');
            $table->string('asset_class')->nullable()->after('compounding');
            $table->string('certificate_reference', 100)->nullable()->after('asset_class');
        });
    }

    public function down(): void
    {
        Schema::table('fixed_deposits', function (Blueprint $table) {
            $table->dropColumn(['compounding', 'asset_class', 'certificate_reference']);
        });
    }
};
