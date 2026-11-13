<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Loan write-offs (Locked-Up loans): principal DR Bad Loan Write-Off (5121) / CR Locked-Up receivable (1104);
 * interest DR Accrued Interest (1106) / CR Locked-Up Loans Interest Receivable (1112). Loan status `written_off`.
 * Adds permission "write off loans" (super_admin, ceo_manager, credit_manager) and the three account settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE loans MODIFY status ENUM('pending','approved','active','closed','defaulted','written_off') NOT NULL DEFAULT 'pending'");

        Schema::create('loan_writeoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->date('writeoff_date');
            $table->decimal('principal', 18, 2)->default(0);
            $table->decimal('interest', 18, 2)->default(0);
            $table->decimal('penalty', 18, 2)->default(0);           // penalty brought forward cleared (off the books, no journal)
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->string('reason', 500);
            $table->json('snapshot')->nullable();                    // raw loan figures + repayment ids before the write-off
            $table->string('status', 20)->default('applied');       // applied | reversed
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->unsignedBigInteger('reversed_by')->nullable();
            $table->timestamps();
            $table->index(['loan_id', 'status']);
        });

        $now = now();
        foreach ([
            'loan_writeoff_expense_account'         => ['5121', 'Loan write-off: principal debited to (bad loan write-off expense)'],
            'loan_writeoff_interest_debit_account'  => ['1106', 'Loan write-off: interest debited to (accrued interest)'],
            'loan_writeoff_interest_credit_account' => ['1112', 'Loan write-off: interest credited to (loan outstanding interest)'],
        ] as $key => [$value, $label]) {
            if (!DB::table('system_settings')->where('key', $key)->exists()) {
                $row = ['key' => $key, 'value' => $value, 'created_at' => $now, 'updated_at' => $now];
                if (Schema::hasColumn('system_settings', 'label')) $row['label'] = $label;
                if (Schema::hasColumn('system_settings', 'group')) $row['group'] = 'loans';
                DB::table('system_settings')->insert($row);
            }
            Cache::forget("setting_{$key}");
        }

        if (Schema::hasTable('permissions')) {
            $guard = config('auth.defaults.guard', 'web');
            DB::table('permissions')->insertOrIgnore(['name' => 'write off loans', 'guard_name' => $guard, 'created_at' => $now, 'updated_at' => $now]);
            $perm = DB::table('permissions')->where('name', 'write off loans')->value('id');
            foreach (DB::table('roles')->whereIn('name', ['super_admin', 'ceo_manager', 'credit_manager'])->pluck('id') as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $perm, 'role_id' => $roleId]);
            }
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_writeoffs');
        DB::table('loans')->where('status', 'written_off')->update(['status' => 'defaulted']);
        DB::statement("ALTER TABLE loans MODIFY status ENUM('pending','approved','active','closed','defaulted') NOT NULL DEFAULT 'pending'");
        DB::table('system_settings')->whereIn('key', ['loan_writeoff_expense_account', 'loan_writeoff_interest_debit_account', 'loan_writeoff_interest_credit_account'])->delete();
        DB::table('permissions')->where('name', 'write off loans')->delete();
    }
};
