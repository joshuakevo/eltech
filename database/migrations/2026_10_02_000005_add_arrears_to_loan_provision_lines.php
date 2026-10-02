<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_provision_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('loan_provision_lines', 'days_in_arrears')) {
                $table->unsignedInteger('days_in_arrears')->nullable()->after('client_id');
            }
            if (!Schema::hasColumn('loan_provision_lines', 'oldest_arrears_date')) {
                $table->date('oldest_arrears_date')->nullable()->after('days_in_arrears');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_provision_lines', function (Blueprint $table) {
            $table->dropColumn(['days_in_arrears', 'oldest_arrears_date']);
        });
    }
};
