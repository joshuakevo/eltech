<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Loan;
use App\Models\LoanSchedule;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Builds a forward-looking repayment schedule for the loans fixed by
 * FixJuly2026LoanTerms, run after it. Not LoanService::generateSchedule() --
 * that assumes a brand-new loan with zero repayments and would recompute
 * outstanding_interest from the full original principal/term, overwriting the
 * already-reconciled current balance. This instead spreads the loan's
 * existing outstanding_principal/outstanding_interest evenly across the
 * months remaining until maturity_date -- a repayment plan for what's left,
 * not a reconstruction of the original amortization. No interest is
 * recalculated from the rate; the two figures being spread are the ones
 * already reconciled against the user's active-loans report.
 *
 * Anchored at 31/07/2026 (the statement's opening date), not the date this
 * command happens to run -- every balance in this migration is "as of"
 * 31/07/2026, so the schedule starts counting from there regardless of when
 * it's actually generated.
 *
 * Safely re-runnable: scoped to exactly the 54 loans in
 * loan_terms_2026_07_31.json (by loan_number), not a generic query, so
 * re-running after an earlier today-anchored run replaces only those --
 * never a normal loan's real schedule. Any of the 54 that already has a real
 * repayment recorded against it is left untouched and flagged, since
 * deleting its schedule could orphan that repayment's allocation.
 *
 * Loans whose maturity_date has already passed get a single lump-sum
 * installment dated on that (past) maturity date, marked 'overdue'
 * immediately.
 */
class GenerateJuly2026LoanSchedules extends Command
{
    protected $signature = 'eltech:generate-loan-schedules-2026-07-31 {--confirm : Required to actually run this}';
    protected $description = 'Generate a repayment schedule (from 31/07/2026 to maturity) for the loans fixed by the 31/07/2026 loan term fix';

    protected const OPENING_DATE = '2026-07-31';

    public function handle(): int
    {
        if (!$this->option('confirm')) {
            $this->error('Re-run with --confirm to proceed.');
            return self::FAILURE;
        }

        $path = database_path('data/loan_terms_2026_07_31.json');
        if (!file_exists($path)) {
            $this->error("Data bundle not found at {$path}");
            return self::FAILURE;
        }
        $bundle = json_decode(file_get_contents($path), true);

        $done = 0;
        $skipped = 0;

        DB::transaction(function () use ($bundle, &$done, &$skipped) {
            foreach ($bundle as $row) {
                $client = Client::where('client_number', $row['client_number'])->first();
                if (!$client) {
                    continue;
                }
                $loan = Loan::where('loan_number', 'LN-' . $row['client_number'])->first();
                if (!$loan || $loan->status !== 'active' || (float) $loan->outstanding_principal <= 0) {
                    continue;
                }

                if ($loan->repayments()->exists()) {
                    $this->warn("Skipped {$loan->loan_number}: has real repayments recorded, not touching its schedule.");
                    $skipped++;
                    continue;
                }

                $loan->schedules()->delete();
                $this->generateForLoan($loan);
                $done++;
            }
        });

        $this->info("Done. Generated schedules for {$done} loans" . ($skipped ? ", skipped {$skipped} with existing repayments." : '.'));
        return self::SUCCESS;
    }

    protected function generateForLoan(Loan $loan): void
    {
        $startDate = Carbon::parse(self::OPENING_DATE);
        $maturity  = Carbon::parse($loan->maturity_date);
        $principal = (float) $loan->outstanding_principal;
        $interest  = (float) $loan->outstanding_interest;

        if ($maturity->lte($startDate)) {
            $this->createInstallment($loan, 1, $maturity, $principal, $interest, max(0, $principal), 'overdue');
            $this->line("{$loan->loan_number}: matured {$maturity->toDateString()} -- 1 overdue installment ({$principal} + {$interest} interest)");
            return;
        }

        $months = $startDate->diffInMonths($maturity);
        if ($startDate->copy()->addMonths($months)->lt($maturity)) {
            $months++;
        }
        $months = max(1, $months);

        $principalPer = round($principal / $months, 2);
        $interestPer  = round($interest / $months, 2);
        $principalLeft = $principal;
        $interestLeft  = $interest;
        $balance       = $principal;

        for ($i = 1; $i <= $months; $i++) {
            $dueDate = $i === $months ? $maturity->copy() : $startDate->copy()->addMonths($i);

            if ($i === $months) {
                $pDue = round($principalLeft, 2);
                $iDue = round($interestLeft, 2);
            } else {
                $pDue = $principalPer;
                $iDue = $interestPer;
            }
            $principalLeft -= $pDue;
            $interestLeft  -= $iDue;
            $balance       -= $pDue;

            $this->createInstallment($loan, $i, $dueDate, $pDue, $iDue, max(0, round($balance, 2)), 'pending');
        }

        $this->line("{$loan->loan_number}: {$months} installment(s) from {$startDate->toDateString()} to {$maturity->toDateString()}");
    }

    protected function createInstallment(Loan $loan, int $no, Carbon $dueDate, float $principalDue, float $interestDue, float $balanceAfter, string $status): void
    {
        LoanSchedule::create([
            'loan_id'        => $loan->id,
            'installment_no' => $no,
            'due_date'       => $dueDate->toDateString(),
            'principal_due'  => $principalDue,
            'interest_due'   => $interestDue,
            'total_due'      => $principalDue + $interestDue,
            'balance_after'  => $balanceAfter,
            'principal_paid' => 0,
            'interest_paid'  => 0,
            'status'         => $status,
        ]);
    }
}
