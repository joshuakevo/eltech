<?php

namespace App\Console\Commands;

use App\Models\Loan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off repair (August 2026) for damage caused by re-running
 * eltech:fix-loan-interest-rates a second time on 2026-08-27. That command's
 * safety check compares a loan's rate to its product's default rate, which
 * cannot tell "still monthly" from "legitimately custom annual" apart -- the
 * second run multiplied every repayment-free loan's interest_rate by 12
 * again, and for a few newer loans (created after the 31/07/2026 migration,
 * so not covered by the legacy-loan skip) it also regenerated their schedule
 * from that doubled rate, corrupting outstanding_interest.
 *
 * database/data/loan_rate_baseline_2026_08_27.json holds the exact
 * interest_rate / outstanding_principal / outstanding_interest for every
 * affected loan as captured in a database backup taken 2026-08-27 12:56:04 --
 * after the first, correct run of the interest-rate fix (2026-08-25) but
 * before the second, harmful one (2026-08-27 18:26). It is ground truth, not
 * a guessed formula.
 *
 * For every loan in the baseline:
 *  - interest_rate is restored whenever it differs (always safe -- the rate
 *    doesn't change from repayments).
 *  - outstanding_principal/outstanding_interest are restored only when the
 *    loan currently has zero recorded repayments, since a real repayment
 *    made after the baseline snapshot legitimately changed those figures and
 *    must not be clobbered.
 *
 * LN-NK00221 needs one more step: the "LN-NK00221 Term Fix" ran *after* the
 * harmful second run and rebuilt its single loan_schedules row using the
 * already-corrupted outstanding_interest. Once the loan's outstanding_interest
 * is restored here, that one schedule row is corrected to match.
 */
class RestoreLoanRatesFromBaseline extends Command
{
    protected $signature = 'eltech:restore-loan-rates-from-baseline {--confirm : Actually write changes; omit for a dry-run report}';
    protected $description = 'Restore loan interest rates (and, where safe, outstanding balances) corrupted by a second run of the loan interest rate fix, using the 2026-08-27 12:56 backup as ground truth';

    private const BASELINE_PATH = 'data/loan_rate_baseline_2026_08_27.json';
    private const NK00221_SCHEDULE_MATURITY = '2026-07-13';

    public function handle(): int
    {
        $confirm = (bool) $this->option('confirm');
        if (!$confirm) {
            $this->warn('DRY RUN — no changes will be saved. Re-run with --confirm to apply.');
        }
        $this->line('');

        $path = database_path(self::BASELINE_PATH);
        if (!file_exists($path)) {
            $this->error("Baseline file not found: {$path}");
            return self::FAILURE;
        }
        $baseline = json_decode(file_get_contents($path), true) ?? [];

        $rateFixed = 0;
        $balanceFixed = 0;
        $balanceSkippedHasRepayments = 0;
        $notFound = 0;
        $alreadyCorrect = 0;

        DB::beginTransaction();
        try {
            foreach ($baseline as $loanNumber => $correct) {
                $loan = Loan::where('loan_number', $loanNumber)->first();
                if (!$loan) {
                    $this->warn("SKIP {$loanNumber}: loan not found.");
                    $notFound++;
                    continue;
                }

                $changed = false;

                $currentRate = (float) $loan->interest_rate;
                $correctRate = (float) $correct['interest_rate'];
                if (abs($currentRate - $correctRate) > 0.0001) {
                    $this->line(sprintf('%s: interest_rate %s -> %s', $loanNumber, number_format($currentRate, 4), number_format($correctRate, 4)));
                    $loan->interest_rate = $correctRate;
                    $changed = true;
                    $rateFixed++;
                }

                $hasRepayments = $loan->repayments()->exists();
                $currentPrincipal = (float) $loan->outstanding_principal;
                $currentInterest = (float) $loan->outstanding_interest;
                $correctPrincipal = (float) $correct['outstanding_principal'];
                $correctInterest = (float) $correct['outstanding_interest'];
                $principalDiff = abs($currentPrincipal - $correctPrincipal) > 0.01;
                $interestDiff = abs($currentInterest - $correctInterest) > 0.01;

                if ($principalDiff || $interestDiff) {
                    if ($hasRepayments) {
                        $this->warn("SKIP balance restore for {$loanNumber}: has real repayments recorded since the baseline, not touching outstanding_principal/outstanding_interest.");
                        $balanceSkippedHasRepayments++;
                    } else {
                        $this->line(sprintf(
                            '%s: outstanding_principal %s -> %s | outstanding_interest %s -> %s',
                            $loanNumber,
                            number_format($currentPrincipal, 2), number_format($correctPrincipal, 2),
                            number_format($currentInterest, 2), number_format($correctInterest, 2)
                        ));
                        $loan->outstanding_principal = $correctPrincipal;
                        $loan->outstanding_interest = $correctInterest;
                        $changed = true;
                        $balanceFixed++;

                        if ($loanNumber === 'LN-NK00221') {
                            $this->fixNk00221Schedule($loan, $correctPrincipal, $correctInterest, $confirm);
                        }
                    }
                }

                if (!$changed) {
                    $alreadyCorrect++;
                    continue;
                }

                if ($confirm) {
                    $loan->save();
                }
            }

            if ($confirm) {
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->line('');
        $this->info(sprintf(
            'Done. Rates restored: %d | Balances restored: %d | Balances skipped (has repayments): %d | Already correct: %d | Not found: %d',
            $rateFixed, $balanceFixed, $balanceSkippedHasRepayments, $alreadyCorrect, $notFound
        ));

        if (!$confirm) {
            $this->line('');
            $this->info('Dry run only — re-run with --confirm to apply.');
        }

        return self::SUCCESS;
    }

    private function fixNk00221Schedule(Loan $loan, float $principal, float $interest, bool $confirm): void
    {
        $schedule = $loan->schedules()->first();
        if (!$schedule) {
            $this->warn('LN-NK00221: no loan_schedules row found, skipping schedule fix.');
            return;
        }

        $total = $principal + $interest;
        $this->line(sprintf(
            'LN-NK00221 schedule: interest_due %s -> %s | total_due %s -> %s',
            number_format((float) $schedule->interest_due, 2), number_format($interest, 2),
            number_format((float) $schedule->total_due, 2), number_format($total, 2)
        ));

        if ($confirm) {
            $schedule->principal_due = $principal;
            $schedule->interest_due = $interest;
            $schedule->total_due = $total;
            $schedule->balance_after = $principal;
            $schedule->save();
        }
    }
}
