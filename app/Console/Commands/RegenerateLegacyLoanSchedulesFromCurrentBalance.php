<?php

namespace App\Console\Commands;

use App\Models\Loan;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off correction (August 2026), superseding
 * eltech:generate-loan-schedules-2026-07-31, eltech:truncate-legacy-loan-schedules
 * and eltech:rebuild-legacy-loan-schedules for legacy (31/07/2026 migration) loans.
 *
 * Confirmed decision: for these loans, the outstanding_principal reconciled on
 * 31/07/2026 is trusted as-is. The remaining schedule (August onward) is a
 * FRESH amortization of that current balance -- as if the loan were
 * re-disbursed today for principal = outstanding_principal, at the loan's
 * real interest_rate/interest_method, over the periods remaining to its
 * original maturity_date. This deliberately replaces outstanding_interest
 * with whatever that fresh calculation produces (which can be well above the
 * previously reconciled figure -- confirmed acceptable for these loans, since
 * the amortization math, not the old reconciled interest figure, is what's
 * authoritative going forward).
 *
 * This does NOT do naive even-division (that's what
 * eltech:generate-loan-schedules-2026-07-31 wrongly did, shrinking the
 * installment) and does NOT try to match the reconciled balance against the
 * loan's original full disbursement-anchored table (that's what
 * eltech:rebuild-legacy-loan-schedules did, which only worked for loans whose
 * real repayment history happened to track a clean table). Instead it always
 * uses the CURRENT outstanding_principal as the amortization base, so it
 * applies uniformly without needing to flag most loans for manual review.
 *
 * Remaining due dates are taken from the loan's ORIGINAL disbursement-anchored
 * date grid (same cadence/day-of-month the client has always seen), filtered
 * to on/after 01/08/2026 -- only the DATES are borrowed from that table, not
 * the amounts.
 *
 * A loan is skipped if: it has real repayments recorded; it's missing
 * disbursement_date/maturity_date/principal/term_months; outstanding_principal
 * is not a positive number; its real maturity was already on/before
 * 31/07/2026 (a single overdue lump sum, nothing to spread); or the original
 * date grid has zero due dates on/after 01/08/2026 (would empty the schedule).
 */
class RegenerateLegacyLoanSchedulesFromCurrentBalance extends Command
{
    protected $signature = 'eltech:regenerate-legacy-schedules-from-balance {--confirm : Actually write changes; omit for a dry-run report}';
    protected $description = 'Rebuild legacy loans\' August-onward schedule as a fresh amortization of the current outstanding_principal over the periods remaining to maturity';

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
        $skippedRepay = 0;
        $skippedAlreadyMatured = 0;
        $skippedWouldEmpty = 0;
        $skippedBadData = 0;

        $loans = Loan::where('status', 'active')
            ->get()
            ->filter(fn ($loan) => in_array(strtolower($loan->loan_number), $legacyLoanNumbers, true));

        DB::beginTransaction();
        try {
            foreach ($loans as $loan) {
                if ($loan->repayments()->exists()) {
                    $skippedRepay++;
                    continue;
                }

                if (!$loan->disbursement_date || !$loan->maturity_date || (float) $loan->principal <= 0 || (int) $loan->term_months <= 0) {
                    $this->warn("SKIP {$loan->loan_number}: missing disbursement_date/maturity_date/principal/term_months, cannot rebuild.");
                    $skippedBadData++;
                    continue;
                }

                if ((float) $loan->outstanding_principal <= 0) {
                    $this->warn("SKIP {$loan->loan_number}: outstanding_principal is zero or missing, nothing to schedule.");
                    $skippedBadData++;
                    continue;
                }

                if (Carbon::parse($loan->maturity_date)->lte($cutoff->copy()->subDay())) {
                    // Real maturity already fell on/before 31/07/2026 -- correctly a single overdue
                    // lump sum, not a multi-row table. Leave it alone.
                    $skippedAlreadyMatured++;
                    continue;
                }

                $frequency = $loan->repayment_frequency ?? 'monthly';
                $step      = $frequency === 'quarterly' ? 3 : 1;
                $totalPeriods = intval($loan->term_months / $step);

                $disbursement = Carbon::parse($loan->disbursement_date);
                $dueDates = collect(range(1, $totalPeriods))
                    ->map(fn ($i) => $disbursement->copy()->addMonths($i * $step))
                    ->filter(fn (Carbon $d) => $d->gte($cutoff))
                    ->values();

                if ($dueDates->isEmpty()) {
                    $this->warn("SKIP {$loan->loan_number}: original schedule grid has no due dates on/after 01/08/2026, would empty the schedule.");
                    $skippedWouldEmpty++;
                    continue;
                }

                $periods   = $dueDates->count();
                $principal = round((float) $loan->outstanding_principal, 2);
                $rate      = (float) $loan->interest_rate / 100;
                $oldInterest = (float) $loan->outstanding_interest;

                $rows = $this->amortize($principal, $rate, $periods, $step, $loan->interest_method, $dueDates);

                $newInterest = round(collect($rows)->sum('interest_due'), 2);

                $this->line(sprintf(
                    '%s: %d installment(s) from %s (%s/month, %s) | outstanding_principal %s (unchanged) | outstanding_interest %s -> %s',
                    $loan->loan_number,
                    $periods,
                    $rows[0]['due_date'],
                    number_format($rows[0]['total_due'], 2),
                    $loan->interest_method,
                    number_format($principal, 2),
                    number_format($oldInterest, 2), number_format($newInterest, 2)
                ));

                if ($confirm) {
                    $loan->schedules()->delete();
                    foreach ($rows as $index => $row) {
                        $loan->schedules()->create([
                            'installment_no' => $index + 1,
                            'due_date'       => $row['due_date'],
                            'principal_due'  => $row['principal_due'],
                            'interest_due'   => $row['interest_due'],
                            'total_due'      => $row['total_due'],
                            'balance_after'  => $row['balance_after'],
                            'principal_paid' => 0,
                            'interest_paid'  => 0,
                            'status'         => 'pending',
                        ]);
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
            'Done. Rebuilt: %d | Skipped (has repayments): %d | Skipped (already matured on/before 31/07): %d | Skipped (would empty schedule): %d | Skipped (bad data): %d',
            $rebuilt, $skippedRepay, $skippedAlreadyMatured, $skippedWouldEmpty, $skippedBadData
        ));

        if (!$confirm) {
            $this->line('');
            $this->info('Dry run only — re-run with --confirm to apply.');
        }

        return self::SUCCESS;
    }

    /**
     * Fresh amortization of $principal over $periods installments at annual
     * rate $rate, using $method ('flat' or 'reducing'), assigned to the given
     * due dates in order. Mirrors LoanService::buildScheduleRows()'s math,
     * but with principal/period-count taken from the current balance and
     * remaining term rather than the loan's original principal/term_months.
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
