<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('employee_number')->constrained('clients')->nullOnDelete();
            $table->dropColumn('name');
        });
    }
    public function down() {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropColumn('client_id');
            $table->string('name')->after('employee_number');
        });
    }
};
