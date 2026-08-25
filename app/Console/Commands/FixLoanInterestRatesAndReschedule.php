<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Services\LoanService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time correction for two related bugs found in August 2026:
 *
 * 1. Every loan's interest_rate was entered as a MONTHLY percentage (e.g. "2.8"
 *    meaning 2.8%/month), but LoanService::buildScheduleRows() always treats
 *    interest_rate as an ANNUAL rate -- understating interest by 12x. Confirmed
 *    by cross-referencing against each loan's own product default (which IS
 *    correctly annual): every affected loan's rate is well below its product's
 *    rate, in a pattern consistent with "product rate / 12".
 *
 * 2. The 31/07/2026 migration's forward schedule (GenerateJuly2026LoanSchedules)
 *    anchored every installment to a fixed calendar date (31/07/2026) instead of
 *    each loan's real disbursement date, so due dates landed on month-end
 *    (31 Aug, 1 Oct, 31 Oct, ...) instead of the disbursement day-of-month.
 *
 * This command fixes both, with two different levels of risk:
 *
 * - Loans NOT in loan_terms_2026_07_31.json ("normal" loans, disbursed through
 *   the app with no external reconciliation): interest_rate x 12, then a full
 *   LoanService::generateSchedule() rebuild. Safe because outstanding_principal
 *   still equals the original principal (no repayment has ever been recorded
 *   against them) -- there is nothing to lose by rebuilding from scratch, and
 *   generateSchedule() already anchors correctly to disbursement_date.
 *
 * - Loans IN loan_terms_2026_07_31.json (balances reconciled by hand against
 *   the old system's real active-loan report -- not derived from interest_rate
 *   at all): interest_rate x 12 is applied to the field for record consistency,
 *   but principal_due/interest_due on every schedule row are left untouched.
 *   Only due_date is rewritten, counting backward in whole months from
 *   maturity_date (which is already correctly disbursement_date + term_months)
 *   so installments land on the disbursement day-of-month instead of
 *   month-end, without changing what's owed or when the loan matures.
 *
 * Either way, a loan is left alone if its interest_rate already equals its own
 * product's rate (already annual -- e.g. a loan that inherited the product
 * default rather than being given a mistaken monthly figure), if the rate is
 * already 0 (Locked-Up/NPL loans, 0% by design), or if it has a real repayment
 * recorded (LoanRepayment row) -- rebuilding a schedule that repayments were
 * already allocated against would orphan that allocation.
 */
class FixLoanInterestRatesAndReschedule extends Command
{
    protected $signature = 'eltech:fix-loan-interest-rates {--confirm : Actually write changes; omit for a dry-run report}';
    protected $description = "Annualise loan interest rates (x12, they were entered monthly) and reschedule installment dates to the real disbursement day-of-month";

    private bool $confirm = false;

    private int $regenerated = 0;
    private int $rescheduled = 0;
    private int $rateOnly    = 0;
    private int $skippedOk   = 0;
    private int $skippedRepay = 0;

    public function handle(LoanService $loanService): int
    {
        $this->confirm = (bool) $this->option('confirm');

        if (!$this->confirm) {
            $this->warn('DRY RUN — no changes will be saved. Re-run with --confirm to apply.');
        }
        $this->line('');

        $legacyPath = database_path('data/loan_terms_2026_07_31.json');
        $legacyLoanNumbers = [];
        if (file_exists($legacyPath)) {
            $bundle = json_decode(file_get_contents($legacyPath), true) ?? [];
            // Lower-cased: client_number casing is inconsistent in the source data
            // (e.g. "Gk00038" vs "GK00050"), so match case-insensitively.
            $legacyLoanNumbers = collect($bundle)->map(fn ($row) => strtolower('LN-' . $row['client_number']))->all();
        }

        $loans = Loan::with(['product', 'schedules' => fn ($q) => $q->orderBy('installment_no')])
            ->whereIn('status', ['pending', 'active', 'closed'])
            ->get();

        // Manual transaction control (not DB::transaction()) so a dry run can always
        // roll back regardless of what happens inside the loop.
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
            'Done. Full regenerate: %d | Dates re-anchored (legacy, amounts untouched): %d | Rate field only: %d | Already correct: %d | Skipped (has repayments): %d',
            $this->regenerated, $this->rescheduled, $this->rateOnly, $this->skippedOk, $this->skippedRepay
        ));

        return self::SUCCESS;
    }

    private function processLoan(Loan $loan, bool $isLegacy, LoanService $loanService): void
    {
        $product = $loan->product;
        $oldRate = (float) $loan->interest_rate;

        if ($oldRate <= 0 || ($product && abs($oldRate - (float) $product->interest_rate) < 0.0001)) {
            $this->skippedOk++;
            return;
        }

        $newRate = round($oldRate * 12, 4);

        if ($loan->status === 'active' && $loan->repayments()->exists()) {
            $this->warn("SKIP {$loan->loan_number}: has real repayments recorded, not touching rate/schedule.");
            $this->skippedRepay++;
            return;
        }

        $canReschedule = $loan->status === 'active' && (int) $loan->term_months > 0;
        $hasSchedule   = $loan->schedules->isNotEmpty();

        if ($isLegacy && $canReschedule && $hasSchedule) {
            $this->line(sprintf(
                '%s: rate %.4f%% -> %.4f%% p.a. | dates re-anchored to day %s (amounts unchanged, total interest %s)',
                $loan->loan_number, $oldRate, $newRate,
                Carbon::parse($loan->disbursement_date)->format('jS'),
                number_format((float) $loan->schedules->sum('interest_due'), 2)
            ));

            if ($this->confirm) {
                $loan->interest_rate = $newRate;
                $loan->save();
                $this->reanchorScheduleDates($loan);
            }
            $this->rescheduled++;
            return;
        }

        if ($canReschedule) {
            $oldInterest = (float) $loan->schedules->sum('interest_due');
            $oldFirstDue = optional($loan->schedules->first())->due_date;
            $oldLastDue  = optional($loan->schedules->last())->due_date;

            if ($this->confirm) {
                $loan->interest_rate = $newRate;
                $loan->save();
                $loanService->generateSchedule($loan);
                $loan->refresh();
            }

            $newInterest = $this->confirm ? (float) $loan->outstanding_interest : null;

            $this->line(sprintf(
                '%s: rate %.4f%% -> %.4f%% p.a. | full regenerate, interest %s -> %s, due dates %s..%s -> anchored to disbursement day %s',
                $loan->loan_number, $oldRate, $newRate,
                number_format($oldInterest, 2),
                $newInterest !== null ? number_format($newInterest, 2) : '(pending apply)',
                $oldFirstDue, $oldLastDue,
                Carbon::parse($loan->disbursement_date)->format('jS')
            ));

            $this->regenerated++;
            return;
        }

        // Pending / closed / zero-term: fix the rate field only, no schedule exists to touch.
        $this->line(sprintf('%s (%s): rate %.4f%% -> %.4f%% p.a. | rate field only, no schedule change', $loan->loan_number, $loan->status, $oldRate, $newRate));
        if ($this->confirm) {
            $loan->interest_rate = $newRate;
            $loan->save();
        }
        $this->rateOnly++;
    }

    /**
     * Keep every schedule row's principal_due/interest_due/total_due/status exactly
     * as reconciled; only move due_date so installments land on the disbursement
     * day-of-month, counting backward in whole months from maturity_date (itself
     * already correct: disbursement_date + term_months).
     */
    private function reanchorScheduleDates(Loan $loan): void
    {
        $schedules = $loan->schedules()->orderBy('installment_no')->get();
        $count     = $schedules->count();
        $maturity  = Carbon::parse($loan->maturity_date);

        foreach ($schedules as $index => $schedule) {
            $periodsBeforeMaturity = $count - ($index + 1);
            $dueDate = $periodsBeforeMaturity === 0
                ? $maturity->copy()
                : $maturity->copy()->subMonths($periodsBeforeMaturity);

            $schedule->due_date = $dueDate->toDateString();
            $schedule->save();
        }
    }
}
