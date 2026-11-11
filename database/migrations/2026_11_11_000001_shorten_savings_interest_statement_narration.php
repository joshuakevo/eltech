<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Statement narration for interest runs: "Interest credit (01 Oct 2026 – 31 Oct 2026) 4% (month end)"
 * becomes "Interest credited (01 Oct 2026 – 31 Oct 2026)" — no rate or method on the member's statement.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('savings_transactions')
            ->where('transaction_type', 'interest')
            ->where('description', 'like', 'Interest credit (%')
            ->update(['description' => DB::raw("CONCAT('Interest credited', SUBSTRING(SUBSTRING_INDEX(description, ')', 1), 16), ')')")]);
    }

    public function down(): void
    {
        // Narration only — nothing to restore.
    }
};
