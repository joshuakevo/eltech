<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->decimal('staff_savings', 15, 2)->default(0)->after('transport');
        });
    }
    public function down() {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn('staff_savings');
        });
    }
};
