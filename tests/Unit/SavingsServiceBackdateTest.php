<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\SavingsTransaction;
use App\Models\Client;
use App\Services\AccountingService;
use App\Services\SavingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SavingsServiceBackdateTest extends TestCase
{
    use RefreshDatabase;

    private SavingsService $savingsService;
    private SavingsAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $mockAccounting = Mockery::mock(AccountingService::class);
        $mockAccounting->shouldReceive('post')->andReturnUsing(function () {
            return \App\Models\Transaction::create([
                'date' => now()->toDateString(), 'reference' => 'TXN-SAV-001',
                'description' => 'Test', 'module' => 'savings', 'module_id' => 1,
            ]);
        });
        $this->savingsService = new SavingsService($mockAccounting);

        Account::firstOrCreate(['account_code' => '1001'], ['account_name' => 'Cash',             'account_type' => 'asset',     'is_active' => true, 'balance' => 0]);
        Account::firstOrCreate(['account_code' => '2001'], ['account_name' => 'Savings Liability', 'account_type' => 'liability', 'is_active' => true, 'balance' => 0]);
        Account::firstOrCreate(['account_code' => '4002'], ['account_name' => 'Fee Income',        'account_type' => 'revenue',   'is_active' => true, 'balance' => 0]);

        $product = SavingsProduct::create([
            'name'                       => 'Regular Savings',
            'code'                       => 'RS01',
            'interest_rate'              => 0,
            'interest_frequency'         => 'monthly',
            'minimum_balance'            => 0,
            'withdrawal_fee'             => 0,
            'is_active'                  => true,
            'savings_liability_account_id' => Account::where('account_code', '2001')->value('id'),
        ]);

        $client = Client::create([
            'client_number' => 'CLT-001', 'name' => 'Test Client',
            'status' => 'active', 'membership_fee' => 0,
            'membership_fee_paid' => 0, 'membership_fee_status' => 'unpaid',
        ]);

        $this->account = SavingsAccount::create([
            'client_id'      => $client->id,
            'product_id'     => $product->id,
            'account_number' => 'SAV26-00001',
            'balance'        => 50000,   // current balance is 50,000 today
            'status'         => 'active',
            'opened_date'    => '2026-01-01',
        ]);
    }

    public function test_withdrawal_blocked_when_balance_was_zero_on_payment_date(): void
    {
        // Account opened in Jan, no transactions recorded before March.
        // Trying to withdraw 10,000 "as of January" should fail — balance was 0 then.

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Insufficient balance as of/');

        $this->savingsService->withdraw($this->account, 10000, '2026-01-15', 'Test withdrawal');
    }

    public function test_withdrawal_allowed_when_balance_was_sufficient_on_payment_date(): void
    {
        // Record a deposit on Jan 10 giving the account 30,000 as of that date
        SavingsTransaction::create([
            'savings_account_id' => $this->account->id,
            'transaction_type'   => 'deposit',
            'amount'             => 30000,
            'balance_before'     => 0,
            'balance_after'      => 30000,
            'transaction_date'   => '2026-01-10',
            'description'        => 'Initial deposit',
        ]);

        // Withdraw 20,000 as of Jan 15 — balance was 30,000, so it should pass
        $txn = $this->savingsService->withdraw($this->account, 20000, '2026-01-15', 'Test withdrawal');

        $this->assertEquals(20000, $txn->amount);
        $this->assertEquals(30000, $txn->balance_before);
        $this->assertEquals(10000, $txn->balance_after);
    }

    public function test_withdrawal_records_historical_balance_not_current_balance(): void
    {
        // Current balance = 50,000 but on Jan 10 only 15,000 existed
        SavingsTransaction::create([
            'savings_account_id' => $this->account->id,
            'transaction_type'   => 'deposit',
            'amount'             => 15000,
            'balance_before'     => 0,
            'balance_after'      => 15000,
            'transaction_date'   => '2026-01-10',
            'description'        => 'Jan deposit',
        ]);

        $txn = $this->savingsService->withdraw($this->account, 10000, '2026-01-20', 'Backdated withdrawal');

        // balance_before in the ledger record should be 15,000 (historical), not 50,000 (current)
        $this->assertEquals(15000, $txn->balance_before,
            'balance_before should reflect what the balance was on the transaction date');
        $this->assertEquals(5000, $txn->balance_after);
    }

    public function test_deposit_records_historical_balance_before(): void
    {
        SavingsTransaction::create([
            'savings_account_id' => $this->account->id,
            'transaction_type'   => 'deposit',
            'amount'             => 20000,
            'balance_before'     => 0,
            'balance_after'      => 20000,
            'transaction_date'   => '2026-01-05',
            'description'        => 'First deposit',
        ]);

        $txn = $this->savingsService->deposit($this->account, 10000, '2026-01-15', 'Second deposit');

        // balance_before should be 20,000 (what existed on Jan 15), not 50,000 (current)
        $this->assertEquals(20000, $txn->balance_before,
            'Deposit balance_before should reflect historical balance on the date');
        $this->assertEquals(30000, $txn->balance_after);
    }
}
