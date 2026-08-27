<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\LoanSchedule;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off correction (August 2026) for LN-NK00221: the 31/07/2026
 * reconciliation source (loan_terms_2026_07_31.json) recorded this loan's
 * term as 4 months, but the real agreement was 2 months (disbursed
 * 13/05/2026, matures 13/07/2026, not 13/09/2026).
 *
 * The reconciled outstanding_principal/outstanding_interest came from the old
 * system's own July report, not from our (wrong) 4-month schedule, so they
 * are left untouched. Only term_months/maturity_date are corrected, and the
 * schedule is rebuilt as a single already-overdue installment on the real
 * maturity date -- the same shape GenerateJuly2026LoanSchedules gives any
 * legacy loan whose maturity had already passed by the 31/07/2026 opening
 * date -- since 13/07/2026 is before that reconciliation date.
 */
class FixNk00221LoanTerm extends Command
{
    protected $signature = 'eltech:fix-nk00221-loan-term {--confirm : Actually write changes; omit for a dry-run report}';
    protected $description = 'Correct LN-NK00221\'s term to 2 months (matures 13/07/2026) and mark it overdue as of the reconciliation date';

    private const LOAN_NUMBER = 'LN-NK00221';
    private const TERM_MONTHS = 2;
    private const MATURITY    = '2026-07-13';

    public function handle(): int
    {
        $confirm = (bool) $this->option('confirm');
        if (!$confirm) {
            $this->warn('DRY RUN — no changes will be saved. Re-run with --confirm to apply.');
        }
        $this->line('');

        $loan = Loan::where('loan_number', self::LOAN_NUMBER)->first();
        if (!$loan) {
            $this->error(self::LOAN_NUMBER . ' not found.');
            return self::FAILURE;
        }

        if ($loan->status !== 'active') {
            $this->error("{$loan->loan_number} is not active (status={$loan->status}), aborting.");
            return self::FAILURE;
        }

        if ($loan->repayments()->exists()) {
            $this->error("{$loan->loan_number} has real repayments recorded, aborting — needs manual review, not this one-off fix.");
            return self::FAILURE;
        }

        if ((int) $loan->term_months === self::TERM_MONTHS) {
            $this->info("{$loan->loan_number} already has term_months=" . self::TERM_MONTHS . ', nothing to do.');
            return self::SUCCESS;
        }

        $principal = (float) $loan->outstanding_principal;
        $interest  = (float) $loan->outstanding_interest;
        $maturity  = Carbon::parse(self::MATURITY);

        $this->line(sprintf(
            '%s: term %d -> %d months | maturity %s -> %s | schedule -> 1 overdue installment on %s (%s principal + %s interest)',
            $loan->loan_number,
            (int) $loan->term_months, self::TERM_MONTHS,
            optional($loan->maturity_date)->toDateString(), $maturity->toDateString(),
            $maturity->toDateString(), number_format($principal, 2), number_format($interest, 2)
        ));

        if (!$confirm) {
            $this->line('');
            $this->info('Dry run only — re-run with --confirm to apply.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($loan, $maturity, $principal, $interest) {
            $loan->term_months   = self::TERM_MONTHS;
            $loan->maturity_date = $maturity->toDateString();
            $loan->save();

            $loan->schedules()->delete();
            LoanSchedule::create([
                'loan_id'        => $loan->id,
                'installment_no' => 1,
                'due_date'       => $maturity->toDateString(),
                'principal_due'  => $principal,
                'interest_due'   => $interest,
                'total_due'      => $principal + $interest,
                'balance_after'  => max(0, $principal),
                'principal_paid' => 0,
                'interest_paid'  => 0,
                'status'         => 'overdue',
            ]);
        });

        $this->line('');
        $this->info('Done.');
        return self::SUCCESS;
    }
}
