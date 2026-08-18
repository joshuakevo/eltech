<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Transaction;
use App\Services\AccountingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time additive import: posts the institutional (non-member) balances
 * from the old system's 31/07/2026 Trial Balance that the July statement
 * migration never touched -- cash/bank/mobile-money tills, investments,
 * loan-related receivables and provisions, fixed assets, and other
 * liabilities -- plus the combined Retained Earnings position (prior
 * accumulated deficit + the Jan-Jul 2026 YTD deficit from the old system's
 * Income Statement, which was never separately closed into equity there
 * either).
 *
 * Every figure is taken straight from the old Trial Balance's own debit/
 * credit column -- no reinterpretation of what each account "should" mean.
 * Not client-tagged (institutional balances, not member-level). The Jan-Jul
 * income/expense detail itself is deliberately not replayed line-by-line --
 * there's no day-by-day transaction data to rebuild 7 months of P&L from,
 * and doing so would double-count against the Retained Earnings figure here.
 *
 * Together with eltech:import-locked-up-loans-2026-07-31 (run independently,
 * no ordering dependency), this collapses the 3004 Opening Balance Equity
 * suspense account from a net debit of 1,095,232,197 down to a residual of
 * about 451,384 (0.018% of the balance sheet) -- verified analytically
 * before writing this command.
 */
class TrueUpJuly2026BalanceSheet extends Command
{
    protected $signature = 'eltech:true-up-balance-sheet-2026-07-31 {--confirm : Required to actually run this}';
    protected $description = 'Post the institutional balance sheet accounts (cash, investments, fixed assets, other liabilities, retained earnings) missing from the 31/07/2026 statement migration';

    protected const OPENING_DATE = '2026-07-31';

    public function handle(AccountingService $accounting): int
    {
        if (!$this->option('confirm')) {
            $this->error('Re-run with --confirm to proceed.');
            return self::FAILURE;
        }

        // Unlike the client/loan imports elsewhere in this project, these are plain
        // journal postings with no unique constraint to catch a re-run -- it would
        // silently double the balances. Guard explicitly instead.
        if (Transaction::where('description', 'like', "%old system Trial Balance%")->exists()) {
            $this->error('This has already been run (found a matching posted transaction). Re-running would double-post every balance. Aborting.');
            return self::FAILURE;
        }

        $path = database_path('data/balance_sheet_true_up_2026_07_31.json');
        if (!file_exists($path)) {
            $this->error("Data bundle not found at {$path}");
            return self::FAILURE;
        }
        $bundle = json_decode(file_get_contents($path), true);

        $openingEquity = Account::where('account_code', '3004')->firstOrFail();

        DB::transaction(function () use ($bundle, $accounting, $openingEquity) {
            foreach ($bundle as $row) {
                $account = Account::where('account_code', $row['account_code'])->firstOrFail();
                $amount  = (float) $row['amount'];
                if (abs($amount) < 0.01) {
                    continue;
                }

                $lines = $row['side'] === 'debit'
                    ? [
                        ['account_id' => $account->id, 'debit' => $amount, 'credit' => 0],
                        ['account_id' => $openingEquity->id, 'debit' => 0, 'credit' => $amount],
                    ]
                    : [
                        ['account_id' => $openingEquity->id, 'debit' => $amount, 'credit' => 0],
                        ['account_id' => $account->id, 'debit' => 0, 'credit' => $amount],
                    ];

                $accounting->post(
                    self::OPENING_DATE,
                    "Opening balance (31/07/2026, old system Trial Balance) - {$row['description']}",
                    $lines,
                    'manual',
                    null
                );

                $this->line("{$row['account_code']} {$account->account_name}: {$row['side']} " . number_format($amount));
            }
        });

        $this->info('Done.');
        return self::SUCCESS;
    }
}
