<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * One-time follow-up for installs that ran eltech:import-client-segments-rm-2026-08
 * before the is_relationship_manager flag existed on users -- those RM users were
 * created without it, so they'd silently disappear from the Clients RM dropdown
 * once that dropdown started filtering on the flag. Flags any user currently set
 * as relationship_manager_id on at least one client, rather than hardcoding names,
 * so it stays correct regardless of which RM users actually exist. Idempotent.
 */
class FlagExistingRelationshipManagers2026Aug extends Command
{
    protected $signature = 'eltech:flag-existing-rms-2026-08 {--confirm : Required to actually run this}';
    protected $description = 'Set is_relationship_manager=true for every user already assigned as a client\'s relationship manager';

    public function handle(): int
    {
        if (!$this->option('confirm')) {
            $this->error('Re-run with --confirm to proceed.');
            return self::FAILURE;
        }

        $userIds = Client::whereNotNull('relationship_manager_id')->distinct()->pluck('relationship_manager_id');

        $updated = User::whereIn('id', $userIds)
            ->where('is_relationship_manager', false)
            ->update(['is_relationship_manager' => true]);

        $this->info("Flagged {$updated} user(s) as Relationship Managers.");

        foreach (User::whereIn('id', $userIds)->orderBy('name')->pluck('name') as $name) {
            $this->line("  - {$name}");
        }

        return self::SUCCESS;
    }
}
