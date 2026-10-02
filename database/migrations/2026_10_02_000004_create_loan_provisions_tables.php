<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('loan_provisions')) {
            Schema::create('loan_provisions', function (Blueprint $table) {
                $table->id();
                $table->enum('provision_type', ['general', 'specific'])->default('general');
                $table->date('as_at_date');
                $table->decimal('rate', 8, 4);                       // percent, e.g. 1.0000 = 1%
                $table->decimal('total_outstanding', 15, 2)->default(0);
                $table->decimal('required_provision', 15, 2)->default(0);
                $table->decimal('previous_provision', 15, 2)->default(0); // GL balance before this run
                $table->decimal('adjustment', 15, 2)->default(0);         // + charge, - write-back
                $table->unsignedInteger('loan_count')->default(0);
                $table->unsignedBigInteger('transaction_id')->nullable();
                $table->enum('status', ['posted', 'reversed'])->default('posted');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['provision_type', 'as_at_date']);
                $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            });
        }

        if (!Schema::hasTable('loan_provision_lines')) {
            Schema::create('loan_provision_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('loan_provision_id');
                $table->unsignedBigInteger('loan_id');
                $table->unsignedBigInteger('client_id')->nullable();
                $table->decimal('outstanding_principal', 15, 2);
                $table->decimal('rate', 8, 4);
                $table->decimal('provision_amount', 15, 2);
                $table->timestamps();

                $table->foreign('loan_provision_id')->references('id')->on('loan_provisions')->onDelete('cascade');
                $table->foreign('loan_id')->references('id')->on('loans')->onDelete('restrict');
                $table->foreign('client_id')->references('id')->on('clients')->onDelete('set null');
            });
        }

        // Permissions — granted additively so custom role setups are preserved.
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $view = Permission::firstOrCreate(['name' => 'view loan provisions', 'guard_name' => 'web']);
        $run  = Permission::firstOrCreate(['name' => 'run loan provisions', 'guard_name' => 'web']);

        foreach (Role::all() as $role) {
            if (in_array($role->name, ['group_leader', 'group_member', 'client'], true)) {
                continue;
            }
            if ($role->name === 'super_admin' || $role->hasPermissionTo('view loans')) {
                $role->givePermissionTo($view);
            }
            if ($role->name === 'super_admin' || $role->hasPermissionTo('disburse loans')) {
                $role->givePermissionTo($run);
            }
        }
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_provision_lines');
        Schema::dropIfExists('loan_provisions');
        Permission::whereIn('name', ['view loan provisions', 'run loan provisions'])->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
