<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->enum('repayment_frequency', ['monthly', 'quarterly'])->default('monthly')->after('term_months');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->enum('repayment_frequency', ['monthly', 'quarterly'])->default('monthly')->after('term_months');
        });
    }

    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->dropColumn('repayment_frequency');
        });
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('repayment_frequency');
        });
    }
};
