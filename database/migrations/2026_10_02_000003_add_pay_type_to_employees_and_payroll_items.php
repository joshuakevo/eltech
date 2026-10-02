<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('pay_type', 20)->default('salary')->after('department');
        });
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->string('pay_type', 20)->default('salary')->after('savings_account_id');
        });
    }
    public function down() {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('pay_type');
        });
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn('pay_type');
        });
    }
};
