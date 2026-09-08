<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * One-off (September 2026): adds a dedicated 'overdraw savings' permission,
 * granted additively wherever 'withdraw savings' already applies (role or
 * direct user grant) -- same rollout pattern as
 * AddGranularLoanAndReportPermissions. Never removes anything, so current
 * access is unaffected; it only makes the confirm-overdraft option on the
 * Withdraw screens available to whoever could already process a withdrawal.
 */
class AddOverdrawSavingsPermission extends Command
{
    protected $signature = 'eltech:add-overdraw-savings-permission {--confirm : Actually write changes; omit for a dry-run report}';
    protected $description = 'Add the "overdraw savings" permission, granted additively wherever "withdraw savings" already applies';

    private const NEW_PERMISSION = 'overdraw savings';
    private const BASED_ON       = 'withdraw savings';

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

            $roles = Role::permission(self::BASED_ON)->get();
            foreach ($roles as $role) {
                if ($role->hasPermissionTo(self::NEW_PERMISSION)) {
                    $this->line("  role \"{$role->name}\" already has \"" . self::NEW_PERMISSION . '"');
                    continue;
                }
                $this->line("  role \"{$role->name}\" has \"" . self::BASED_ON . '" -> grant "' . self::NEW_PERMISSION . '"');
                if ($confirm) {
                    $role->givePermissionTo(self::NEW_PERMISSION);
                }
            }

            $users = User::permission(self::BASED_ON)->get();
            foreach ($users as $user) {
                if (!$user->hasDirectPermission(self::BASED_ON)) {
                    continue; // has it via role only -- already covered above
                }
                if ($user->hasDirectPermission(self::NEW_PERMISSION)) {
                    $this->line("  user \"{$user->name}\" already has direct \"" . self::NEW_PERMISSION . '"');
                    continue;
                }
                $this->line("  user \"{$user->name}\" has direct \"" . self::BASED_ON . '" -> grant direct "' . self::NEW_PERMISSION . '"');
                if ($confirm) {
                    $user->givePermissionTo(self::NEW_PERMISSION);
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
            ? 'Done. "overdraw savings" created and granted additively -- nothing was removed.'
            : 'Dry run only — re-run with --confirm to apply.');

        return self::SUCCESS;
    }
}
