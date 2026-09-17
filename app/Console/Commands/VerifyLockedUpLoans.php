<?php

namespace App\Console\Commands;

use App\Models\Loan;
use Illuminate\Console\Command;

/**
 * Checks that a deployment's Locked-Up Loans book matches the old system's
 * Lock Up Report (18 Aug 2026) exactly -- the same 76-row bundle that
 * ImportJuly2026LockedUpLoans used, kept here as the single source of truth
 * for what these 76 loans' principal/interest are supposed to be.
 *
 * Several of these loans carry a NEGATIVE principal or interest by design --
 * a credit, where the member overpaid rather than owes. Those are never
 * floored to zero here; only actual repayments (via LoanService) reduce a
 * balance toward zero.
 *
 * Run this after deploying to a new/other environment (e.g. production) to
 * confirm its data matches, without needing direct DB access from outside.
 * Safe by default: reports only. --fix corrects outstanding_principal /
 * outstanding_interest on loans that already exist but drifted (e.g. hit by
 * the old ReconcileData bug that zeroed interest on schedule-less loans),
 * and reopens any that the same bug incorrectly auto-closed (it treated a
 * bug-zeroed interest as "nothing outstanding" and closed loans that still
 * genuinely owed/were owed money). It never creates loans or clients, since
 * a genuinely missing loan needs a human decision, not an automatic
 * client-creation on production.
 */
class VerifyLockedUpLoans extends Command
{
    protected $signature   = 'eltech:verify-locked-up-loans {--fix : Correct outstanding_principal/outstanding_interest drift and wrongly-closed status on existing loans}';
    protected $description = 'Compare this environment\'s Locked-Up Loans against the old system\'s Lock Up Report (31/07/2026 bundle) and report/fix drift';

    public function handle(): int
    {
        $fix  = $this->option('fix');
        $path = database_path('data/locked_up_loans_2026_07_31.json');

        if (!file_exists($path)) {
            $this->error("Data bundle not found at {$path}");
            return self::FAILURE;
        }

        $bundle = json_decode(file_get_contents($path), true);

        $missing  = [];
        $drifted  = [];
        $ok       = 0;

        foreach ($bundle as $row) {
            $loanNumber = 'LU-' . $row['fcode'];
            $loan       = Loan::where('loan_number', $loanNumber)->first();

            if (!$loan) {
                $missing[] = $row;
                continue;
            }

            // A loan with repayments recorded has legitimately moved below the
            // opening balance -- that's expected, not drift. Reconstruct the
            // opening figures from what's left plus what's already been paid.
            $paidPrincipal = (float) $loan->repayments()->sum('principal_paid');
            $paidInterest  = (float) $loan->repayments()->sum('interest_paid');

            $openingPrincipal = (float) $loan->outstanding_principal + $paidPrincipal;
            $openingInterest  = (float) $loan->outstanding_interest  + $paidInterest;

            $expectedPrincipal = (float) $row['lnbal'];
            $expectedInterest  = (float) $row['intbal'];

            $principalOk = abs($openingPrincipal - $expectedPrincipal) < 0.5;
            $interestOk  = abs($openingInterest  - $expectedInterest)  < 0.5;

            // A loan should only be closed once nothing more is owed/credited in
            // either direction. If it still has a genuine (possibly negative,
            // i.e. credit) balance after accounting for repayments, it was
            // wrongly closed -- almost certainly by the old reconcile bug, which
            // treated its bug-zeroed interest as "nothing outstanding".
            $remainingPrincipal = $expectedPrincipal - $paidPrincipal;
            $remainingInterest  = $expectedInterest  - $paidInterest;
            $shouldBeClosed     = abs($remainingPrincipal) < 0.5 && abs($remainingInterest) < 0.5;
            $statusOk           = $shouldBeClosed
                ? true // closed or still open is fine once genuinely settled
                : $loan->status !== 'closed';

            if ($principalOk && $interestOk && $statusOk) {
                $ok++;
                continue;
            }

            $drifted[] = [
                'loan'               => $loan,
                'row'                => $row,
                'openingPrincipal'   => $openingPrincipal,
                'openingInterest'    => $openingInterest,
                'expectedPrincipal'  => $expectedPrincipal,
                'expectedInterest'   => $expectedInterest,
                'remainingPrincipal' => $remainingPrincipal,
                'remainingInterest'  => $remainingInterest,
                'principalOk'        => $principalOk,
                'interestOk'         => $interestOk,
                'statusOk'           => $statusOk,
            ];
        }

        $this->line('');
        $this->info("Checked " . count($bundle) . " locked-up loans from the old system's Lock Up Report.");
        $this->line("  Matching:  {$ok}");
        $this->line("  Drifted:   " . count($drifted));
        $this->line("  Missing:   " . count($missing));
        $this->line('');

        if ($drifted) {
            $this->warn('DRIFTED (exist here, but figures and/or status don\'t reconcile to the old report):');
            foreach ($drifted as $d) {
                $loan = $d['loan'];
                $this->line(sprintf(
                    '  %s (%s): principal opening=%s expected=%s%s | interest opening=%s expected=%s%s%s',
                    $loan->loan_number,
                    $d['row']['name'],
                    number_format($d['openingPrincipal']),
                    number_format($d['expectedPrincipal']),
                    $d['principalOk'] ? '' : '  <-- MISMATCH',
                    number_format($d['openingInterest']),
                    number_format($d['expectedInterest']),
                    $d['interestOk'] ? '' : '  <-- MISMATCH',
                    $d['statusOk'] ? '' : "  | status={$loan->status} <-- WRONGLY CLOSED"
                ));

                if ($fix) {
                    $updates = [
                        // Not floored at 0 -- several of these are legitimate
                        // credits (negative balances), not debts.
                        'outstanding_principal' => $d['remainingPrincipal'],
                        'outstanding_interest'  => $d['remainingInterest'],
                    ];
                    if (!$d['statusOk']) {
                        $updates['status'] = 'defaulted';
                    }
                    $loan->update($updates);
                    $this->line('    -> fixed');
                }
            }
            $this->line('');
        }

        if ($missing) {
            $this->warn('MISSING (no loan record found at all -- not auto-created; investigate or re-run the import):');
            foreach ($missing as $row) {
                $this->line(sprintf(
                    '  LU-%s  %s  principal=%s  interest=%s',
                    $row['fcode'],
                    $row['name'],
                    number_format((float) $row['lnbal']),
                    number_format((float) $row['intbal'])
                ));
            }
            $this->line('');
            $this->line('  To bring these in: php artisan eltech:import-locked-up-loans-2026-07-31 --confirm');
            $this->line('  (safe to re-run -- it only inserts rows for fcodes not already present as LU-<fcode> loans)');
        }

        if (!$drifted && !$missing) {
            $this->info('Everything matches the old system exactly.');
        } elseif ($drifted && !$fix) {
            $this->line('');
            $this->line('Re-run with --fix to correct the drifted figures/status above.');
        }

        return self::SUCCESS;
    }
}
