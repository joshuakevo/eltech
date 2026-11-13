<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Overdraft charges are run at month end (Accounting → Overdraft Charges), not at withdrawal:
 * each run charges every account negative at that date once. Charges belong to their run (undo).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('overdraft_runs')) {
            Schema::create('overdraft_runs', function (Blueprint $table) {
                $table->id();
                $table->date('run_date');                         // month end
                $table->decimal('rate', 8, 4);
                $table->decimal('minimum', 18, 2)->default(0);
                $table->unsignedInteger('accounts')->default(0);
                $table->decimal('total', 18, 2)->default(0);
                $table->string('status', 10)->default('applied'); // applied | reversed
                $table->timestamp('reversed_at')->nullable();
                $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index('run_date');
            });
        }
        if (!Schema::hasColumn('overdraft_charges', 'overdraft_run_id')) {
            Schema::table('overdraft_charges', function (Blueprint $table) {
                $table->foreignId('overdraft_run_id')->nullable()->after('id')->constrained('overdraft_runs')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('overdraft_charges', 'overdraft_run_id')) {
            Schema::table('overdraft_charges', fn (Blueprint $t) => $t->dropConstrainedForeignId('overdraft_run_id'));
        }
        Schema::dropIfExists('overdraft_runs');
    }
};
