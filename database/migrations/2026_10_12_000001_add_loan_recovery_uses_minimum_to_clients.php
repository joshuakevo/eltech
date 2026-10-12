<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Run Loans may recover an installment from the savings minimum balance (down to 0) for this member
            $table->boolean('loan_recovery_uses_minimum')->default(false)->after('relationship_manager_id');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('loan_recovery_uses_minimum');
        });
    }
};
