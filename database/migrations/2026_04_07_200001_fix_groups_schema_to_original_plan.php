<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Fix groups table ──────────────────────────────────────────────
        Schema::table('groups', function (Blueprint $table) {
            // Drop client_id (groups are standalone, not tied to a client)
            if (Schema::hasColumn('groups', 'client_id')) {
                $table->dropForeign(['client_id']);
                $table->dropColumn('client_id');
            }
            // Add standalone group fields
            if (!Schema::hasColumn('groups', 'name')) {
                $table->string('name')->after('group_number')->default('');
            }
            if (!Schema::hasColumn('groups', 'registration_date')) {
                $table->date('registration_date')->nullable()->after('name');
            }
            if (!Schema::hasColumn('groups', 'gl_account_id')) {
                $table->unsignedBigInteger('gl_account_id')->nullable()->after('monthly_interest_rate');
                $table->foreign('gl_account_id')->references('id')->on('accounts')->nullOnDelete();
            }
            if (!Schema::hasColumn('groups', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // ── Fix group_members: rename full_name → name ────────────────────
        if (Schema::hasColumn('group_members', 'full_name') && !Schema::hasColumn('group_members', 'name')) {
            DB::statement('ALTER TABLE `group_members` CHANGE `full_name` `name` VARCHAR(255) NOT NULL');
        }

        // ── Fix group_transactions: rename group_member_id → member_id ────
        if (Schema::hasColumn('group_transactions', 'group_member_id') && !Schema::hasColumn('group_transactions', 'member_id')) {
            Schema::table('group_transactions', function (Blueprint $table) {
                $table->dropForeign(['group_member_id']);
            });
            DB::statement('ALTER TABLE `group_transactions` CHANGE `group_member_id` `member_id` BIGINT UNSIGNED NULL');
            Schema::table('group_transactions', function (Blueprint $table) {
                $table->foreign('member_id')->references('id')->on('group_members')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Reverse: re-add client_id, rename name→full_name, rename member_id→group_member_id
        Schema::table('groups', function (Blueprint $table) {
            if (!Schema::hasColumn('groups', 'client_id')) {
                $table->foreignId('client_id')->nullable()->constrained('clients')->cascadeOnDelete();
            }
            if (Schema::hasColumn('groups', 'name')) $table->dropColumn('name');
            if (Schema::hasColumn('groups', 'registration_date')) $table->dropColumn('registration_date');
            if (Schema::hasColumn('groups', 'gl_account_id')) {
                $table->dropForeign(['gl_account_id']);
                $table->dropColumn('gl_account_id');
            }
            if (Schema::hasColumn('groups', 'deleted_at')) $table->dropSoftDeletes();
        });
    }
};
