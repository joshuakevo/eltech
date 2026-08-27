<?php

namespace App\Console\Commands;

use App\Models\Loan;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Follow-up to eltech:regenerate-legacy-schedules-from-balance, for legacy
 * (31/07/2026 migration) loans that ALREADY have one or more repayments
 * recorded -- the base command skips those entirely to avoid touching real
 * money movements. This handles them safely by leaving anything with money
 * already applied completely untouched, and only recomputing the truly
 * unpaid ('pending'/'overdue') installments as a fresh amortization of the
 * loan's current outstanding_principal (already net of what's been paid).
 *
 * Confirmed example: LN-GK00075 has installment #1 already fully paid
 * (3,444,566 on 02/08/2026). Installments #2/#3 are still 'pending' at the
 * OLD reconciled interest split (111,233/month). This recomputes only #2/#3
 * from the current outstanding_principal (6,666,667 after the payment),
 * leaving installment #1 exactly as it is.
 *
 * Per-row rule:
 *  - status 'paid' -> untouched (fully settled, never rewritten).
 *  - status 'partial' -> the loan is SKIPPED ENTIRELY and flagged. A
 *    partially-paid row already carries its own principal_paid/interest_paid
 *    against its old due amounts; safely re-deriving how much of the current
 *    outstanding_principal that remainder represents (vs. what belongs to the
 *    still-untouched pending rows) needs a manual look, not a formula.
 *  - status 'pending'/'overdue' dated before 01/08/2026 -> deleted (the
 *    balance was already reconciled/transferred as of 31/07, so no unpaid
 *    installment before that should remain).
 *  - status 'pending'/'overdue' dated on/after 01/08/2026 -> kept, due dates
 *    unchanged, amounts recomputed as a fresh amortization of the current
 *    outstanding_principal over exactly that many remaining periods.
 *
 * outstanding_principal is left unchanged (trusted, and by construction the
 * recomputed rows' principal sums back to it). outstanding_interest is set
 * to the sum of the recomputed rows' interest (there is none left on 'paid'
 * rows, and loans with a 'partial' row are skipped rather than guessed at).
 */
class RegenerateLegacyPendingInstallmentsFromBalance extends Command
{
    protected $signature = 'eltech:regenerate-legacy-pending-installments {--confirm : Actually write changes; omit for a dry-run report}';
    protected $description = 'For legacy loans with existing repayments, recompute only the still-pending installments as a fresh amortization of the current balance, leaving paid rows untouched';

    private const CUTOFF = '2026-08-01';

    public function handle(): int
    {
        $confirm = (bool) $this->option('confirm');
        if (!$confirm) {
            $this->warn('DRY RUN — no changes will be saved. Re-run with --confirm to apply.');
        }
        $this->line('');

        $legacyPath = database_path('data/loan_terms_2026_07_31.json');
        if (!file_exists($legacyPath)) {
            $this->error("Legacy loan list not found at {$legacyPath}");
            return self::FAILURE;
        }
        $bundle = json_decode(file_get_contents($legacyPath), true) ?? [];
        $legacyLoanNumbers = collect($bundle)->map(fn ($row) => strtolower('LN-' . $row['client_number']))->all();

        $cutoff = Carbon::parse(self::CUTOFF);

        $rebuilt = 0;
        $skippedHasPartial = 0;
        $skippedNothingPending = 0;
        $skippedBadData = 0;

        $loans = Loan::with(['schedules' => fn ($q) => $q->orderBy('installment_no')])
            ->where('status', 'active')
            ->get()
            ->filter(fn ($loan) => in_array(strtolower($loan->loan_number), $legacyLoanNumbers, true))
            ->filter(fn ($loan) => $loan->repayments()->exists());

        DB::beginTransaction();
        try {
            foreach ($loans as $loan) {
                if ((float) $loan->outstanding_principal <= 0) {
                    $skippedNothingPending++;
                    continue;
                }

                if ($loan->schedules->contains(fn ($s) => $s->status === 'partial')) {
                    $this->warn("SKIP {$loan->loan_number}: has a partially-paid installment, needs manual review before recomputing.");
                    $skippedHasPartial++;
                    continue;
                }

                $locked = $loan->schedules->filter(fn ($s) => $s->status === 'paid');
                $candidates = $loan->schedules->filter(fn ($s) => in_array($s->status, ['pending', 'overdue'], true));

                $preAugust = $candidates->filter(fn ($s) => Carbon::parse($s->due_date)->lt($cutoff));
                $keep = $candidates->filter(fn ($s) => Carbon::parse($s->due_date)->gte($cutoff))->values();

                if ($keep->isEmpty()) {
                    $this->warn("SKIP {$loan->loan_number}: no pending installments on/after 01/08/2026 to recompute.");
                    $skippedNothingPending++;
                    continue;
                }

                if (!$loan->interest_method || (float) $loan->interest_rate <= 0) {
                    $this->warn("SKIP {$loan->loan_number}: missing interest_method/interest_rate, cannot recompute.");
                    $skippedBadData++;
                    continue;
                }

                $frequency = $loan->repayment_frequency ?? 'monthly';
                $step      = $frequency === 'quarterly' ? 3 : 1;

                $principal = round((float) $loan->outstanding_principal, 2);
                $rate      = (float) $loan->interest_rate / 100;
                $periods   = $keep->count();
                $dueDates  = $keep->map(fn ($s) => Carbon::parse($s->due_date))->values();
                $oldInterest = (float) $loan->outstanding_interest;

                $rows = $this->amortize($principal, $rate, $periods, $step, $loan->interest_method, $dueDates);
                $newInterest = round(collect($rows)->sum('interest_due'), 2);

                $this->line(sprintf(
                    '%s: %d paid (untouched) | removing %d stray pre-August unpaid row(s) | recomputing %d pending installment(s) from %s (%s/month, %s) | outstanding_principal %s (unchanged) | outstanding_interest %s -> %s',
                    $loan->loan_number,
                    $locked->count(),
                    $preAugust->count(),
                    $periods,
                    $rows[0]['due_date'],
                    number_format($rows[0]['total_due'], 2),
                    $loan->interest_method,
                    number_format($principal, 2),
                    number_format($oldInterest, 2), number_format($newInterest, 2)
                ));

                if ($confirm) {
                    foreach ($preAugust as $row) {
                        $row->delete();
                    }
                    foreach ($keep as $index => $scheduleRow) {
                        $new = $rows[$index];
                        $scheduleRow->principal_due  = $new['principal_due'];
                        $scheduleRow->interest_due   = $new['interest_due'];
                        $scheduleRow->total_due      = $new['total_due'];
                        $scheduleRow->balance_after  = $new['balance_after'];
                        $scheduleRow->save();
                    }
                    $loan->outstanding_interest = $newInterest;
                    $loan->save();
                }

                $rebuilt++;
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
            'Done. Rebuilt: %d | Skipped (has partial installment): %d | Skipped (nothing pending to recompute): %d | Skipped (bad data): %d',
            $rebuilt, $skippedHasPartial, $skippedNothingPending, $skippedBadData
        ));

        if (!$confirm) {
            $this->line('');
            $this->info('Dry run only — re-run with --confirm to apply.');
        }

        return self::SUCCESS;
    }

    /**
     * Same amortization math as RegenerateLegacyLoanSchedulesFromCurrentBalance.
     */
    private function amortize(float $principal, float $rate, int $periods, int $step, string $method, $dueDates): array
    {
        $rows = [];
        $balance = $principal;

        if ($method === 'flat') {
            $months = $periods * $step;
            $totalInterest = $principal * $rate * ($months / 12);
            $perPrincipal  = $principal / $periods;
            $perInterest   = $totalInterest / $periods;
            $principalSaved = 0;
            $interestSaved  = 0;

            for ($i = 1; $i <= $periods; $i++) {
                $balance -= $perPrincipal;

                if ($i === $periods) {
                    $pDue = round($principal - $principalSaved, 2);
                    $iDue = round($totalInterest - $interestSaved, 2);
                } else {
                    $pDue = round($perPrincipal, 2);
                    $iDue = round($perInterest, 2);
                }

                $principalSaved += $pDue;
                $interestSaved  += $iDue;

                $rows[] = [
                    'due_date'      => $dueDates[$i - 1]->toDateString(),
                    'principal_due' => $pDue,
                    'interest_due'  => $iDue,
                    'total_due'     => $pDue + $iDue,
                    'balance_after' => round(max($balance, 0), 2),
                ];
            }
        } else {
            $periodsPerYear = 12 / $step;
            $periodRate     = $rate / $periodsPerYear;

            if ($periodRate == 0) {
                $periodInstallment = $principal / $periods;
            } else {
                $periodInstallment = $principal * ($periodRate * pow(1 + $periodRate, $periods))
                    / (pow(1 + $periodRate, $periods) - 1);
            }

            $principalSaved = 0;

            for ($i = 1; $i <= $periods; $i++) {
                $interestDue  = $balance * $periodRate;
                $principalDue = $periodInstallment - $interestDue;

                if ($i === $periods) {
                    $pStored = round($principal - $principalSaved, 2);
                    $iStored = round($interestDue, 2);
                    $tStored = $pStored + $iStored;
                } else {
                    $pStored = round($principalDue, 2);
                    $iStored = round($interestDue, 2);
                    $tStored = round($periodInstallment, 2);
                }

                $balance -= $principalDue;
                $principalSaved += $pStored;

                $rows[] = [
                    'due_date'      => $dueDates[$i - 1]->toDateString(),
                    'principal_due' => $pStored,
                    'interest_due'  => $iStored,
                    'total_due'     => $tStored,
                    'balance_after' => round(max($balance, 0), 2),
                ];
            }
        }

        return $rows;
    }
}
