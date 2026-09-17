<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * One-off (September 2026): the "Delete" action on a Journal Entry was
 * hardcoded to role:super_admin rather than a permission check, so no
 * permission existed to grant it through. Creates the new "delete
 * transactions" permission and grants it to the main_cashier role (per
 * request) plus super_admin (to preserve existing access, since the route
 * no longer checks the role directly). Additive only -- never resets or
 * removes any role's existing permissions, unlike the full
 * RolesAndPermissionsSeeder.
 */
class AddDeleteTransactionsPermission extends Command
{
    protected $signature   = 'eltech:add-delete-transactions-permission {--confirm : Actually write changes; omit for a dry-run report}';
    protected $description = 'Add the "delete transactions" permission and grant it to main_cashier and super_admin';

    private const NEW_PERMISSION = 'delete transactions';
    private const GRANT_TO       = ['main_cashier', 'super_admin'];

    public function handle(): int
    {
        $confirm = (bool) $this->option('confirm');
        if (!$confirm) {
            $this->warn('DRY RUN — no changes will be saved. Re-run with --confirm to apply.');
        }
        $this->line('');

        DB::beginTransaction();
        try {
            $permission = Permission::firstOrCreate(['name' => self::NEW_PERMISSION, 'guard_name' => 'web']);
            $this->line(($permission->wasRecentlyCreated ? 'Created' : 'Already exists') . ': permission "' . self::NEW_PERMISSION . '"');

            foreach (self::GRANT_TO as $roleName) {
                $role = Role::where('name', $roleName)->first();
                if (!$role) {
                    $this->line("  role \"{$roleName}\" not found -- skipped");
                    continue;
                }
                if ($role->hasPermissionTo(self::NEW_PERMISSION)) {
                    $this->line("  role \"{$roleName}\" already has \"" . self::NEW_PERMISSION . '"');
                    continue;
                }
                $this->line("  role \"{$roleName}\" -> grant \"" . self::NEW_PERMISSION . '"');
                if ($confirm) {
                    $role->givePermissionTo(self::NEW_PERMISSION);
                }
            }

            if ($confirm) {
                app()[PermissionRegistrar::class]->forgetCachedPermissions();
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->info($confirm
            ? 'Done. "delete transactions" created and granted to main_cashier + super_admin -- nothing else was touched.'
            : 'Dry run only — re-run with --confirm to apply.');

        return self::SUCCESS;
    }
}
