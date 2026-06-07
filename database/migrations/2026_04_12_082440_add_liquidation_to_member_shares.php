<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE member_shares MODIFY COLUMN status ENUM('unpaid','partial','paid','liquidated') NOT NULL DEFAULT 'unpaid'");

        Schema::table('member_shares', function (Blueprint $table) {
            $table->date('liquidated_at')->nullable()->after('status');
            $table->string('liquidation_notes')->nullable()->after('liquidated_at');
            $table->foreignId('liquidated_by')->nullable()->constrained('users')->nullOnDelete()->after('liquidation_notes');
        });
    }

    public function down(): void
    {
        Schema::table('member_shares', function (Blueprint $table) {
            $table->dropForeign(['liquidated_by']);
            $table->dropColumn(['liquidated_at', 'liquidation_notes', 'liquidated_by']);
        });
        DB::statement("ALTER TABLE member_shares MODIFY COLUMN status ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid'");
    }
};
