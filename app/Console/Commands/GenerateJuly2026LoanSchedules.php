<?php

namespace App\Console\Commands;

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
 * Loans whose maturity_date has already passed get a single lump-sum
 * installment dated on that (past) maturity date, marked 'overdue'
 * immediately -- 17 of these as of the July fix.
 */
class GenerateJuly2026LoanSchedules extends Command
{
    protected $signature = 'eltech:generate-loan-schedules-2026-07-31 {--confirm : Required to actually run this}';
    protected $description = 'Generate a repayment schedule (from 31/07/2026 to maturity) for active loans fixed by the 31/07/2026 loan term fix';

    protected const OPENING_DATE = '2026-07-31';

    public function handle(): int
    {
        if (!$this->option('confirm')) {
            $this->error('Re-run with --confirm to proceed.');
            return self::FAILURE;
        }

        $loans = Loan::where('status', 'active')
            ->whereNotNull('maturity_date')
            ->where('outstanding_principal', '>', 0)
            ->whereDoesntHave('schedules')
            ->get();

        if ($loans->isEmpty()) {
            $this->info('No eligible loans found (active, has a maturity date, outstanding principal > 0, no existing schedule).');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($loans) {
            foreach ($loans as $loan) {
                $this->generateForLoan($loan);
            }
        });

        $this->info("Done. Generated schedules for {$loans->count()} loans.");
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
