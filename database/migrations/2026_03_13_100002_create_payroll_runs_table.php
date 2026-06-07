<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_number', 20)->unique();
            $table->unsignedTinyInteger('period_month'); // 1-12
            $table->unsignedSmallInteger('period_year');
            $table->string('description')->nullable();
            $table->decimal('total_gross', 15, 2)->default(0);
            $table->enum('status', ['draft', 'processed'])->default('draft');
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
    public function down() { Schema::dropIfExists('payroll_runs'); }
};
