<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Services\LoanService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off correction (August 2026), superseding both
 * eltech:generate-loan-schedules-2026-07-31 and eltech:truncate-legacy-loan-schedules
 * for the loans this touches.
 *
 * Some legacy (31/07/2026 migration) loans' real remaining balance is the one
 * implied by their ORIGINAL disbursement-anchored amortization table (same
 * principal, rate, term, method as when the loan was actually disbursed) --
 * confirmed against LN-MK00106, where the reconciled outstanding_interest
 * (46,290) was far below the ~1.8M the original 1,506,931/month table implies
 * for the remaining periods. The reconciled figure was incomplete/stale for
 * these loans; the original table is authoritative.
 *
 * For each eligible loan this:
 *  1. Rebuilds the FULL amortization table from disbursement_date using
 *     LoanService::buildScheduleRows() (a pure calculation, no side effects) --
 *     the same monthly installment amount the client was always meant to pay.
 *  2. Drops every row dated before 01/08/2026 -- the client's balance was
 *     already transferred as of 31/07/2026, so those months must not appear.
 *  3. Sets outstanding_principal/outstanding_interest to the sum of the KEPT
 *     rows, so the loan's balance always matches its visible schedule exactly.
 *  4. Replaces the loan's current schedule with the kept rows, renumbered.
 *
 * A loan is left alone if: it has real repayments recorded (rebuilding would
 * orphan their allocation); its real maturity was already on/before
 * 31/07/2026 (an already-matured loan correctly has a single overdue lump
 * sum, not a multi-row table -- nothing to rebuild); the rebuilt table has
 * zero rows on/after the cutoff (same "would empty the schedule" guard); or --
 * critically -- the rebuilt table's remaining PRINCIPAL does not already
 * closely match the loan's current reconciled outstanding_principal.
 *
 * That last check matters: MK00106's remaining principal happened to already
 * match its clean disbursement-based table almost exactly (only interest was
 * wrong), which is what made trusting the table for that loan safe. Checking
 * against every other legacy loan shows most do NOT have that property --
 * their real off-system repayment history clearly diverged from a clean
 * monthly schedule (missed/uneven payments), so their reconciled principal is
 * the only trustworthy figure and the clean table would badly corrupt it.
 * Only loans where principal already agrees are rebuilt; everything else is
 * flagged for manual review, never guessed at.
 *
 * This is a real, sometimes large, upward revision of outstanding_interest on
 * the loans it does touch -- always preview and review the size of each
 * change before confirming.
 */
class RebuildLegacyLoanSchedulesFromDisbursement extends Command
{
    protected $signature = 'eltech:rebuild-legacy-loan-schedules {--confirm : Actually write changes; omit for a dry-run report}';
    protected $description = 'Rebuild legacy loans\' schedules from their original disbursement-anchored amortization table, truncated to 01/08/2026 onward, and reset outstanding balances to match';

    private const CUTOFF = '2026-08-01';
    private const PRINCIPAL_TOLERANCE = 5000.00;

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
        $skippedPrincipalMismatch = 0;

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
                    // Real maturity already fell on/before 31/07/2026 -- correctly a single overdue
                    // lump sum, not a multi-row table. Leave it alone.
                    $skippedAlreadyMatured++;
                    continue;
                }

                $rows = $loanService->buildScheduleRows($loan, Carbon::parse($loan->disbursement_date));
                $keep = collect($rows)->filter(fn ($r) => Carbon::parse($r['due_date'])->gte($cutoff))->values();

                if ($keep->isEmpty()) {
                    $this->warn("SKIP {$loan->loan_number}: rebuilt table has no installments on/after 01/08/2026, would empty the schedule.");
                    $skippedWouldEmpty++;
                    continue;
                }

                $newPrincipal = round($keep->sum('principal_due'), 2);
                $newInterest = round($keep->sum('interest_due'), 2);
                $oldPrincipal = (float) $loan->outstanding_principal;
                $oldInterest = (float) $loan->outstanding_interest;

                if (abs($newPrincipal - $oldPrincipal) > self::PRINCIPAL_TOLERANCE) {
                    $this->warn(sprintf(
                        "SKIP %s: clean-table remaining principal %s does not match reconciled outstanding_principal %s (diff %s) -- this loan's real repayment history diverged from a clean schedule, its reconciled balance is the trustworthy figure here, not the table. Needs manual review, not touching.",
                        $loan->loan_number,
                        number_format($newPrincipal, 2), number_format($oldPrincipal, 2),
                        number_format($newPrincipal - $oldPrincipal, 2)
                    ));
                    $skippedPrincipalMismatch++;
                    continue;
                }

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
            'Done. Rebuilt: %d | Skipped (has repayments): %d | Skipped (already matured on/before 31/07): %d | Skipped (would empty schedule): %d | Skipped (principal mismatch, needs review): %d | Skipped (bad data): %d',
            $rebuilt, $skippedRepay, $skippedAlreadyMatured, $skippedWouldEmpty, $skippedPrincipalMismatch, $skippedBadData
        ));

        if (!$confirm) {
            $this->line('');
            $this->info('Dry run only — re-run with --confirm to apply.');
        }

        return self::SUCCESS;
    }
}
