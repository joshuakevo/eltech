<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\LoanProduct;
use App\Services\LoanService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time correction (August 2026): installment amounts on active loans do
 * not reflect a full amortization of the amount actually disbursed over the
 * real disbursement-to-maturity term.
 *
 * This does NOT apply to the 54 loans migrated on 31/07/2026
 * (loan_terms_2026_07_31.json). Their outstanding_principal/outstanding_interest
 * are reconciled *remaining* balances as of 31/07/2026 -- real off-system
 * repayment happened before the system existed and is folded into those
 * figures, not into any loan_repayments row. Their schedules (built by
 * GenerateJuly2026LoanSchedules, then re-anchored to the disbursement
 * day-of-month by eltech:fix-loan-interest-rates) correctly spread that
 * remaining balance across the installments falling after 31/07/2026 --
 * rebuilding from the original principal at disbursement_date would discard
 * the real repayment those balances represent. So legacy loans are always
 * skipped here, regardless of loan_repayments.
 *
 * For every other ("normal") active loan -- disbursed through the app, no
 * external reconciliation -- outstanding_principal still equals the original
 * principal until a real repayment is recorded, so there's nothing to lose by
 * rebuilding its schedule from scratch via LoanService::generateSchedule(),
 * which anchors on disbursement_date and amortizes the loan's original
 * principal (the amount actually disbursed) across its full term.
 *
 * The number of installments (term) is taken from the real calendar gap
 * between disbursement_date and maturity_date -- not the stored term_months
 * field -- and term_months is corrected to match if it disagrees, so the two
 * can't drift apart again.
 *
 * This intentionally does NOT touch interest_rate (already annualised by
 * eltech:fix-loan-interest-rates) and does NOT touch closed loans -- a closed
 * loan's outstanding_interest reflects how it was actually settled, and
 * generateSchedule() would overwrite that with a freshly computed "still
 * owing" figure, which is wrong for a loan that's already paid off. A loan is
 * also left alone if it has a real repayment recorded (rebuilding would
 * orphan that repayment's allocation), if it's on the Locked-Up Loans product
 * (0% historical debt import, not a real amortizing loan), or if its dates
 * don't span at least one whole month (bad/missing data -- flagged for manual
 * review).
 */
class FixLoanInstallmentSchedules extends Command
{
    protected $signature = 'eltech:fix-loan-installment-schedules {--confirm : Actually write changes; omit for a dry-run report}';
    protected $description = 'Rebuild active loans\' installment schedules from disbursement date, maturity date and amount disbursed';

    private bool $confirm = false;

    private int $regenerated   = 0;
    private int $skippedRepay  = 0;
    private int $skippedDates  = 0;
    private int $skippedLocked = 0;
    private int $skippedLegacy = 0;

    public function handle(LoanService $loanService): int
    {
        $this->confirm = (bool) $this->option('confirm');

        if (!$this->confirm) {
            $this->warn('DRY RUN — no changes will be saved. Re-run with --confirm to apply.');
        }
        $this->line('');

        $lockedUpProductId = LoanProduct::where('name', 'Locked-Up Loans')->value('id');

        $legacyPath = database_path('data/loan_terms_2026_07_31.json');
        $legacyLoanNumbers = [];
        if (file_exists($legacyPath)) {
            $bundle = json_decode(file_get_contents($legacyPath), true) ?? [];
            $legacyLoanNumbers = collect($bundle)->map(fn ($row) => strtolower('LN-' . $row['client_number']))->all();
        }

        $loans = Loan::with(['schedules' => fn ($q) => $q->orderBy('installment_no')])
            ->where('status', 'active')
            ->whereNotNull('disbursement_date')
            ->whereNotNull('maturity_date')
            ->where('principal', '>', 0)
            ->when($lockedUpProductId, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('loan_product_id', '!=', $lockedUpProductId)
                ->orWhereNull('loan_product_id')))
            ->get();

        DB::beginTransaction();
        try {
            foreach ($loans as $loan) {
                $this->processLoan($loan, in_array(strtolower($loan->loan_number), $legacyLoanNumbers, true), $loanService);
            }

            if ($this->confirm) {
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
            'Done. Regenerated: %d | Skipped (legacy 31/07/2026 loan): %d | Skipped (has repayments): %d | Skipped (term < 1 month, bad dates): %d | Skipped (Locked-Up Loans product): %d',
            $this->regenerated, $this->skippedLegacy, $this->skippedRepay, $this->skippedDates, $this->skippedLocked
        ));

        return self::SUCCESS;
    }

    private function processLoan(Loan $loan, bool $isLegacy, LoanService $loanService): void
    {
        if ($isLegacy) {
            $this->warn("SKIP {$loan->loan_number}: legacy 31/07/2026 migrated loan, outstanding balances already reflect real off-system repayment, not touching its schedule.");
            $this->skippedLegacy++;
            return;
        }

        if ($loan->repayments()->exists()) {
            $this->warn("SKIP {$loan->loan_number}: has real repayments recorded, not touching its schedule.");
            $this->skippedRepay++;
            return;
        }

        $disbursement = Carbon::parse($loan->disbursement_date);
        $maturity     = Carbon::parse($loan->maturity_date);
        $realMonths   = $disbursement->diffInMonths($maturity);

        if ($realMonths < 1) {
            $this->warn("SKIP {$loan->loan_number}: disbursement {$disbursement->toDateString()} to maturity {$maturity->toDateString()} spans less than a month, needs manual review.");
            $this->skippedDates++;
            return;
        }

        $oldTermMonths    = (int) $loan->term_months;
        $oldScheduleCount = $loan->schedules->count();
        $oldTotalDue      = (float) $loan->schedules->sum('total_due');
        $oldFirstDue      = optional($loan->schedules->first())->due_date;
        $oldLastDue       = optional($loan->schedules->last())->due_date;

        if ($this->confirm) {
            if ($oldTermMonths !== $realMonths) {
                $loan->term_months = $realMonths;
                $loan->save();
            }
            $loanService->generateSchedule($loan);
            $loan->refresh();
            $newRows = $loan->schedules()->orderBy('installment_no')->get()->map(fn ($s) => [
                'due_date' => $s->due_date->toDateString(), 'total_due' => (float) $s->total_due,
            ])->all();
        } else {
            // Preview only: build the rows an apply would produce, without saving.
            $previewLoan = $loan->replicate();
            $previewLoan->term_months = $realMonths;
            $newRows = $loanService->buildScheduleRows($previewLoan, $disbursement);
        }

        $newCount    = count($newRows);
        $newTotalDue = array_sum(array_column($newRows, 'total_due'));
        $newFirstDue = $newRows[0]['due_date'] ?? null;
        $newLastDue  = $newRows[$newCount - 1]['due_date'] ?? null;

        $this->line(sprintf(
            '%s: principal %s | term %d -> %d months | %d installment(s) totalling %s (%s..%s) -> %d installment(s) totalling %s (%s..%s)',
            $loan->loan_number,
            number_format((float) $loan->principal, 2),
            $oldTermMonths, $realMonths,
            $oldScheduleCount, number_format($oldTotalDue, 2), $oldFirstDue, $oldLastDue,
            $newCount, number_format($newTotalDue, 2), $newFirstDue, $newLastDue
        ));

        $this->regenerated++;
    }
}
