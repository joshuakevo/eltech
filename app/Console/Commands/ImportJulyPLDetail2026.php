<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Transaction;
use App\Services\AccountingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time additive import: unbundles the Jan-Jul 2026 YTD deficit (315,792,628)
 * currently folded into the single 3002 Retained Earnings opening entry (posted
 * by eltech:true-up-balance-sheet-2026-07-31), replacing it with the old
 * system's actual 41 individual income/expense account balances for that
 * period -- taken straight from the old Income Statement / Trial Balance.
 *
 * Each row is posted as its own two-line entry against 3002 (not 3004 --
 * this is purely reclassifying an amount already sitting in equity into its
 * granular P&L detail, not a new balance-sheet discovery). The net effect on
 * 3002 across all 41 rows is a credit of exactly 315,792,628, which reduces
 * 3002 from 1,148,540,221 debit down to 832,747,593 debit -- the old
 * system's own "prior period" Retained Earnings figure, with the YTD portion
 * now represented by these accounts instead.
 *
 * Verified analytically before writing this command: summing the bundle's
 * income (credit) and expense (debit) rows nets to exactly -315,792,628,
 * matching the documented YTD deficit used in the original true-up.
 */
class ImportJulyPLDetail2026 extends Command
{
    protected $signature = 'eltech:import-pl-detail-jan-jul-2026 {--confirm : Required to actually run this}';
    protected $description = 'Unbundle the Jan-Jul 2026 YTD deficit in Retained Earnings into the old system\'s individual income/expense account balances';

    protected const OPENING_DATE = '2026-07-31';

    public function handle(AccountingService $accounting): int
    {
        if (!$this->option('confirm')) {
            $this->error('Re-run with --confirm to proceed.');
            return self::FAILURE;
        }

        if (Transaction::where('description', 'like', '%Jan-Jul 2026 P&L detail%')->exists()) {
            $this->error('This has already been run (found a matching posted transaction). Re-running would double-post every balance. Aborting.');
            return self::FAILURE;
        }

        $path = database_path('data/pl_detail_jan_jul_2026.json');
        if (!file_exists($path)) {
            $this->error("Data bundle not found at {$path}");
            return self::FAILURE;
        }
        $bundle = json_decode(file_get_contents($path), true);

        $retainedEarnings = Account::where('account_code', '3002')->firstOrFail();

        DB::transaction(function () use ($bundle, $accounting, $retainedEarnings) {
            foreach ($bundle as $row) {
                $account = Account::where('account_code', $row['account_code'])->firstOrFail();
                $amount  = (float) $row['amount'];
                if (abs($amount) < 0.01) {
                    continue;
                }

                $lines = $row['side'] === 'debit'
                    ? [
                        ['account_id' => $account->id, 'debit' => $amount, 'credit' => 0],
                        ['account_id' => $retainedEarnings->id, 'debit' => 0, 'credit' => $amount],
                    ]
                    : [
                        ['account_id' => $retainedEarnings->id, 'debit' => $amount, 'credit' => 0],
                        ['account_id' => $account->id, 'debit' => 0, 'credit' => $amount],
                    ];

                $accounting->post(
                    self::OPENING_DATE,
                    "Jan-Jul 2026 P&L detail import (old system) - {$row['description']}",
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
