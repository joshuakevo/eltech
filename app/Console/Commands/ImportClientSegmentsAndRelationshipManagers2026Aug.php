<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ClientSegment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * One-time import: populates client_segments and each client's segment_id /
 * relationship_manager_id from the legacy system's client export
 * (database/data/2026-08-client-segments-rm-import.csv, derived from the
 * user-supplied Segments.xlsx).
 *
 * Matching: primarily by client_number with the legacy 'X' prefix(es)
 * stripped (the live system's numbers, e.g. BK00038, match the legacy
 * numbers once stripped, e.g. XXBK00038); falls back to an exact
 * case-insensitive name match for the handful of rows that don't carry a
 * live-format number. Rows that match neither are skipped and reported.
 *
 * ~70 rows had the literal word "CLOSE" typed into the Relationship
 * Manager column in the old system -- clearly a marker that the account
 * should be closed, not a real RM name. This command does NOT close
 * anything automatically (closing needs a per-account zero-balance check);
 * it only reports which matched clients were CLOSE-flagged so they can be
 * reviewed and closed via the new Close Accounts admin page.
 *
 * Idempotent: safe to re-run (firstOrCreate for segments/users, plain
 * column assignment for clients).
 */
class ImportClientSegmentsAndRelationshipManagers2026Aug extends Command
{
    protected $signature = 'eltech:import-client-segments-rm-2026-08 {--confirm : Required to actually run this}';
    protected $description = 'Import client segments and relationship managers from the 2026-08 legacy export';

    /** Segment names that are real segments (others in the raw data are data-entry noise). */
    protected const VALID_SEGMENTS = ['Konnect Sacco', 'Konnect Businness', 'KDF', 'Venture Capital', 'Option 1'];

    /** Canonicalizes RM name typo variants seen in the raw export onto one spelling. */
    protected const RM_NAME_ALIASES = [
        'Brooklyn Musiinguzi' => 'Brooklyn Musinguzi',
        'Deborah Aviniya'     => 'Deborah Avinyia',
    ];

    public function handle(): int
    {
        if (!$this->option('confirm')) {
            $this->error('Re-run with --confirm to proceed.');
            return self::FAILURE;
        }

        $path = database_path('data/2026-08-client-segments-rm-import.csv');
        if (!file_exists($path)) {
            $this->error("Data file not found at {$path}");
            return self::FAILURE;
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($path));
        $rows    = array_map('str_getcsv', explode("\n", trim($content)));
        $header  = array_map('trim', array_shift($rows));

        $segmentsCreated = 0;
        $usersCreated    = 0;
        $clientsUpdated  = 0;
        $unmatched       = [];
        $closeFlagged    = [];

        DB::transaction(function () use ($rows, $header, &$segmentsCreated, &$usersCreated, &$clientsUpdated, &$unmatched, &$closeFlagged) {
            foreach ($rows as $row) {
                if (count($row) < count($header)) {
                    continue;
                }
                $r = array_combine($header, $row);

                $rawNumber = trim($r['client_number'] ?? '');
                $rawName   = trim($r['name'] ?? '');
                $rawSegment = trim($r['segment'] ?? '');
                $rawRm      = trim($r['rm'] ?? '');

                if ($rawNumber === '' || $rawNumber === 'TOTAL') {
                    continue;
                }

                $number = ltrim($rawNumber, 'X');

                $client = Client::where('client_number', $number)->first();
                if (!$client && $rawName !== '' && strcasecmp($rawName, 'CLOSE') !== 0) {
                    $client = Client::whereRaw('LOWER(name) = ?', [strtolower($rawName)])->first();
                }

                if (!$client) {
                    $unmatched[] = "{$rawNumber} | {$rawName}";
                    continue;
                }

                $updates = [];

                if (in_array($rawSegment, self::VALID_SEGMENTS, true)) {
                    $segment = ClientSegment::firstOrCreate(
                        ['name' => $rawSegment],
                        ['is_active' => true]
                    );
                    if ($segment->wasRecentlyCreated) {
                        $segmentsCreated++;
                    }
                    $updates['segment_id'] = $segment->id;
                }

                if ($rawRm !== '' && strcasecmp($rawRm, 'CLOSE') !== 0) {
                    $rmName = self::RM_NAME_ALIASES[$rawRm] ?? $rawRm;
                    $user = User::whereRaw('LOWER(name) = ?', [strtolower($rmName)])->first();
                    if (!$user) {
                        $slug  = Str::slug($rmName, '.');
                        $email = "{$slug}@eltechfinance.local";
                        $user  = User::create([
                            'name'      => $rmName,
                            'email'     => $email,
                            'password'  => Hash::make(Str::random(32)),
                            'is_active' => true,
                        ]);
                        $user->assignRole('staff');
                        $usersCreated++;
                    }
                    $updates['relationship_manager_id'] = $user->id;
                }

                if (strcasecmp($rawRm, 'CLOSE') === 0) {
                    $closeFlagged[] = "{$client->client_number} | {$client->name}";
                }

                if ($updates) {
                    $client->update($updates);
                    $clientsUpdated++;
                }
            }
        });

        $this->info("Segments created: {$segmentsCreated}");
        $this->info("Relationship manager users created: {$usersCreated}");
        $this->info("Clients updated: {$clientsUpdated}");

        if ($closeFlagged) {
            $this->warn(count($closeFlagged) . ' client(s) were flagged CLOSE in the legacy export — review and close their accounts via Administration > Close Accounts:');
            foreach ($closeFlagged as $c) {
                $this->line("  - {$c}");
            }
        }

        if ($unmatched) {
            $this->warn(count($unmatched) . ' row(s) could not be matched to an existing client:');
            foreach ($unmatched as $u) {
                $this->line("  - {$u}");
            }
        }

        return self::SUCCESS;
    }
}
