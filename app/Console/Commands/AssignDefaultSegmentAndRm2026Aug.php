<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ClientSegment;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * One-time follow-up to eltech:import-client-segments-rm-2026-08. That import
 * only sets segment/RM for clients present in the legacy export with a
 * non-blank value -- 109 clients were left with no segment_id (64 blank in
 * the legacy export itself, 45 not in the export at all) and 122 with no
 * relationship_manager_id, for the same two reasons.
 *
 * Rather than leave them unassigned, this assigns every remaining
 * segment-less client to "Konnect Sacco" (already the dominant segment --
 * 272 of the original 511 legacy rows) and every remaining RM-less client to
 * Shaddai Kamoga -- both explicit choices made by the org owner, not
 * inferred. Idempotent: only ever touches NULL columns, safe to re-run (a
 * second run is a no-op once nothing is left unassigned).
 */
class AssignDefaultSegmentAndRm2026Aug extends Command
{
    protected $signature = 'eltech:assign-default-segment-rm-2026-08 {--confirm : Required to actually run this}';
    protected $description = 'Assign the Konnect Sacco segment and Shaddai Kamoga as RM to any client still missing one after the 2026-08 legacy import';

    protected const DEFAULT_SEGMENT_NAME = 'Konnect Sacco';
    protected const DEFAULT_RM_NAME      = 'Shaddai Kamoga';

    public function handle(): int
    {
        if (!$this->option('confirm')) {
            $this->error('Re-run with --confirm to proceed.');
            return self::FAILURE;
        }

        $segment = ClientSegment::where('name', self::DEFAULT_SEGMENT_NAME)->first();
        if (!$segment) {
            $this->error('Segment "' . self::DEFAULT_SEGMENT_NAME . '" not found. Run eltech:import-client-segments-rm-2026-08 first.');
            return self::FAILURE;
        }

        $rm = User::where('name', self::DEFAULT_RM_NAME)->first();
        if (!$rm) {
            $this->error('User "' . self::DEFAULT_RM_NAME . '" not found. Run eltech:import-client-segments-rm-2026-08 first.');
            return self::FAILURE;
        }

        $segmentCount = Client::whereNull('segment_id')->count();
        Client::whereNull('segment_id')->update(['segment_id' => $segment->id]);

        $rmCount = Client::whereNull('relationship_manager_id')->count();
        Client::whereNull('relationship_manager_id')->update(['relationship_manager_id' => $rm->id]);

        $this->info("Assigned {$segment->name} to {$segmentCount} previously segment-less client(s).");
        $this->info("Assigned {$rm->name} as RM to {$rmCount} previously unassigned client(s).");

        return self::SUCCESS;
    }
}
