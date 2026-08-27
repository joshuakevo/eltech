<?php

namespace App\Console\Commands;

use App\Models\Loan;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off correction (August 2026) for legacy loans (the 54 in
 * loan_terms_2026_07_31.json) whose schedule got wrongly rebuilt from the
 * loan's real disbursement date -- e.g. LN-MK00106 ended up with 12
 * installments starting May 2026 instead of the ~9 remaining from August
 * 2026 to maturity. The client's balance was already reconciled and
 * transferred as of 31/07/2026, so months before August must not appear in
 * the schedule at all -- but re-spreading the reconciled balance evenly over
 * the remaining months (what eltech:generate-loan-schedules-2026-07-31 does)
 * changes the per-installment amount, which is NOT what's wanted here: the
 * client's real agreed installment amount is the one from the original
 * disbursement-anchored amortization table, unchanged.
 *
 * So instead of recalculating anything, this only DELETES the schedule rows
 * dated before 01/08/2026 and renumbers what's left -- every kept row's
 * due_date, principal_due, interest_due, total_due, balance_after and status
 * are left exactly as they are.
 *
 * Safety check before touching a loan: the sum of principal_due (and
 * interest_due) across the rows that would be KEPT must already equal the
 * loan's current outstanding_principal (outstanding_interest) within a small
 * tolerance -- confirming the existing amortization table already reflects
 * the reconciled remaining balance and nothing but relabelling is needed. If
 * it doesn't match closely, the loan is flagged for manual review rather than
 * guessed at. A loan is also left alone if it has real repayments recorded,
 * or if truncating would leave it with zero schedule rows (e.g. a loan whose
 * only row is a single already-overdue lump sum dated before August, such as
 * LN-NK00221 -- that's a different, already-correct shape, not a leftover
 * pre-August installment run).
 */
class TruncateLegacyLoanSchedulesToAugust extends Command
{
    protected $signature = 'eltech:truncate-legacy-loan-schedules {--confirm : Actually write changes; omit for a dry-run report}';
    protected $description = "Remove pre-August 2026 schedule rows from legacy (31/07/2026 migration) loans, keeping the remaining installments' amounts exactly as they are";

    private const CUTOFF = '2026-08-01';
    private const TOLERANCE = 1000.00;

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

        $truncated = 0;
        $skippedRepay = 0;
        $skippedNoPreAugustRows = 0;
        $skippedWouldEmpty = 0;
        $skippedMismatch = 0;

        $loans = Loan::with(['schedules' => fn ($q) => $q->orderBy('due_date')])
            ->where('status', 'active')
            ->get()
            ->filter(fn ($loan) => in_array(strtolower($loan->loan_number), $legacyLoanNumbers, true));

        DB::beginTransaction();
        try {
            foreach ($loans as $loan) {
                if ($loan->repayments()->exists()) {
                    $skippedRepay++;
                    continue;
                }

                $before = $loan->schedules->filter(fn ($s) => Carbon::parse($s->due_date)->lt($cutoff));
                $keep = $loan->schedules->filter(fn ($s) => Carbon::parse($s->due_date)->gte($cutoff))->values();

                if ($before->isEmpty()) {
                    $skippedNoPreAugustRows++;
                    continue;
                }

                if ($keep->isEmpty()) {
                    $this->warn("SKIP {$loan->loan_number}: truncating would remove ALL schedule rows (its only installment(s) are dated before 01/08/2026) -- likely a matured lump-sum loan, not a leftover pre-August run. Needs manual review if that's wrong.");
                    $skippedWouldEmpty++;
                    continue;
                }

                $keepPrincipal = round($keep->sum('principal_due'), 2);
                $keepInterest = round($keep->sum('interest_due'), 2);
                $principalDiff = abs($keepPrincipal - (float) $loan->outstanding_principal);
                $interestDiff = abs($keepInterest - (float) $loan->outstanding_interest);

                if ($principalDiff > self::TOLERANCE || $interestDiff > self::TOLERANCE) {
                    $this->warn(sprintf(
                        "SKIP %s: kept rows total principal %s / interest %s vs outstanding_principal %s / outstanding_interest %s -- diff exceeds tolerance, needs manual review, not touching.",
                        $loan->loan_number,
                        number_format($keepPrincipal, 2), number_format($keepInterest, 2),
                        number_format((float) $loan->outstanding_principal, 2), number_format((float) $loan->outstanding_interest, 2)
                    ));
                    $skippedMismatch++;
                    continue;
                }

                $this->line(sprintf(
                    '%s: removing %d installment(s) before %s, keeping %d installment(s) from %s (unchanged amounts, principal %s / interest %s)',
                    $loan->loan_number, $before->count(), $cutoff->toDateString(),
                    $keep->count(), optional($keep->first())->due_date,
                    number_format($keepPrincipal, 2), number_format($keepInterest, 2)
                ));

                if ($confirm) {
                    foreach ($before as $row) {
                        $row->delete();
                    }
                    foreach ($keep as $index => $row) {
                        $row->installment_no = $index + 1;
                        $row->save();
                    }
                }

                $truncated++;
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
            'Done. Truncated: %d | Already clean (no pre-August rows): %d | Skipped (has repayments): %d | Skipped (would empty schedule): %d | Skipped (balance mismatch, needs review): %d',
            $truncated, $skippedNoPreAugustRows, $skippedRepay, $skippedWouldEmpty, $skippedMismatch
        ));

        if (!$confirm) {
            $this->line('');
            $this->info('Dry run only — re-run with --confirm to apply.');
        }

        return self::SUCCESS;
    }
}
