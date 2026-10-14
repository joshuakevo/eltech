<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Four-eyes lock-up of a normal loan: one person initiates, one Ceo manager and one Credit
 * manager approve (both different people, neither the initiator), then the loan moves to
 * Locked-Up Loans. Adds permission "initiate loan lockup" for every role that can create loans.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_lockups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->date('lockup_date');
            $table->string('reason', 500);
            $table->string('status', 20)->default('pending');   // pending | executed | rejected | cancelled | reversed
            $table->unsignedBigInteger('requested_by');
            $table->json('approvals')->nullable();               // [{user_id, name, role, at, note}]
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->decimal('principal', 15, 2)->nullable();     // moved to Locked-Up at execution
            $table->decimal('interest', 15, 2)->nullable();      // interest owed at execution (incl. days to the lock-up date)
            $table->unsignedBigInteger('from_product_id')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->json('snapshot')->nullable();                // raw loan + schedule before execution
            $table->timestamp('reversed_at')->nullable();
            $table->unsignedBigInteger('reversed_by')->nullable();
            $table->timestamps();
            $table->index(['loan_id', 'status']);
        });

        if (Schema::hasTable('permissions')) {
            $guard = config('auth.defaults.guard', 'web');
            $now = now();
            DB::table('permissions')->insertOrIgnore(['name' => 'initiate loan lockup', 'guard_name' => $guard, 'created_at' => $now, 'updated_at' => $now]);
            $perm = DB::table('permissions')->where('name', 'initiate loan lockup')->value('id');
            $createLoans = DB::table('permissions')->where('name', 'create loans')->value('id');
            $roles = DB::table('role_has_permissions')->where('permission_id', $createLoans)->pluck('role_id')
                ->merge(DB::table('roles')->whereIn('name', ['super_admin', 'ceo_manager', 'credit_manager'])->pluck('id'))->unique();
            foreach ($roles as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $perm, 'role_id' => $roleId]);
            }
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_lockups');
        DB::table('permissions')->where('name', 'initiate loan lockup')->delete();
    }
};
