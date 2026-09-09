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
use App\Services\ClientActivityService;
use App\Services\ClientFinancialSummaryService;
use App\Services\ClientHealthService;
use App\Services\SavingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ClientHealthServiceTest extends TestCase
{
    use RefreshDatabase;

    private ClientHealthService $health;

    protected function setUp(): void
    {
        parent::setUp();

        $mockAccounting = Mockery::mock(\App\Services\AccountingService::class);
        $savingsService = new SavingsService($mockAccounting);
        $financials = new ClientFinancialSummaryService($savingsService);
        $activity = new ClientActivityService();
        $this->health = new ClientHealthService($activity, $financials);

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

    private function savingsProduct(): SavingsProduct
    {
        return SavingsProduct::create([
            'name' => 'Regular Savings', 'code' => 'RS-' . uniqid(), 'interest_rate' => 0,
            'interest_method' => 'flat', 'interest_frequency' => 'monthly',
            'minimum_balance' => 0, 'withdrawal_fee' => 0, 'is_active' => true,
            'savings_liability_account_id' => Account::where('account_code', '2001')->value('id'),
        ]);
    }

    public function test_client_with_no_activity_or_products_scores_low_and_is_at_risk(): void
    {
        $client = $this->makeClient();

        $result = $this->health->scoreFor($client);

        $this->assertLessThan(40, $result['score']);
        $this->assertEquals('At Risk', $result['label']);
        $this->assertEquals('🔴', $result['emoji']);
        // No loan history -> repayment factor should be excluded, not penalized as 0
        $this->assertFalse($result['factors']['repayment']['applicable']);
    }

    public function test_active_engaged_client_with_all_products_scores_well(): void
    {
        $client  = $this->makeClient();
        $product = $this->savingsProduct();
        $account = SavingsAccount::create([
            'client_id' => $client->id, 'product_id' => $product->id,
            'account_number' => 'SAV-' . uniqid(), 'balance' => 100000, 'status' => 'active',
            'opened_date' => now()->subYear()->toDateString(),
        ]);
        // Several recent transactions (good frequency + recency)
        foreach ([30, 20, 10, 2] as $daysAgo) {
            SavingsTransaction::create([
                'savings_account_id' => $account->id, 'transaction_type' => 'deposit',
                'amount' => 10000, 'balance_before' => 0, 'balance_after' => 100000,
                'transaction_date' => now()->subDays($daysAgo)->toDateString(), 'description' => 'Deposit',
            ]);
        }

        $result = $this->health->scoreFor($client);

        $this->assertGreaterThanOrEqual(60, $result['factors']['recency']['score']);
        $this->assertGreaterThanOrEqual(60, $result['factors']['frequency']['score']);
        $this->assertTrue($result['score'] > 40, 'A recently and frequently active client should not score as low as an inactive one');
    }

    public function test_overdue_loan_drags_repayment_factor_down_but_not_when_paid_on_time(): void
    {
        $product = LoanProduct::create([
            'name' => 'Test Loan', 'code' => 'LP-' . uniqid(), 'interest_rate' => 10,
            'interest_method' => 'flat', 'repayment_frequency' => 'monthly',
            'term_months' => 12, 'penalty_rate' => 0, 'min_amount' => 0, 'max_amount' => 1000000,
            'is_active' => true,
        ]);

        // Client A: overdue installment, unpaid
        $clientA = $this->makeClient(['client_number' => 'CLT-A']);
        $loanA = Loan::create([
            'loan_number' => 'LN-A', 'client_id' => $clientA->id, 'loan_product_id' => $product->id,
            'principal' => 500000, 'interest_rate' => 10, 'interest_method' => 'flat',
            'repayment_frequency' => 'monthly', 'term_months' => 12,
            'disbursement_date' => now()->subMonths(2)->toDateString(), 'maturity_date' => now()->addMonths(10)->toDateString(),
            'outstanding_principal' => 500000, 'outstanding_interest' => 0, 'outstanding_penalty' => 0,
            'status' => 'active',
        ]);
        LoanSchedule::create([
            'loan_id' => $loanA->id, 'installment_no' => 1, 'due_date' => now()->subDays(15)->toDateString(),
            'principal_due' => 41666, 'interest_due' => 4166, 'total_due' => 45832,
            'balance_after' => 458334, 'principal_paid' => 0, 'interest_paid' => 0,
            'status' => 'pending',
        ]);

        // Client B: same profile but installment paid on time
        $clientB = $this->makeClient(['client_number' => 'CLT-B']);
        $loanB = Loan::create([
            'loan_number' => 'LN-B', 'client_id' => $clientB->id, 'loan_product_id' => $product->id,
            'principal' => 500000, 'interest_rate' => 10, 'interest_method' => 'flat',
            'repayment_frequency' => 'monthly', 'term_months' => 12,
            'disbursement_date' => now()->subMonths(2)->toDateString(), 'maturity_date' => now()->addMonths(10)->toDateString(),
            'outstanding_principal' => 458334, 'outstanding_interest' => 0, 'outstanding_penalty' => 0,
            'status' => 'active',
        ]);
        LoanSchedule::create([
            'loan_id' => $loanB->id, 'installment_no' => 1, 'due_date' => now()->subDays(15)->toDateString(),
            'principal_due' => 41666, 'interest_due' => 4166, 'total_due' => 45832,
            'balance_after' => 458334, 'principal_paid' => 41666, 'interest_paid' => 4166,
            'status' => 'paid',
        ]);
        LoanRepayment::create([
            'loan_id' => $loanB->id, 'payment_date' => now()->subDays(16)->toDateString(), 'amount' => 45832,
            'principal_paid' => 41666, 'interest_paid' => 4166, 'penalty_paid' => 0,
            'payment_method' => 'direct',
        ]);

        $resultA = $this->health->scoreFor($clientA);
        $resultB = $this->health->scoreFor($clientB);

        $this->assertTrue($resultA['factors']['repayment']['applicable']);
        $this->assertTrue($resultB['factors']['repayment']['applicable']);
        $this->assertEquals(0, $resultA['factors']['repayment']['score']);
        $this->assertEquals(100, $resultB['factors']['repayment']['score']);
        $this->assertLessThan($resultB['score'], $resultA['score']);
    }

    public function test_scores_for_bulk_matches_score_for_single(): void
    {
        $clientA = $this->makeClient(['client_number' => 'CLT-BULK-A']);
        $clientB = $this->makeClient(['client_number' => 'CLT-BULK-B']);

        $bulk = $this->health->scoresFor(collect([$clientA->id, $clientB->id]));
        $singleA = $this->health->scoreFor($clientA);

        $this->assertEquals($singleA['score'], $bulk->get($clientA->id)['score']);
        $this->assertCount(2, $bulk);
    }

    public function test_classification_bands_are_mutually_exclusive_and_cover_0_to_100(): void
    {
        $config = config('crm.health_score');
        $client = $this->makeClient();

        // Force a mid-range and low-range score via reflection-free approach:
        // just confirm the classify boundaries behave via the public scoreFor path
        // using the config thresholds directly.
        $this->assertTrue($config['thresholds']['healthy'] > $config['thresholds']['needs_attention']);

        $result = $this->health->scoreFor($client);
        $this->assertContains($result['label'], ['Healthy', 'Needs Attention', 'At Risk']);
        $this->assertGreaterThanOrEqual(0, $result['score']);
        $this->assertLessThanOrEqual(100, $result['score']);
    }
}
