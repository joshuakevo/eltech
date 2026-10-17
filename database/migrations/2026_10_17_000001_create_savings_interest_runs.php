<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Running Interest (Accounting → Running Interest): per-product savings interest runs with a preview,
 * a report and undo (deletes the run's journal and statement rows).
 *  - savings_products.interest_run_method: 'tiers' (org-wide graduated tiers) | 'bands' (whole balance at the
 *    rate of the highest band it reaches) ; interest_bands = [{"min":5000000,"rate":10},{"min":1500000,"rate":7}]
 *  - savings_accounts.interest_rate_override: special annual rate for one account (the "few" exceptions)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('savings_products', function (Blueprint $table) {
            if (!Schema::hasColumn('savings_products', 'interest_run_method')) {
                $table->string('interest_run_method', 20)->nullable()->after('interest_method');
            }
            if (!Schema::hasColumn('savings_products', 'interest_bands')) {
                $table->json('interest_bands')->nullable()->after('interest_run_method');
            }
        });
        Schema::table('savings_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('savings_accounts', 'interest_rate_override')) {
                $table->decimal('interest_rate_override', 8, 4)->nullable()->after('last_interest_date');
            }
        });

        if (!Schema::hasTable('savings_interest_runs')) {
            Schema::create('savings_interest_runs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('savings_product_id')->constrained('savings_products');
                $table->date('period_end');
                $table->decimal('total_interest', 18, 2)->default(0);
                $table->unsignedInteger('accounts_count')->default(0);
                $table->unsignedBigInteger('transaction_id')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['savings_product_id', 'period_end']);
            });
        }
        if (!Schema::hasTable('savings_interest_run_lines')) {
            Schema::create('savings_interest_run_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('savings_interest_run_id')->constrained('savings_interest_runs')->cascadeOnDelete();
                $table->unsignedBigInteger('savings_account_id');
                $table->unsignedBigInteger('client_id')->nullable();
                $table->date('period_start');
                $table->date('period_end');
                $table->unsignedInteger('days');
                $table->decimal('closing_balance', 18, 2);
                $table->decimal('average_balance', 18, 2);
                $table->string('rate_label', 40);
                $table->decimal('interest', 18, 2);
                $table->unsignedBigInteger('savings_transaction_id')->nullable();
                $table->date('previous_last_interest_date')->nullable();
                $table->index('savings_account_id');
            });
        }

        // Interest Wallet: 10% p.a. from 5M, 7% from 1.5M (below: nothing). Ordinary Savings: the org-wide tiers.
        DB::table('savings_products')->where('name', 'like', '%Interest Wallet%')->whereNull('interest_run_method')
            ->update(['interest_run_method' => 'bands', 'interest_bands' => json_encode([['min' => 5000000, 'rate' => 10], ['min' => 1500000, 'rate' => 7]])]);
        DB::table('savings_products')->whereNull('interest_run_method')
            ->update(['interest_run_method' => 'tiers']);

        if (Schema::hasTable('permissions')) {
            $guard = config('auth.defaults.guard', 'web');
            $now = now();
            DB::table('permissions')->insertOrIgnore(['name' => 'run savings interest', 'guard_name' => $guard, 'created_at' => $now, 'updated_at' => $now]);
            $perm = DB::table('permissions')->where('name', 'run savings interest')->value('id');
            $tiers = DB::table('permissions')->where('name', 'manage savings interest tiers')->value('id');
            $roles = DB::table('role_has_permissions')->where('permission_id', $tiers)->pluck('role_id')
                ->merge(DB::table('roles')->whereIn('name', ['super_admin', 'admin', 'ceo_manager'])->pluck('id'))->unique();
            foreach ($roles as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $perm, 'role_id' => $roleId]);
            }
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_interest_run_lines');
        Schema::dropIfExists('savings_interest_runs');
        Schema::table('savings_accounts', fn (Blueprint $t) => $t->dropColumn('interest_rate_override'));
        Schema::table('savings_products', fn (Blueprint $t) => $t->dropColumn(['interest_run_method', 'interest_bands']));
        DB::table('permissions')->where('name', 'run savings interest')->delete();
    }
};
