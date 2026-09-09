<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Client;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanRepayment;
use App\Models\LoanSchedule;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\SavingsTransaction;
use App\Services\ClientFinancialSummaryService;
use App\Services\SavingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ClientFinancialSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    private ClientFinancialSummaryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $mockAccounting = Mockery::mock(\App\Services\AccountingService::class);
        $savingsService = new SavingsService($mockAccounting);
        $this->service  = new ClientFinancialSummaryService($savingsService);

        Account::firstOrCreate(['account_code' => '2001'], ['account_name' => 'Savings Liability', 'account_type' => 'liability', 'is_active' => true, 'balance' => 0]);
    }

    private function makeClient(array $overrides = []): Client
    {
        return Client::create(array_merge([
            'client_number' => 'CLT-' . uniqid(),
            'name' => 'Test Client',
            'status' => 'active',
            'membership_fee' => 0, 'membership_fee_paid' => 0, 'membership_fee_status' => 'unpaid',
        ], $overrides));
    }

    public function test_savings_balance_reflects_latest_transaction_on_or_before_as_of_date(): void
    {
        $client  = $this->makeClient();
        $product = SavingsProduct::create([
            'name' => 'Regular Savings', 'code' => 'RS01', 'interest_rate' => 0,
            'interest_method' => 'flat', 'interest_frequency' => 'monthly',
            'minimum_balance' => 0, 'withdrawal_fee' => 0, 'is_active' => true,
            'savings_liability_account_id' => Account::where('account_code', '2001')->value('id'),
        ]);
        $account = SavingsAccount::create([
            'client_id' => $client->id, 'product_id' => $product->id,
            'account_number' => 'SAV-001', 'balance' => 150000, 'status' => 'active',
            'opened_date' => '2026-01-01',
        ]);
        SavingsTransaction::create([
            'savings_account_id' => $account->id, 'transaction_type' => 'deposit',
            'amount' => 100000, 'balance_before' => 0, 'balance_after' => 100000,
            'transaction_date' => '2026-01-01', 'description' => 'Opening deposit',
        ]);
        SavingsTransaction::create([
            'savings_account_id' => $account->id, 'transaction_type' => 'deposit',
            'amount' => 50000, 'balance_before' => 100000, 'balance_after' => 150000,
            'transaction_date' => '2026-02-01', 'description' => 'Top-up',
        ]);

        // As of a date before the second deposit, only the first should count
        $summaryBefore = $this->service->summaryFor($client, '2026-01-15');
        $this->assertEquals(100000.0, $summaryBefore['savings_balance']);

        // As of today (after both), the full balance should count
        $summaryNow = $this->service->summaryFor($client, '2026-03-01');
        $this->assertEquals(150000.0, $summaryNow['savings_balance']);
        $this->assertEquals(150000.0, $summaryNow['total_assets']);
    }

    public function test_loan_outstanding_and_total_liability(): void
    {
        $client  = $this->makeClient();
        $product = LoanProduct::create([
            'name' => 'Test Loan Product', 'code' => 'LP01', 'interest_rate' => 10,
            'interest_method' => 'flat', 'repayment_frequency' => 'monthly',
            'term_months' => 12, 'penalty_rate' => 0, 'min_amount' => 0, 'max_amount' => 1000000,
            'is_active' => true,
        ]);
        $loan = Loan::create([
            'loan_number' => 'LN-001', 'client_id' => $client->id, 'loan_product_id' => $product->id,
            'principal' => 500000, 'interest_rate' => 10, 'interest_method' => 'flat',
            'repayment_frequency' => 'monthly', 'term_months' => 12,
            'disbursement_date' => '2026-01-01', 'maturity_date' => '2026-12-31',
            'outstanding_principal' => 400000, 'outstanding_interest' => 20000, 'outstanding_penalty' => 0,
            'status' => 'active',
        ]);
        LoanRepayment::create([
            'loan_id' => $loan->id, 'payment_date' => '2026-02-01', 'amount' => 100000,
            'principal_paid' => 100000, 'interest_paid' => 0, 'penalty_paid' => 0,
            'payment_method' => 'direct',
        ]);
        LoanSchedule::create([
            'loan_id' => $loan->id, 'installment_no' => 1, 'due_date' => '2026-02-01',
            'principal_due' => 41666, 'interest_due' => 4166, 'total_due' => 45832,
            'balance_after' => 458334, 'principal_paid' => 41666, 'interest_paid' => 4166,
            'status' => 'paid',
        ]);
        LoanSchedule::create([
            'loan_id' => $loan->id, 'installment_no' => 2, 'due_date' => '2026-03-01',
            'principal_due' => 41666, 'interest_due' => 4166, 'total_due' => 45832,
            'balance_after' => 416668, 'principal_paid' => 0, 'interest_paid' => 0,
            'status' => 'pending',
        ]);

        $summary = $this->service->summaryFor($client, '2026-03-15');

        // outstanding principal = 500000 - 100000 repaid
        $this->assertEquals(400000.0, $summary['loan_principal']);
        // outstanding interest = sum of GREATEST(0, interest_due - interest_paid) for schedules due on/before as-of
        $this->assertEquals(4166.0, $summary['loan_interest']);
        $this->assertEquals(404166.0, $summary['total_liability']);
    }

    public function test_client_with_no_products_returns_zeroed_summary(): void
    {
        $client = $this->makeClient();

        $summary = $this->service->summaryFor($client);

        $this->assertEquals(0.0, $summary['total_assets']);
        $this->assertEquals(0.0, $summary['total_liability']);
    }

    public function test_summaries_for_matches_summary_for_across_multiple_clients(): void
    {
        $product = SavingsProduct::create([
            'name' => 'Regular Savings', 'code' => 'RS02', 'interest_rate' => 0,
            'interest_method' => 'flat', 'interest_frequency' => 'monthly',
            'minimum_balance' => 0, 'withdrawal_fee' => 0, 'is_active' => true,
            'savings_liability_account_id' => Account::where('account_code', '2001')->value('id'),
        ]);

        $clientA = $this->makeClient(['client_number' => 'CLT-A']);
        $accountA = SavingsAccount::create([
            'client_id' => $clientA->id, 'product_id' => $product->id,
            'account_number' => 'SAV-A', 'balance' => 20000, 'status' => 'active', 'opened_date' => '2026-01-01',
        ]);
        SavingsTransaction::create([
            'savings_account_id' => $accountA->id, 'transaction_type' => 'deposit',
            'amount' => 20000, 'balance_before' => 0, 'balance_after' => 20000,
            'transaction_date' => '2026-01-01', 'description' => 'Deposit',
        ]);

        $clientB = $this->makeClient(['client_number' => 'CLT-B']);
        $accountB = SavingsAccount::create([
            'client_id' => $clientB->id, 'product_id' => $product->id,
            'account_number' => 'SAV-B', 'balance' => 70000, 'status' => 'active', 'opened_date' => '2026-01-01',
        ]);
        SavingsTransaction::create([
            'savings_account_id' => $accountB->id, 'transaction_type' => 'deposit',
            'amount' => 70000, 'balance_before' => 0, 'balance_after' => 70000,
            'transaction_date' => '2026-01-01', 'description' => 'Deposit',
        ]);

        $bulk = $this->service->summariesFor(collect([$clientA->id, $clientB->id]));

        $this->assertEquals(20000.0, $bulk->get($clientA->id)['savings_balance']);
        $this->assertEquals(70000.0, $bulk->get($clientB->id)['savings_balance']);
        $this->assertEquals($this->service->summaryFor($clientA)['savings_balance'], $bulk->get($clientA->id)['savings_balance']);
    }
}
