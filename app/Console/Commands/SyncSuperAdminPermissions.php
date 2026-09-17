<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The Roles & Permissions screen tells the admin "Super Admin always has all
 * permissions and cannot be edited" and hides its checkbox list accordingly
 * -- but that's only a UI message. Nothing in the app code actually enforces
 * it (no Gate::before bypass exists); super_admin's access is just an
 * ordinary Spatie role-permission assignment, set once by
 * RolesAndPermissionsSeeder and otherwise never kept in sync. Any permission
 * added since (e.g. a new module, or "delete transactions") sits ungranted
 * for super_admin until something explicitly assigns it, silently breaking
 * the guarantee the UI promises -- which is exactly what caused a super_admin
 * user to get a genuine 403 on "manage shares" despite the screen insisting
 * they have every permission.
 *
 * Safe to run any time a new permission is introduced: syncs super_admin to
 * every permission that currently exists, and nothing else -- it never
 * touches admin/cashier/staff or any custom role.
 */
class SyncSuperAdminPermissions extends Command
{
    protected $signature   = 'eltech:sync-super-admin-permissions {--confirm : Actually write changes; omit for a dry-run report}';
    protected $description = 'Ensure super_admin has every permission that currently exists -- restores the guarantee the Roles & Permissions screen claims';

    public function handle(): int
    {
        $confirm = (bool) $this->option('confirm');
        if (!$confirm) {
            $this->warn('DRY RUN — no changes will be saved. Re-run with --confirm to apply.');
        }
        $this->line('');

        $role = Role::where('name', 'super_admin')->first();
        if (!$role) {
            $this->error('super_admin role not found.');
            return self::FAILURE;
        }

        $all      = Permission::pluck('name')->toArray();
        $current  = $role->permissions->pluck('name')->toArray();
        $missing  = array_values(array_diff($all, $current));

        $this->info("super_admin currently has " . count($current) . ' of ' . count($all) . ' permissions.');

        if (empty($missing)) {
            $this->info('Nothing missing — already matches the "all permissions" guarantee.');
            return self::SUCCESS;
        }

        $this->warn('Missing:');
        foreach ($missing as $perm) {
            $this->line("  - {$perm}");
        }

        if ($confirm) {
            DB::transaction(function () use ($role, $all) {
                $role->syncPermissions($all);
            });
            app()[PermissionRegistrar::class]->forgetCachedPermissions();
            $this->info('Done. super_admin now has all ' . count($all) . ' permissions.');
        } else {
            $this->line('');
            $this->line('Re-run with --confirm to grant the missing permissions above.');
        }

        return self::SUCCESS;
    }
}
