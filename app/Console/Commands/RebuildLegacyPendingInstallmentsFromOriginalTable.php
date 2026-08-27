<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Services\LoanService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Companion to eltech:rebuild-legacy-schedules-original-table, for legacy
 * loans that already have one or more repayments recorded (that command
 * skips those entirely). Same final policy: match the OLD system's original
 * disbursement-anchored table, ignoring the reconciled 31/07 balance.
 *
 * Per-row rule:
 *  - status 'paid' -> untouched. Real money already collected against it;
 *    its historical split may not match the original table (it was posted
 *    under the reconciled figures at the time) and that's fine -- history
 *    doesn't get rewritten.
 *  - status 'partial' -> the loan is SKIPPED ENTIRELY and flagged for manual
 *    review, same caution as every other schedule-fix command here.
 *  - status 'pending'/'overdue' dated before 01/08/2026 -> deleted.
 *  - status 'pending'/'overdue' dated on/after 01/08/2026 -> matched by
 *    due_date against the loan's original disbursement-anchored table
 *    (LoanService::buildScheduleRows(), a pure calculation) and overwritten
 *    with that row's principal_due/interest_due/total_due/balance_after. If
 *    a pending row's due_date has no match in the original table (its date
 *    grid drifted from an earlier fix), the loan is skipped and flagged
 *    rather than guessed at.
 *
 * outstanding_principal/outstanding_interest are set to the sum of principal_
 * due/interest_due across the now-updated pending/overdue rows, ignoring the
 * previously reconciled figures entirely, per the confirmed policy.
 */
class RebuildLegacyPendingInstallmentsFromOriginalTable extends Command
{
    protected $signature = 'eltech:rebuild-legacy-pending-original-table {--confirm : Actually write changes; omit for a dry-run report}';
    protected $description = 'For legacy loans with existing repayments, match the OLD system exactly: recompute pending installments from the original disbursement-anchored table, leaving paid rows untouched';

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
        $skippedHasPartial = 0;
        $skippedNothingPending = 0;
        $skippedBadData = 0;
        $skippedDateMismatch = 0;

        $loans = Loan::with(['schedules' => fn ($q) => $q->orderBy('installment_no')])
            ->where('status', 'active')
            ->get()
            ->filter(fn ($loan) => in_array(strtolower($loan->loan_number), $legacyLoanNumbers, true))
            ->filter(fn ($loan) => $loan->repayments()->exists());

        DB::beginTransaction();
        try {
            foreach ($loans as $loan) {
                if ($loan->schedules->contains(fn ($s) => $s->status === 'partial')) {
                    $this->warn("SKIP {$loan->loan_number}: has a partially-paid installment, needs manual review before recomputing.");
                    $skippedHasPartial++;
                    continue;
                }

                if (!$loan->disbursement_date || !$loan->maturity_date || (float) $loan->principal <= 0 || (int) $loan->term_months <= 0) {
                    $this->warn("SKIP {$loan->loan_number}: missing disbursement_date/maturity_date/principal/term_months, cannot rebuild.");
                    $skippedBadData++;
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

                $originalRows = $loanService->buildScheduleRows($loan, Carbon::parse($loan->disbursement_date));
                $originalByDate = collect($originalRows)->keyBy(fn ($r) => Carbon::parse($r['due_date'])->toDateString());

                $matched = [];
                $mismatch = false;
                foreach ($keep as $scheduleRow) {
                    $key = Carbon::parse($scheduleRow->due_date)->toDateString();
                    if (!$originalByDate->has($key)) {
                        $mismatch = true;
                        break;
                    }
                    $matched[] = $originalByDate->get($key);
                }

                if ($mismatch) {
                    $this->warn("SKIP {$loan->loan_number}: a pending installment's due date has no match in the original disbursement table, needs manual review.");
                    $skippedDateMismatch++;
                    continue;
                }

                $newPrincipal = round(collect($matched)->sum('principal_due'), 2);
                $newInterest = round(collect($matched)->sum('interest_due'), 2);
                $oldPrincipal = (float) $loan->outstanding_principal;
                $oldInterest = (float) $loan->outstanding_interest;

                $this->line(sprintf(
                    '%s: %d paid (untouched) | removing %d stray pre-August unpaid row(s) | recomputing %d pending installment(s) from %s (%s/month) | outstanding_principal %s -> %s | outstanding_interest %s -> %s',
                    $loan->loan_number,
                    $locked->count(),
                    $preAugust->count(),
                    $keep->count(),
                    $matched[0]['due_date'],
                    number_format($matched[0]['total_due'], 2),
                    number_format($oldPrincipal, 2), number_format($newPrincipal, 2),
                    number_format($oldInterest, 2), number_format($newInterest, 2)
                ));

                if ($confirm) {
                    foreach ($preAugust as $row) {
                        $row->delete();
                    }
                    foreach ($keep as $index => $scheduleRow) {
                        $new = $matched[$index];
                        $scheduleRow->principal_due  = $new['principal_due'];
                        $scheduleRow->interest_due   = $new['interest_due'];
                        $scheduleRow->total_due      = $new['total_due'];
                        $scheduleRow->balance_after  = $new['balance_after'];
                        $scheduleRow->save();
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
            'Done. Rebuilt: %d | Skipped (has partial installment): %d | Skipped (nothing pending to recompute): %d | Skipped (due date mismatch): %d | Skipped (bad data): %d',
            $rebuilt, $skippedHasPartial, $skippedNothingPending, $skippedDateMismatch, $skippedBadData
        ));

        if (!$confirm) {
            $this->line('');
            $this->info('Dry run only — re-run with --confirm to apply.');
        }

        return self::SUCCESS;
    }
}
