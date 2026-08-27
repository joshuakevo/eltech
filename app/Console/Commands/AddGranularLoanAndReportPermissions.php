<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * One-off (August 2026): splits three previously-bundled permissions into
 * their own dedicated ones, so they can be granted to a specific user
 * independently via the existing per-user "Direct Permissions" section on
 * the Edit User page (Users > edit a user), without also granting everything
 * else the old bundle covered:
 *
 *  - 'run loans'          was bundled into 'view loans' (the Run Loans batch
 *                          screen, loans/run)
 *  - 'view loan reports'  was bundled into 'view reports' (Loan Reports
 *                          sidebar section: Loan Disbursements, Loan
 *                          Recoveries, Loan Portfolio, Loan Aging)
 *  - 'view savings reports' was bundled into 'view reports' (Savings Reports
 *                          sidebar section: Savings Balances, FD Maturity)
 *
 * This does NOT use Role::syncPermissions() (which would reset a role to an
 * exact list and could silently wipe any custom permissions added since via
 * the Roles admin screen). It only ADDS: for every role/user that currently
 * has 'view loans', it additionally grants 'run loans'; for every role/user
 * that currently has 'view reports', it additionally grants 'view loan
 * reports' and 'view savings reports'. Nothing is ever removed, so current
 * access is fully preserved -- the new permissions just become independently
 * assignable/revokable going forward.
 */
class AddGranularLoanAndReportPermissions extends Command
{
    protected $signature = 'eltech:add-granular-loan-report-permissions {--confirm : Actually write changes; omit for a dry-run report}';
    protected $description = 'Add dedicated "run loans", "view loan reports", "view savings reports" permissions, granted additively wherever the old bundled permission already applies';

    private const NEW_PERMISSIONS = [
        'run loans'            => 'view loans',
        'view loan reports'    => 'view reports',
        'view savings reports' => 'view reports',
    ];

    public function handle(): int
    {
        $confirm = (bool) $this->option('confirm');
        if (!$confirm) {
            $this->warn('DRY RUN — no changes will be saved. Re-run with --confirm to apply.');
        }
        $this->line('');

        DB::beginTransaction();
        try {
            foreach (self::NEW_PERMISSIONS as $newPerm => $basedOn) {
                $permission = Permission::firstOrCreate(['name' => $newPerm, 'guard_name' => 'web']);
                $this->line(($permission->wasRecentlyCreated ? 'Created' : 'Already exists') . ": permission \"{$newPerm}\"");

                $roles = Role::permission($basedOn)->get();
                foreach ($roles as $role) {
                    if ($role->hasPermissionTo($newPerm)) {
                        $this->line("  role \"{$role->name}\" already has \"{$newPerm}\"");
                        continue;
                    }
                    $this->line("  role \"{$role->name}\" has \"{$basedOn}\" -> grant \"{$newPerm}\"");
                    if ($confirm) {
                        $role->givePermissionTo($newPerm);
                    }
                }

                $users = User::permission($basedOn)->get();
                foreach ($users as $user) {
                    if (!$user->hasDirectPermission($basedOn)) {
                        continue; // has it via role only -- already covered above
                    }
                    if ($user->hasDirectPermission($newPerm)) {
                        $this->line("  user \"{$user->name}\" already has direct \"{$newPerm}\"");
                        continue;
                    }
                    $this->line("  user \"{$user->name}\" has direct \"{$basedOn}\" -> grant direct \"{$newPerm}\"");
                    if ($confirm) {
                        $user->givePermissionTo($newPerm);
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
            ? 'Done. New permissions created and granted additively -- nothing was removed.'
            : 'Dry run only — re-run with --confirm to apply.');

        return self::SUCCESS;
    }
}
