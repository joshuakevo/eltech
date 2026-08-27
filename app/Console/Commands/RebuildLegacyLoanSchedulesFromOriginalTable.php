<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Services\LoanService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Final, confirmed policy (August 2026) for legacy (31/07/2026 migration)
 * loans with ZERO repayments recorded: the client compares the new system's
 * Repayment Schedule against the OLD system, so the installment amounts must
 * match the OLD system's -- i.e. the loan's ORIGINAL disbursement-anchored
 * amortization table (same principal/rate/term/method as when the loan was
 * actually disbursed), truncated to drop any month before 01/08/2026.
 *
 * This is a deliberate reversal of the "reconcile against the 31/07 balance"
 * approach used by eltech:regenerate-legacy-schedules-from-balance and its
 * predecessor eltech:rebuild-legacy-loan-schedules (which only rebuilt a
 * loan when the original table's implied remaining principal already closely
 * matched the reconciled outstanding_principal). Confirmed on LN-GK00072:
 * the original table implies 7,500,000 principal / 2,520,000 interest
 * remaining across the 6 months from August, while the reconciled balance
 * was 9,412,520 / 190,623 -- a real, large gap. The decision is to trust the
 * original table anyway and NOT reconcile the difference; outstanding_
 * principal/outstanding_interest are simply overwritten to match the kept
 * table rows, superseding whatever value any earlier command left there.
 *
 * A loan is left alone if: it has real repayments (handled instead by
 * eltech:rebuild-legacy-pending-original-table); it's missing disbursement_
 * date/maturity_date/principal/term_months; its real maturity already fell
 * on/before 31/07/2026 (a single overdue lump sum, nothing to rebuild); or
 * the original table has zero installments on/after 01/08/2026 (would empty
 * the schedule).
 */
class RebuildLegacyLoanSchedulesFromOriginalTable extends Command
{
    protected $signature = 'eltech:rebuild-legacy-schedules-original-table {--confirm : Actually write changes; omit for a dry-run report}';
    protected $description = 'Rebuild legacy loans\' (zero-repayment) schedules to match the OLD system exactly: the original disbursement-anchored table, truncated to 01/08/2026 onward';

    private const CUTOFF = '2026-08-01';

    public function handle(LoanService $loanService): int
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

                if (Carbon::parse($loan->maturity_date)->lte($cutoff->copy()->subDay())) {
                    $skippedAlreadyMatured++;
                    continue;
                }

                $rows = $loanService->buildScheduleRows($loan, Carbon::parse($loan->disbursement_date));
                $keep = collect($rows)->filter(fn ($r) => Carbon::parse($r['due_date'])->gte($cutoff))->values();

                if ($keep->isEmpty()) {
                    $this->warn("SKIP {$loan->loan_number}: original table has no installments on/after 01/08/2026, would empty the schedule.");
                    $skippedWouldEmpty++;
                    continue;
                }

                $newPrincipal = round($keep->sum('principal_due'), 2);
                $newInterest = round($keep->sum('interest_due'), 2);
                $oldPrincipal = (float) $loan->outstanding_principal;
                $oldInterest = (float) $loan->outstanding_interest;

                $this->line(sprintf(
                    '%s: %d installment(s) from %s (%s/month) | outstanding_principal %s -> %s | outstanding_interest %s -> %s',
                    $loan->loan_number,
                    $keep->count(),
                    $keep->first()['due_date'],
                    number_format($keep->first()['total_due'], 2),
                    number_format($oldPrincipal, 2), number_format($newPrincipal, 2),
                    number_format($oldInterest, 2), number_format($newInterest, 2)
                ));

                if ($confirm) {
                    $loan->schedules()->delete();
                    foreach ($keep as $index => $row) {
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
                    $loan->outstanding_principal = $newPrincipal;
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
}
