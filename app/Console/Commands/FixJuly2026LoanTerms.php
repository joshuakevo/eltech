<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Loan;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Follow-up fix for the 54 clients with an active loan created by
 * MigrateJuly2026Statement: that migration only had the statement's aggregate
 * Loan_Value/Loan_Int per client, so every loan was disbursed on the generic
 * opening date on whichever single LoanProduct happened to be active, with no
 * real rate/term. Cross-referencing the "Loan Balance By date" report
 * (uploaded by the user) against LoanInfo.xlsx by Loan ID -- not by member
 * code, avoiding the top-up ambiguity some clients have -- gives the real
 * disbursement date, rate, term, interest method, and original principal for
 * each.
 *
 * Same pattern as the FD term fix: corrects the loan's own fields only, does
 * not touch loan_product_id or GL accounts. Every loan stays on whichever
 * product the statement migration originally assigned it to -- kept simple
 * on purpose. Also computes maturity_date (disbursement_date + term_months),
 * which the migration never set, so overdue loans can be identified (see
 * loans/index.blade.php). No schedule is generated -- LoanService's
 * generateSchedule() assumes zero prior repayments and would overwrite
 * outstanding_interest with a full fresh-loan total, corrupting the already-
 * reconciled current balance these loans carry from real repayment history
 * we don't have day-by-day. Interest rates are stored as given in
 * LoanInfo.xlsx (annual convention, matching how the rest of the system
 * treats interest_rate) -- spot-checking a sample against actual accrued
 * interest didn't cleanly support a "monthly rate" conversion either, so
 * left as-is rather than guessing a factor that only fit one sample loan.
 * Three loans from the report were deliberately left out
 * (XDK00067, XTK00018: small negative/credit balances outside what the
 * original migration created; XXMK00190: XX-prefixed, excluded by the
 * statement migration's own rule) -- see project notes, not a bug.
 */
class FixJuly2026LoanTerms extends Command
{
    protected $signature = 'eltech:fix-loan-terms-2026-07-31 {--confirm : Required to actually run this}';
    protected $description = 'Correct rate/term/disbursement date on the 54 active loans created by the 31/07/2026 statement migration using the real LoanInfo.xlsx + active-loans report';

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

        DB::transaction(function () use ($bundle) {
            foreach ($bundle as $row) {
                $this->fixLoan($row);
            }
        });

        $this->info('Done.');
        return self::SUCCESS;
    }

    protected function fixLoan(array $row): void
    {
        $client = Client::where('client_number', $row['client_number'])->firstOrFail();
        // Not filtered by status='active' -- a handful of these (0 principal, small
        // residual interest only) were correctly marked 'closed' by the migration.
        $loan = Loan::where('loan_number', 'LN-' . $row['client_number'])->firstOrFail();

        $maturityDate = Carbon::parse($row['disbursement_date'])->addMonths($row['term_months'])->toDateString();

        $loan->update([
            'principal'         => $row['original_principal'],
            'interest_rate'     => $row['interest_rate'],
            'interest_method'   => $row['interest_method'],
            'term_months'       => $row['term_months'],
            'disbursement_date' => $row['disbursement_date'],
            'maturity_date'     => $maturityDate,
        ]);

        $this->line("Fixed {$row['client_number']} (loan #{$row['loan_id']}): {$row['interest_rate']}% {$row['interest_method']}, {$row['term_months']}mo, disbursed {$row['disbursement_date']} -> matures {$maturityDate}");
    }
}
