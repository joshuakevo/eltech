<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Services\AccountingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Follow-up fix for the 54 clients with an active loan created by
 * MigrateJuly2026Statement: that migration only had the statement's aggregate
 * Loan_Value/Loan_Int per client, so every loan was disbursed on the generic
 * opening date on whichever single LoanProduct happened to be active, with no
 * real rate/term/category. Cross-referencing the "Loan Balance By date"
 * report (uploaded by the user) against LoanInfo.xlsx by Loan ID -- not by
 * member code, avoiding the top-up ambiguity some clients have -- gives the
 * real disbursement date, rate, term, interest method, original principal,
 * and category (BSLN -> Business, AGLN -> General) for each.
 *
 * Reassigning the loan product moves its receivable account (e.g. General
 * 1101 -> Business 1102), so unlike the FD term fix this DOES need a GL
 * reclassification entry when the product actually changes -- handled here,
 * not left implicit. Three loans from the report were deliberately left out
 * (XDK00067, XTK00018: small negative/credit balances outside what the
 * original migration created; XXMK00190: XX-prefixed, excluded by the
 * statement migration's own rule) -- see project notes, not a bug.
 */
class FixJuly2026LoanTerms extends Command
{
    protected $signature = 'eltech:fix-loan-terms-2026-07-31 {--confirm : Required to actually run this}';
    protected $description = 'Correct rate/term/category/disbursement date on the 54 active loans created by the 31/07/2026 statement migration using the real LoanInfo.xlsx + active-loans report';

    protected const OPENING_DATE = '2026-07-31';

    public function handle(AccountingService $accounting): int
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

        $products = [
            'business' => LoanProduct::whereHas('receivableAccount', fn ($q) => $q->where('account_code', '1102'))->firstOrFail(),
            'general'  => LoanProduct::whereHas('receivableAccount', fn ($q) => $q->where('account_code', '1101'))->firstOrFail(),
        ];

        DB::transaction(function () use ($bundle, $products, $accounting) {
            foreach ($bundle as $row) {
                $this->fixLoan($row, $products[$row['product']], $accounting);
            }
        });

        $this->info('Done.');
        return self::SUCCESS;
    }

    protected function fixLoan(array $row, LoanProduct $targetProduct, AccountingService $accounting): void
    {
        $client = Client::where('client_number', $row['client_number'])->firstOrFail();
        // Not filtered by status='active' -- a handful of these (0 principal, small
        // residual interest only) were correctly marked 'closed' by the migration.
        $loan = Loan::where('loan_number', 'LN-' . $row['client_number'])->firstOrFail();

        $oldReceivableId = $loan->product->receivable_account_id;
        $newReceivableId = $targetProduct->receivable_account_id;

        if ($oldReceivableId != $newReceivableId && abs((float) $loan->outstanding_principal) > 0.01) {
            $this->reclassifyReceivable($loan, $oldReceivableId, $newReceivableId, $accounting);
        }

        $loan->update([
            'loan_product_id'   => $targetProduct->id,
            'principal'         => $row['original_principal'],
            'interest_rate'     => $row['interest_rate'],
            'interest_method'   => $row['interest_method'],
            'term_months'       => $row['term_months'],
            'disbursement_date' => $row['disbursement_date'],
        ]);

        $this->line("Fixed {$row['client_number']} (loan #{$row['loan_id']}): {$row['product']}, {$row['interest_rate']}% {$row['interest_method']}, {$row['term_months']}mo, disbursed {$row['disbursement_date']}");
    }

    /**
     * Moves the loan's outstanding principal from its current receivable account
     * to the correct product's receivable account -- a real reclassification
     * entry, not just a sub-ledger tweak, since the two are different GL accounts.
     * Interest stays where it was (outstanding_interest isn't a posted GL balance
     * here, same as the original migration).
     */
    protected function reclassifyReceivable(Loan $loan, int $oldAccountId, int $newAccountId, AccountingService $accounting): void
    {
        $amount = abs((float) $loan->outstanding_principal);
        $lines = $loan->outstanding_principal > 0
            ? [
                ['account_id' => $newAccountId, 'debit' => $amount, 'credit' => 0, 'client_id' => $loan->client_id],
                ['account_id' => $oldAccountId, 'debit' => 0, 'credit' => $amount, 'client_id' => $loan->client_id],
            ]
            : [
                ['account_id' => $oldAccountId, 'debit' => $amount, 'credit' => 0, 'client_id' => $loan->client_id],
                ['account_id' => $newAccountId, 'debit' => 0, 'credit' => $amount, 'client_id' => $loan->client_id],
            ];

        $accounting->post(
            self::OPENING_DATE,
            "Loan product reclassification (31/07/2026 statement) - {$loan->loan_number}",
            $lines,
            'loan',
            null
        );
    }
}
