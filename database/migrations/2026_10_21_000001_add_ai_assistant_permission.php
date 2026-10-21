<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Floating AI assistant: permission "use ai assistant" for super admin, admin, CEO manager and credit manager. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }
        $guard = config('auth.defaults.guard', 'web');
        DB::table('permissions')->insertOrIgnore(['name' => 'use ai assistant', 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now()]);
        $perm = DB::table('permissions')->where('name', 'use ai assistant')->value('id');
        foreach (DB::table('roles')->whereIn('name', ['super_admin', 'admin', 'ceo_manager', 'credit_manager'])->pluck('id') as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $perm, 'role_id' => $roleId]);
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'use ai assistant')->delete();
    }
};
