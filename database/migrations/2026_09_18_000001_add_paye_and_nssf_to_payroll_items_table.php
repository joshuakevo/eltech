<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->decimal('paye', 15, 2)->default(0)->after('allowances');
            $table->decimal('nssf_employee', 15, 2)->default(0)->after('paye');
            $table->decimal('nssf_employer', 15, 2)->default(0)->after('nssf_employee');
        });
    }
    public function down() {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn(['paye', 'nssf_employee', 'nssf_employer']);
        });
    }
};
