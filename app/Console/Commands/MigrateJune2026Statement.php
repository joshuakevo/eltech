<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Client;
use App\Models\FixedDeposit;
use App\Models\FixedDepositProduct;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\MemberShare;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\SavingsTransaction;
use App\Services\AccountingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time reset: wipes all transactional/sub-ledger data and replaces the client
 * roster + balances with the 30/06/2026 Konnect Initiatives member statement.
 *
 * Everything (deletes, wipes, rebuild) runs inside a single DB transaction -- if
 * anything throws, nothing is committed. Requires --confirm since this is
 * destructive and irreversible once it commits.
 */
class MigrateJune2026Statement extends Command
{
    protected $signature = 'eltech:migrate-statement-2026-06-30 {--confirm : Required to actually run this}';
    protected $description = 'Wipe all transactional data and replace clients/balances with the 30/06/2026 member statement';

    protected const OPENING_DATE = '2026-06-30';

    public function handle(AccountingService $accounting): int
    {
        if (!$this->option('confirm')) {
            $this->error('This permanently deletes and rebuilds production data. Re-run with --confirm to proceed.');
            return self::FAILURE;
        }

        $path = database_path('data/statement_migration_2026_06_30.json');
        if (!file_exists($path)) {
            $this->error("Data bundle not found at {$path}");
            return self::FAILURE;
        }
        $bundle = json_decode(file_get_contents($path), true);

        $openingEquity  = Account::where('account_code', '3004')->firstOrFail();
        $shareCapital   = Account::where('account_code', '3001')->firstOrFail();
        $groupSavingsGl = Account::where('account_code', '2005')->firstOrFail();
        $savingsProduct = SavingsProduct::where('is_active', true)->firstOrFail();
        $loanProduct    = LoanProduct::where('is_active', true)->firstOrFail();
        $fdProduct      = FixedDepositProduct::where('is_active', true)->firstOrFail();

        $this->info('Starting migration inside a single transaction...');

        DB::transaction(function () use ($bundle, $accounting, $openingEquity, $shareCapital, $groupSavingsGl, $savingsProduct, $loanProduct, $fdProduct) {
            // Wipe sub-ledger/transactional tables FIRST -- they hold RESTRICT foreign
            // keys back to clients, so deleting a client while their old savings/loan/FD
            // rows still exist would fail.
            $this->wipeTransactionalTables();
            $this->deleteConfirmedClients($bundle['delete_client_numbers']);
            $this->rebuildClients($bundle['clients'], $accounting, $openingEquity, $shareCapital, $savingsProduct, $loanProduct, $fdProduct);
            $this->rebuildGroups($bundle['groups'], $accounting, $openingEquity, $groupSavingsGl);
        });

        $this->info('Migration complete.');
        return self::SUCCESS;
    }

    protected function deleteConfirmedClients(array $clientNumbers): void
    {
        $count = Client::whereIn('client_number', $clientNumbers)->count();
        Client::whereIn('client_number', $clientNumbers)->forceDelete();
        $this->line("Deleted {$count} confirmed non-matching clients.");
    }

    /**
     * Full wipe of every transactional/sub-ledger table, for every client, not just
     * the ones being deleted -- this is the "hard reset" the migration is built
     * around. FK checks are dropped only for the duration of the deletes.
     */
    protected function wipeTransactionalTables(): void
    {
        // DELETE, not TRUNCATE -- TRUNCATE causes an implicit commit in MySQL and
        // cannot be rolled back if a later step in this transaction fails. That was
        // caught live in local testing: a deliberate failure after the wipe left the
        // wipe committed anyway. DELETE respects the surrounding transaction.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ([
            'mobile_money_transactions', 'share_transactions', 'loan_repayments', 'loan_schedules',
            'loan_guarantors', 'savings_transactions', 'group_transactions', 'transaction_lines',
            'transactions', 'member_shares', 'loans', 'fixed_deposits', 'savings_accounts',
            'group_members', 'groups',
        ] as $table) {
            DB::table($table)->delete();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        $this->line('Wiped all transactional/sub-ledger tables.');
    }

    protected function rebuildClients(
        array $rows,
        AccountingService $accounting,
        Account $openingEquity,
        Account $shareCapital,
        SavingsProduct $savingsProduct,
        LoanProduct $loanProduct,
        FixedDepositProduct $fdProduct
    ): void {
        $bar = $this->output->createProgressBar(count($rows));

        foreach ($rows as $row) {
            $client = $row['is_new']
                ? $this->createClient($row)
                : Client::findOrFail($row['client_id']);

            if ((float) $row['shares_no'] != 0 || (float) $row['shares_val'] != 0) {
                $this->createShares($client, $row, $accounting, $shareCapital, $openingEquity);
            }
            if ((float) $row['opt_save'] != 0 || (float) $row['save_int'] != 0) {
                $this->createSavings($client, $row, $accounting, $savingsProduct, $openingEquity);
            }
            if ((float) $row['fix_dep_save'] != 0) {
                $this->createFixedDeposit($client, $row, $accounting, $fdProduct, $openingEquity);
            }
            if ((float) $row['loan_value'] != 0 || (float) $row['loan_int'] != 0) {
                $this->createLoan($client, $row, $accounting, $loanProduct, $openingEquity);
            }

            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->line('Rebuilt ' . count($rows) . ' client balances.');
    }

    protected function createClient(array $row): Client
    {
        return Client::create([
            'client_number' => $row['client_number'],
            'client_type'   => 'individual',
            'name'          => $row['name'],
            'status'        => 'active',
            'joining_date'  => self::OPENING_DATE,
        ]);
    }

    protected function createShares(Client $client, array $row, AccountingService $accounting, Account $shareCapital, Account $openingEquity): void
    {
        $amount = (float) $row['shares_val'];

        $share = new MemberShare([
            'client_id'   => $client->id,
            'share_number' => 'SH-' . $client->client_number,
            'share_value' => $amount,
            'amount_paid' => $amount,
            'status'      => $amount > 0 ? 'paid' : 'unpaid',
        ]);
        // created_at isn't fillable and the report filters member_shares by it as
        // the closest thing to a business date -- back-date it explicitly instead
        // of letting Eloquent stamp the real run time, or the report would filter
        // every migrated share out for any "as of" date before today.
        $share->timestamps = false;
        $share->created_at = self::OPENING_DATE;
        $share->updated_at = self::OPENING_DATE;
        $share->save();

        $this->postOpeningEntry(
            $accounting, $shareCapital, $openingEquity, $amount, $client,
            'Opening balance (30/06/2026 statement) - share capital', 'manual', 'credit'
        );
    }

    protected function createSavings(Client $client, array $row, AccountingService $accounting, SavingsProduct $product, Account $openingEquity): void
    {
        // Fold accrued-but-uncredited tiered interest into the opening balance --
        // there is no carryforward column, and last_interest_date below resets the
        // accrual clock to this date so future tiered accrual starts clean from here.
        $balance = (float) $row['opt_save'] + (float) $row['save_int'];

        $account = SavingsAccount::create([
            'client_id'          => $client->id,
            'product_id'         => $product->id,
            'account_number'     => 'SA-' . $client->client_number,
            'balance'            => $balance,
            'status'             => 'active',
            'opened_date'        => self::OPENING_DATE,
            'last_interest_date' => self::OPENING_DATE,
        ]);

        // The Member Summary report (and SavingsService::balanceAsOf()) derive the
        // point-in-time balance from SavingsTransaction.balance_after history, not
        // from savings_accounts.balance directly -- without this row the account's
        // balance is invisible to any "as of" report, permanently reading 0.
        SavingsTransaction::create([
            'savings_account_id' => $account->id,
            'transaction_type'   => $balance >= 0 ? 'deposit' : 'withdrawal',
            'amount'             => $balance,
            'balance_before'     => 0,
            'balance_after'      => $balance,
            'transaction_date'   => self::OPENING_DATE,
            'description'        => 'Opening balance (30/06/2026 statement)',
        ]);

        $liabilityAccount = Account::findOrFail($product->savings_liability_account_id);

        $this->postOpeningEntry(
            $accounting, $liabilityAccount, $openingEquity, $balance, $client,
            "Opening balance (30/06/2026 statement) - savings {$account->account_number}", 'manual', 'credit'
        );
    }

    protected function createFixedDeposit(Client $client, array $row, AccountingService $accounting, FixedDepositProduct $product, Account $openingEquity): void
    {
        $principal   = (float) $row['fix_dep_save'];
        $termMonths  = $product->term_months;
        $rate        = $product->interest_rate;
        $interest    = $principal * ($rate / 100) * ($termMonths / 12);
        $startDate   = self::OPENING_DATE;
        $maturity    = \Carbon\Carbon::parse($startDate)->addMonths($termMonths)->toDateString();

        FixedDeposit::create([
            'client_id'        => $client->id,
            'product_id'       => $product->id,
            'deposit_number'   => 'FD-' . $client->client_number,
            'principal'        => $principal,
            'interest_rate'    => $rate,
            'term_months'      => $termMonths,
            'start_date'       => $startDate,
            'maturity_date'    => $maturity,
            'interest_amount'  => $interest,
            'maturity_amount'  => $principal + $interest,
            'accrued_interest' => 0,
            'status'           => 'active',
        ]);

        $liabilityAccount = Account::findOrFail($product->deposit_liability_account_id);

        $this->postOpeningEntry(
            $accounting, $liabilityAccount, $openingEquity, $principal, $client,
            'Opening balance (30/06/2026 statement) - fixed deposit', 'manual', 'credit'
        );
    }

    protected function createLoan(Client $client, array $row, AccountingService $accounting, LoanProduct $product, Account $openingEquity): void
    {
        $principal = (float) $row['loan_value'];
        $interest  = (float) $row['loan_int'];

        Loan::create([
            'loan_number'           => 'LN-' . $client->client_number,
            'client_id'             => $client->id,
            'loan_product_id'       => $product->id,
            'principal'             => $principal,
            'interest_rate'         => $product->interest_rate,
            'interest_method'       => $product->interest_method,
            'term_months'           => $product->term_months,
            'disbursement_date'     => self::OPENING_DATE,
            'outstanding_principal' => $principal,
            'outstanding_interest'  => $interest,
            'status'                => $principal > 0 ? 'active' : 'closed',
        ]);

        $receivableAccount = Account::findOrFail($product->receivable_account_id);

        // Loan receivable is an asset -- opposite pairing from savings/FD/shares
        // (which are liability/equity): the subledger account debits, equity credits.
        $this->postOpeningEntry(
            $accounting, $receivableAccount, $openingEquity, $principal, $client,
            'Opening balance (30/06/2026 statement) - loan principal', 'manual', 'debit'
        );
    }

    protected function rebuildGroups(array $groups, AccountingService $accounting, Account $openingEquity, Account $groupSavingsGl): void
    {
        foreach ($groups as $groupData) {
            if (empty($groupData['members'])) {
                continue;
            }

            // The Member Summary report only attributes a group's balance to a member
            // row when groups.client_id points at a real Client -- both group entities
            // already exist as Client records in production (FK00001/WK00001 are
            // themselves listed in the statement), so link to those.
            $representativeClient = Client::where('client_number', $groupData['group_number'])->first();

            $group = Group::create([
                'group_number'          => $groupData['group_number'],
                'name'                  => $groupData['name'],
                'registration_date'     => self::OPENING_DATE,
                'gl_account_id'         => $groupSavingsGl->id,
                'client_id'             => $representativeClient?->id,
                'status'                => 'active',
            ]);

            foreach ($groupData['members'] as $member) {
                GroupMember::create([
                    'group_id' => $group->id,
                    'name'     => $member['name'],
                    'balance'  => (float) $member['balance'],
                    'status'   => 'active',
                ]);
            }

            $total = array_sum(array_column($groupData['members'], 'balance'));
            $this->postOpeningEntry(
                $accounting, $groupSavingsGl, $openingEquity, (float) $total, null,
                "Opening balance (30/06/2026 statement) - group {$group->name}", 'groups', 'credit'
            );

            $this->line("Created group {$group->name} with " . count($groupData['members']) . ' members.');
        }
    }

    /**
     * Post a balanced 2-line opening entry against the equity suspense account.
     * $subledgerSide is 'credit' for liability/equity subledger accounts (savings,
     * FD, shares, group savings -- they increase on the credit side) or 'debit' for
     * asset subledger accounts (loan receivable -- increases on the debit side).
     * A negative $amount flips both lines so the subledger account still ends up
     * with the correct (possibly overdrawn) balance.
     */
    protected function postOpeningEntry(
        AccountingService $accounting,
        Account $subledgerAccount,
        Account $equityAccount,
        float $amount,
        ?Client $client,
        string $description,
        string $module,
        string $subledgerSide
    ): void {
        if (abs($amount) < 0.01) {
            return;
        }

        $magnitude = abs($amount);
        $subledgerIsCreditSide = ($subledgerSide === 'credit') === ($amount > 0);

        $lines = $subledgerIsCreditSide
            ? [
                ['account_id' => $equityAccount->id, 'debit' => $magnitude, 'credit' => 0, 'client_id' => $client?->id],
                ['account_id' => $subledgerAccount->id, 'debit' => 0, 'credit' => $magnitude, 'client_id' => $client?->id],
            ]
            : [
                ['account_id' => $subledgerAccount->id, 'debit' => $magnitude, 'credit' => 0, 'client_id' => $client?->id],
                ['account_id' => $equityAccount->id, 'debit' => 0, 'credit' => $magnitude, 'client_id' => $client?->id],
            ];

        $accounting->post(self::OPENING_DATE, $description, $lines, $module, null);
    }
}
