<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Loan balance corrections (Correct Balance pop-up on the loan page).
 * Each row keeps a snapshot of the loan + schedule before the correction so it
 * can be undone exactly; the GL side is the linked principal adjustment journal.
 *
 * Also adds the "correct loans" permission, granted additively to every role that
 * already has "repay loans" (nothing is removed from any role).
 */
return new class extends Migration {
    public function up() {
        Schema::create('loan_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->date('as_at_date');
            $table->decimal('old_principal', 15, 2);
            $table->decimal('old_interest', 15, 2);
            $table->decimal('principal_at_date', 15, 2);
            $table->decimal('interest_at_date', 15, 2);
            $table->decimal('new_principal', 15, 2);
            $table->decimal('new_interest', 15, 2);
            $table->decimal('principal_adjustment', 15, 2)->default(0);
            $table->foreignId('offset_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->string('reason', 500);
            $table->json('snapshot');
            $table->enum('status', ['applied', 'reversed'])->default('applied');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $guard = config('auth.defaults.guard', 'web');
        $now   = now();
        DB::table('permissions')->insertOrIgnore([
            'name' => 'correct loans', 'guard_name' => $guard, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $permId = DB::table('permissions')->where('name', 'correct loans')->where('guard_name', $guard)->value('id');
        $repayId = DB::table('permissions')->where('name', 'repay loans')->where('guard_name', $guard)->value('id');
        if ($permId && $repayId) {
            foreach (DB::table('role_has_permissions')->where('permission_id', $repayId)->pluck('role_id') as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permId, 'role_id' => $roleId]);
            }
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down() {
        Schema::dropIfExists('loan_corrections');
        $permId = DB::table('permissions')->where('name', 'correct loans')->value('id');
        if ($permId) {
            DB::table('role_has_permissions')->where('permission_id', $permId)->delete();
            DB::table('model_has_permissions')->where('permission_id', $permId)->delete();
            DB::table('permissions')->where('id', $permId)->delete();
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
