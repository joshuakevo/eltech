<?php

namespace App\Console\Commands;

use App\Services\AccountingService;
use Illuminate\Console\Command;

/**
 * Read-only diagnostic for "the Income Statement segment filter always
 * returns zero" -- see AccountingService::diagnoseSegmentCoverage(). Prints
 * why every revenue/expense transaction line does or doesn't show up under a
 * segment filter: unresolvable client, resolved client with no segment, or
 * resolved + segmented (broken down by segment). Never writes anything.
 */
class DiagnoseSegmentIncomeStatement extends Command
{
    protected $signature = 'eltech:diagnose-segment-income-statement {--from=} {--to=}';
    protected $description = 'Read-only: shows how revenue/expense transaction lines resolve to clients and segments';

    public function handle(AccountingService $accounting): int
    {
        $from = $this->option('from');
        $to   = $this->option('to');

        $this->info('Diagnosing revenue/expense transaction lines' . ($from || $to ? " from {$from} to {$to}" : ' (all time)') . '...');

        $result = $accounting->diagnoseSegmentCoverage($from, $to);

        $this->line("Total revenue/expense lines examined: {$result['total_lines']} (amount: " . number_format($result['total_amount'], 2) . ')');
        $this->line("No resolvable client at all: {$result['unresolved_client_lines']} line(s), amount " . number_format($result['unresolved_client_amount'], 2) . ' -- these can NEVER show under any segment filter.');
        if (!empty($result['unresolved_by_account'])) {
            $this->line('  Broken down by account (this is the list to check against what the Income Statement is actually showing):');
            foreach ($result['unresolved_by_account'] as $account => $row) {
                $modules = collect($row['modules'])->map(fn($n, $m) => "{$m}:{$n}")->implode(', ');
                $this->line("    - {$account}: {$row['lines']} line(s), amount " . number_format($row['amount'], 2) . " [modules: {$modules}] e.g. \"{$row['sample_description']}\"");
            }
        }
        $this->line("Resolved client but client has NO segment set: {$result['resolved_no_segment_lines']} line(s), amount " . number_format($result['resolved_no_segment_amount'], 2) . ' -- these can NEVER show under any segment filter either.');

        if (empty($result['by_segment'])) {
            $this->warn('No lines resolved to a client WITH a segment at all -- this is why every segment filter on the Income Statement returns zero.');
        } else {
            $this->line('Resolved to a client WITH a segment, broken down by segment:');
            foreach ($result['by_segment'] as $row) {
                $this->line("  - {$row['name']}: {$row['lines']} line(s), amount " . number_format($row['amount'], 2));
            }
        }

        return self::SUCCESS;
    }
}
