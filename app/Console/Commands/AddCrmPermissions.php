<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Adds the two permissions gating the new CRM module -- 'view crm' (see the
 * CRM section at all) and 'manage crm' (create/edit notes, tasks, and act on
 * opportunities) -- and grants both directly to super_admin, admin, and
 * cashier, matching who already has full day-to-day client/product access.
 * staff (the view-only role) does not get it by default.
 *
 * Unlike the additive "granted wherever X already applies" rollout pattern
 * used for prior permission splits, there is no pre-existing bundled
 * permission for CRM to piggyback on, so this grants directly to the three
 * named roles. Safe to run more than once.
 */
class AddCrmPermissions extends Command
{
    protected $signature = 'eltech:add-crm-permissions {--confirm : Actually write changes; omit for a dry-run report}';
    protected $description = 'Add "view crm" and "manage crm" permissions, granted to super_admin, admin, and cashier';

    private const PERMISSIONS = ['view crm', 'manage crm'];
    private const ROLES       = ['super_admin', 'admin', 'cashier'];

    public function handle(): int
    {
        $confirm = (bool) $this->option('confirm');
        if (!$confirm) {
            $this->warn('DRY RUN — no changes will be saved. Re-run with --confirm to apply.');
        }
        $this->line('');

        DB::beginTransaction();
        try {
            foreach (self::PERMISSIONS as $permName) {
                $permission = Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
                $this->line(($permission->wasRecentlyCreated ? 'Created' : 'Already exists') . ": permission \"{$permName}\"");

                foreach (self::ROLES as $roleName) {
                    $role = Role::where('name', $roleName)->first();
                    if (!$role) {
                        $this->line("  role \"{$roleName}\" not found -- skipped");
                        continue;
                    }
                    if ($role->hasPermissionTo($permName)) {
                        $this->line("  role \"{$roleName}\" already has \"{$permName}\"");
                        continue;
                    }
                    $this->line("  grant \"{$permName}\" -> role \"{$roleName}\"");
                    if ($confirm) {
                        $role->givePermissionTo($permName);
                    }
                }
                $this->line('');
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
            ? 'Done. CRM permissions created and granted to super_admin, admin, cashier.'
            : 'Dry run only — re-run with --confirm to apply.');

        return self::SUCCESS;
    }
}
