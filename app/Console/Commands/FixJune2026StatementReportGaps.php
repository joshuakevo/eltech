<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Group;
use App\Models\MemberShare;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Follow-up fix for data already created by MigrateJune2026Statement (already run
 * on production): the Member Summary report derives Savings/Savings Interest from
 * SavingsTransaction history (not savings_accounts.balance), attributes Group Value
 * only when groups.client_id is set, and filters member_shares by created_at -- none
 * of which the original migration populated correctly. This is purely additive
 * (creates missing SavingsTransaction rows, sets groups.client_id, back-dates
 * member_shares.created_at) -- no deletes, safe to re-run (idempotent).
 */
class FixJune2026StatementReportGaps extends Command
{
    protected $signature = 'eltech:fix-statement-report-gaps {--confirm : Required to actually run this}';
    protected $description = 'Backfill SavingsTransaction rows, group client_id links, and share created_at dates missed by the 30/06/2026 statement migration';

    protected const OPENING_DATE = '2026-06-30';

    public function handle(): int
    {
        if (!$this->option('confirm')) {
            $this->error('Re-run with --confirm to proceed.');
            return self::FAILURE;
        }

        DB::transaction(function () {
            $this->backfillSavingsTransactions();
            $this->linkGroupClients();
            $this->backdateShares();
        });

        $this->info('Done.');
        return self::SUCCESS;
    }

    protected function backfillSavingsTransactions(): void
    {
        $accounts = SavingsAccount::where('opened_date', self::OPENING_DATE)
            ->whereDoesntHave('transactions')
            ->get();

        foreach ($accounts as $account) {
            SavingsTransaction::create([
                'savings_account_id' => $account->id,
                'transaction_type'   => $account->balance >= 0 ? 'deposit' : 'withdrawal',
                'amount'             => $account->balance,
                'balance_before'     => 0,
                'balance_after'      => $account->balance,
                'transaction_date'   => self::OPENING_DATE,
                'description'        => 'Opening balance (30/06/2026 statement)',
            ]);
        }

        $this->line("Created opening SavingsTransaction rows for {$accounts->count()} accounts.");
    }

    protected function linkGroupClients(): void
    {
        $groups = Group::whereNull('client_id')->get();
        $linked = 0;

        foreach ($groups as $group) {
            $client = Client::where('client_number', $group->group_number)->first();
            if ($client) {
                $group->update(['client_id' => $client->id]);
                $linked++;
            }
        }

        $this->line("Linked {$linked} of {$groups->count()} unlinked groups to a representative client.");
    }

    protected function backdateShares(): void
    {
        $shares = MemberShare::where('share_number', 'like', 'SH-%')
            ->whereDate('created_at', '!=', self::OPENING_DATE)
            ->get();

        foreach ($shares as $share) {
            $share->timestamps = false;
            $share->created_at = self::OPENING_DATE;
            $share->updated_at = self::OPENING_DATE;
            $share->save();
        }

        $this->line("Back-dated created_at for {$shares->count()} shares.");
    }
}
