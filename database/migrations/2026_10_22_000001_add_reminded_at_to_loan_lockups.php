<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** When the approvers of a lock-up request were last emailed a reminder. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('loan_lockups', 'reminded_at')) {
            Schema::table('loan_lockups', fn (Blueprint $t) => $t->timestamp('reminded_at')->nullable()->after('approvals'));
        }
    }

    public function down(): void
    {
        Schema::table('loan_lockups', fn (Blueprint $t) => $t->dropColumn('reminded_at'));
    }
};
