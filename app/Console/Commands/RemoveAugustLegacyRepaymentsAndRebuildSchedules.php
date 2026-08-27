<?php

namespace App\Console\Commands;

use App\Http\Controllers\TransactionController;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * One-off correction (August 2026): the client wants to re-enter all real
 * loan repayments collected in August 2026 on legacy (31/07/2026 migration)
 * loans, now that eltech:rebuild-legacy-schedules-original-table has fixed
 * those loans' schedules to match the OLD system's original installment
 * amounts. The August repayments already on file were posted against
 * installments that, at the time, still carried the reconciled (not
 * original-table) split -- so removing them and letting the schedule fully
 * regenerate keeps every row uniform, then the client re-enters the same
 * real payments against the now-correct numbers.
 *
 * This does NOT delete LoanRepayment rows directly. Each one's linked GL
 * journal (transactions.module = 'loan', description "Loan repayment: ...")
 * is destroyed via the app's own TransactionController::destroy(), which
 * already implements the correct, audited unwind: deletes the GL lines and
 * transaction, restores the loan's outstanding_principal/outstanding_interest/
 * outstanding_penalty, and rolls back the affected schedule rows' paid/
 * partial status -- the same path used when a staff member reverses/deletes
 * a journal entry from the Transactions screen. A repayment with no linked
 * transaction_id (shouldn't happen for a legacy loan repayment, but handled
 * defensively) has its row deleted directly with a warning.
 *
 * After all August repayments are removed, this chains into the two
 * existing original-table rebuild commands (for the now-repayment-free
 * loans, and for any legacy loan that still has a repayment outside
 * August) so every affected loan's schedule comes out uniform in the same
 * run, matching the confirmed final policy.
 */
class RemoveAugustLegacyRepaymentsAndRebuildSchedules extends Command
{
    protected $signature = 'eltech:remove-august-legacy-repayments {--confirm : Actually write changes; omit for a dry-run report}';
    protected $description = 'Remove August 2026 repayments on legacy loans via proper GL/sub-ledger reversal, then rebuild their schedules to match the original disbursement table';

    private const RANGE_START = '2026-08-01';
    private const RANGE_END = '2026-08-31';

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

        $legacyLoanIds = Loan::get()
            ->filter(fn ($loan) => in_array(strtolower($loan->loan_number), $legacyLoanNumbers, true))
            ->pluck('id');

        $repayments = LoanRepayment::whereIn('loan_id', $legacyLoanIds)
            ->whereBetween('payment_date', [self::RANGE_START, self::RANGE_END])
            ->with('loan')
            ->orderBy('loan_id')
            ->orderBy('payment_date')
            ->get();

        if ($repayments->isEmpty()) {
            $this->info('No August 2026 repayments found on legacy loans.');
            return self::SUCCESS;
        }

        $removed = 0;
        $noTransaction = 0;

        DB::beginTransaction();
        try {
            foreach ($repayments as $repayment) {
                $loanNumber = $repayment->loan->loan_number ?? "loan_id {$repayment->loan_id}";

                $this->line(sprintf(
                    '%s: repayment on %s, amount %s (principal %s / interest %s / penalty %s)',
                    $loanNumber,
                    $repayment->payment_date,
                    number_format($repayment->amount, 2),
                    number_format($repayment->principal_paid, 2),
                    number_format($repayment->interest_paid, 2),
                    number_format($repayment->penalty_paid, 2)
                ));

                if ($confirm) {
                    $transaction = $repayment->transaction_id ? Transaction::find($repayment->transaction_id) : null;

                    if ($transaction) {
                        app(TransactionController::class)->destroy($transaction);
                    } else {
                        $this->warn("  -> no linked GL transaction found, deleting repayment row directly.");
                        $noTransaction++;
                        $repayment->delete();
                    }
                }

                $removed++;
            }

            if ($confirm) {
                $this->line('');
                $this->info('Repayments removed. Rebuilding schedules to match the original disbursement table...');
                $this->line('');

                Artisan::call('eltech:rebuild-legacy-schedules-original-table', ['--confirm' => true]);
                $this->line(Artisan::output());

                Artisan::call('eltech:rebuild-legacy-pending-original-table', ['--confirm' => true]);
                $this->line(Artisan::output());

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
            'Done. Repayments removed: %d | Removed without a linked GL transaction: %d',
            $removed, $noTransaction
        ));

        if (!$confirm) {
            $this->line('');
            $this->info('Dry run only — re-run with --confirm to apply.');
        }

        return self::SUCCESS;
    }
}
