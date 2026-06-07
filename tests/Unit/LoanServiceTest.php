<?php

namespace Tests\Unit;

use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanSchedule;
use App\Models\Client;
use App\Models\User;
use App\Models\Account;
use App\Models\Transaction;
use App\Services\AccountingService;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class LoanServiceTest extends TestCase
{
    use RefreshDatabase;

    private LoanService $loanService;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock AccountingService so tests don't need GL accounts or a logged-in user
        $stubTxn = Transaction::create([
            'date'        => now()->toDateString(),
            'reference'   => 'TXN-TEST-001',
            'description' => 'Test transaction',
            'module'      => 'loan',
            'module_id'   => 1,
        ]);

        $mockAccounting = Mockery::mock(AccountingService::class);
        $mockAccounting->shouldReceive('post')->andReturn($stubTxn);
        $this->loanService = new LoanService($mockAccounting);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeLoanProduct(array $overrides = []): LoanProduct
    {
        return LoanProduct::create(array_merge([
            'name'              => 'Test Product',
            'code'              => 'TP01',
            'interest_rate'     => 24.0,   // 24% p.a. flat
            'interest_method'   => 'flat',
            'term_months'       => 12,
            'penalty_rate'      => 0,
            'min_amount'        => 1000,
            'max_amount'        => 10000000,
            'is_active'         => true,
        ], $overrides));
    }

    private function makeClient(): Client
    {
        return Client::create([
            'client_number'         => 'CLT-001',
            'name'                  => 'Test Client',
            'status'                => 'active',
            'membership_fee'        => 50000,
            'membership_fee_paid'   => 0,
            'membership_fee_status' => 'unpaid',
        ]);
    }

    private function makeLoan(LoanProduct $product, Client $client, array $overrides = []): Loan
    {
        return Loan::create(array_merge([
            'loan_number'           => 'LN-TEST-001',
            'client_id'             => $client->id,
            'loan_product_id'       => $product->id,
            'principal'             => 120000,
            'interest_rate'         => $product->interest_rate,
            'interest_method'       => $product->interest_method,
            'term_months'           => $product->term_months,
            'status'                => 'active',
            'disbursement_date'     => '2026-01-01',
            'maturity_date'         => '2027-01-01',
            'outstanding_principal' => 120000,
            'outstanding_interest'  => 0,
            'outstanding_penalty'   => 0,
        ], $overrides));
    }

    // -----------------------------------------------------------------------
    // Schedule generation — flat rate
    // -----------------------------------------------------------------------

    public function test_flat_rate_schedule_generates_correct_number_of_installments(): void
    {
        $product = $this->makeLoanProduct(['interest_method' => 'flat', 'term_months' => 12]);
        $client  = $this->makeClient();
        $loan    = $this->makeLoan($product, $client);

        $this->loanService->generateSchedule($loan);

        $this->assertCount(12, $loan->schedules);
    }

    public function test_flat_rate_schedule_principal_sums_to_loan_amount(): void
    {
        $product = $this->makeLoanProduct(['interest_method' => 'flat', 'term_months' => 12]);
        $client  = $this->makeClient();
        $loan    = $this->makeLoan($product, $client, ['principal' => 120000]);

        $this->loanService->generateSchedule($loan);

        $totalPrincipal = $loan->schedules()->sum('principal_due');
        $this->assertEqualsWithDelta(120000, $totalPrincipal, 1.0,
            'Scheduled principal should sum to loan principal');
    }

    public function test_flat_rate_schedule_interest_sums_correctly(): void
    {
        // 120,000 at 24% p.a. flat for 12 months = 120,000 * 0.24 * 1 = 28,800 total interest
        $product = $this->makeLoanProduct(['interest_method' => 'flat', 'interest_rate' => 24, 'term_months' => 12]);
        $client  = $this->makeClient();
        $loan    = $this->makeLoan($product, $client, ['principal' => 120000, 'interest_rate' => 24]);

        $this->loanService->generateSchedule($loan);

        $totalInterest = $loan->schedules()->sum('interest_due');
        $this->assertEqualsWithDelta(28800, $totalInterest, 1.0,
            '24% p.a. flat on 120,000 for 12 months = 28,800 interest');
    }

    public function test_flat_rate_each_installment_is_equal(): void
    {
        $product = $this->makeLoanProduct(['interest_method' => 'flat', 'interest_rate' => 24, 'term_months' => 12]);
        $client  = $this->makeClient();
        $loan    = $this->makeLoan($product, $client, ['principal' => 120000]);

        $this->loanService->generateSchedule($loan);

        $totals = $loan->schedules()->pluck('total_due')->unique();
        $this->assertCount(1, $totals, 'All flat-rate installments should be the same amount');
    }

    public function test_reducing_balance_schedule_principal_sums_to_loan_amount(): void
    {
        $product = $this->makeLoanProduct(['interest_method' => 'reducing', 'interest_rate' => 24, 'term_months' => 12]);
        $client  = $this->makeClient();
        $loan    = $this->makeLoan($product, $client, [
            'interest_method' => 'reducing',
            'interest_rate'   => 24,
            'principal'       => 100000,
        ]);

        $this->loanService->generateSchedule($loan);

        $totalPrincipal = $loan->schedules()->sum('principal_due');
        $this->assertEqualsWithDelta(100000, $totalPrincipal, 2.0,
            'Reducing balance principal should sum to loan amount');
    }

    public function test_schedule_outstanding_interest_updated_after_generation(): void
    {
        $product = $this->makeLoanProduct(['interest_method' => 'flat', 'interest_rate' => 24, 'term_months' => 12]);
        $client  = $this->makeClient();
        $loan    = $this->makeLoan($product, $client, ['principal' => 120000]);

        $this->loanService->generateSchedule($loan);
        $loan->refresh();

        $this->assertGreaterThan(0, $loan->outstanding_interest,
            'outstanding_interest should be set after schedule generation');
        $this->assertEqualsWithDelta(28800, $loan->outstanding_interest, 1.0);
    }

    // -----------------------------------------------------------------------
    // Repayment allocation
    // -----------------------------------------------------------------------

    private function makeLoanWithSchedule(array $schedules): Loan
    {
        $product = $this->makeLoanProduct(['penalty_rate' => 0]);
        $client  = $this->makeClient();

        $totalPrincipal = array_sum(array_column($schedules, 'principal_due'));
        $totalInterest  = array_sum(array_column($schedules, 'interest_due'));

        $loan = $this->makeLoan($product, $client, [
            'principal'             => $totalPrincipal,
            'outstanding_principal' => $totalPrincipal,
            'outstanding_interest'  => $totalInterest,
            'disbursement_date'     => '2026-01-01',
        ]);

        foreach ($schedules as $i => $s) {
            LoanSchedule::create([
                'loan_id'        => $loan->id,
                'installment_no' => $i + 1,
                'due_date'       => '2026-0' . ($i + 2) . '-01',
                'principal_due'  => $s['principal_due'],
                'interest_due'   => $s['interest_due'],
                'total_due'      => $s['principal_due'] + $s['interest_due'],
                'balance_after'  => 0,
                'principal_paid' => 0,
                'interest_paid'  => 0,
                'status'         => 'pending',
            ]);
        }

        return $loan;
    }

    public function test_repayment_pays_interest_before_principal_on_first_installment(): void
    {
        // Installment 1: 10,000 principal + 2,400 interest = 12,400
        $loan = $this->makeLoanWithSchedule([
            ['principal_due' => 10000, 'interest_due' => 2400],
            ['principal_due' => 10000, 'interest_due' => 2400],
        ]);

        $repayment = $this->loanService->processRepayment($loan, [
            'amount'         => 2400,  // exactly covers interest of installment 1
            'payment_date'   => '2026-02-01',
            'payment_method' => 'cash',
        ]);

        $this->assertEquals(2400, $repayment->interest_paid);
        $this->assertEquals(0,    $repayment->principal_paid,
            'Should pay interest first, no principal yet');
    }

    public function test_repayment_pays_principal_after_interest_on_same_installment(): void
    {
        $loan = $this->makeLoanWithSchedule([
            ['principal_due' => 10000, 'interest_due' => 2400],
        ]);

        $repayment = $this->loanService->processRepayment($loan, [
            'amount'         => 12400,
            'payment_date'   => '2026-02-01',
            'payment_method' => 'cash',
        ]);

        $this->assertEquals(2400,  $repayment->interest_paid);
        $this->assertEquals(10000, $repayment->principal_paid);
    }

    public function test_repayment_does_not_skip_to_next_installment_principal_before_first_installment_interest(): void
    {
        // Two installments. Pay only 1,000 — should go to interest of installment 1 only
        $loan = $this->makeLoanWithSchedule([
            ['principal_due' => 10000, 'interest_due' => 2400],
            ['principal_due' => 10000, 'interest_due' => 2400],
        ]);

        $repayment = $this->loanService->processRepayment($loan, [
            'amount'         => 1000,
            'payment_date'   => '2026-02-01',
            'payment_method' => 'cash',
        ]);

        $this->assertEquals(1000, $repayment->interest_paid);
        $this->assertEquals(0,    $repayment->principal_paid);
        $this->assertEquals(0,    $repayment->penalty_paid);
    }

    public function test_repayment_spans_multiple_installments(): void
    {
        // 2 installments, 10,000 + 2,400 each. Pay 24,800 = both installments fully
        $loan = $this->makeLoanWithSchedule([
            ['principal_due' => 10000, 'interest_due' => 2400],
            ['principal_due' => 10000, 'interest_due' => 2400],
        ]);

        $repayment = $this->loanService->processRepayment($loan, [
            'amount'         => 24800,
            'payment_date'   => '2026-02-01',
            'payment_method' => 'cash',
        ]);

        $this->assertEquals(4800,  $repayment->interest_paid);
        $this->assertEquals(20000, $repayment->principal_paid);

        $this->assertEquals(2, $loan->schedules()->where('status', 'paid')->count(),
            'Both installments should be marked paid');
    }

    public function test_repayment_marks_installment_partial_when_not_fully_paid(): void
    {
        $loan = $this->makeLoanWithSchedule([
            ['principal_due' => 10000, 'interest_due' => 2400],
        ]);

        $this->loanService->processRepayment($loan, [
            'amount'         => 5000,
            'payment_date'   => '2026-02-01',
            'payment_method' => 'cash',
        ]);

        $schedule = $loan->schedules()->first();
        $this->assertEquals('partial', $schedule->status);
    }

    public function test_repayment_reduces_outstanding_principal_and_interest(): void
    {
        $loan = $this->makeLoanWithSchedule([
            ['principal_due' => 10000, 'interest_due' => 2400],
        ]);

        $this->loanService->processRepayment($loan, [
            'amount'         => 12400,
            'payment_date'   => '2026-02-01',
            'payment_method' => 'cash',
        ]);

        $loan->refresh();
        $this->assertEquals(0, $loan->outstanding_principal);
        $this->assertEquals(0, $loan->outstanding_interest);
    }

    public function test_loan_status_becomes_closed_when_fully_paid(): void
    {
        $loan = $this->makeLoanWithSchedule([
            ['principal_due' => 10000, 'interest_due' => 2400],
        ]);

        $this->loanService->processRepayment($loan, [
            'amount'         => 12400,
            'payment_date'   => '2026-02-01',
            'payment_method' => 'cash',
        ]);

        $loan->refresh();
        $this->assertEquals('closed', $loan->status);
    }

    public function test_partial_payment_does_not_close_loan(): void
    {
        $loan = $this->makeLoanWithSchedule([
            ['principal_due' => 10000, 'interest_due' => 2400],
        ]);

        $this->loanService->processRepayment($loan, [
            'amount'         => 6000,
            'payment_date'   => '2026-02-01',
            'payment_method' => 'cash',
        ]);

        $loan->refresh();
        $this->assertEquals('active', $loan->status);
    }

    // -----------------------------------------------------------------------
    // Early settlement
    // -----------------------------------------------------------------------

    public function test_early_settlement_excludes_future_interest(): void
    {
        $product = $this->makeLoanProduct(['penalty_rate' => 0]);
        $client  = $this->makeClient();
        $loan    = $this->makeLoan($product, $client, [
            'principal'             => 120000,
            'outstanding_principal' => 120000,
            'outstanding_interest'  => 28800,
            'disbursement_date'     => '2026-01-01',
        ]);

        // 12 installments: principal 10,000 + interest 2,400 each
        for ($i = 1; $i <= 12; $i++) {
            LoanSchedule::create([
                'loan_id'        => $loan->id,
                'installment_no' => $i,
                'due_date'       => "2026-{$this->padMonth($i + 1)}-01",
                'principal_due'  => 10000,
                'interest_due'   => 2400,
                'total_due'      => 12400,
                'balance_after'  => 120000 - ($i * 10000),
                'principal_paid' => 0,
                'interest_paid'  => 0,
                'status'         => 'pending',
            ]);
        }

        // Settle on 2026-01-15 — period 1 has started (started 2026-01-01), periods 2-12 have not
        $result = $this->loanService->calculateEarlySettlement($loan, '2026-01-15');

        $this->assertEquals(120000, $result['principal']);
        $this->assertEquals(2400,   $result['interest'],
            'Only installment 1 interest should be charged (period started 2026-01-01)');
        $this->assertEquals(0,      $result['penalty']);
        $this->assertEquals(122400, $result['total']);
    }

    public function test_early_settlement_includes_interest_for_all_started_periods(): void
    {
        $product = $this->makeLoanProduct(['penalty_rate' => 0]);
        $client  = $this->makeClient();
        $loan    = $this->makeLoan($product, $client, [
            'principal'             => 120000,
            'outstanding_principal' => 120000,
            'outstanding_interest'  => 28800,
            'disbursement_date'     => '2026-01-01',
        ]);

        for ($i = 1; $i <= 12; $i++) {
            LoanSchedule::create([
                'loan_id'        => $loan->id,
                'installment_no' => $i,
                'due_date'       => "2026-{$this->padMonth($i + 1)}-01",
                'principal_due'  => 10000,
                'interest_due'   => 2400,
                'total_due'      => 12400,
                'balance_after'  => 0,
                'principal_paid' => 0,
                'interest_paid'  => 0,
                'status'         => 'pending',
            ]);
        }

        // Settle on 2026-03-15 — period 1 started 2026-01-01, period 2 started 2026-02-01,
        // period 3 started 2026-03-01 — so 3 periods have started
        $result = $this->loanService->calculateEarlySettlement($loan, '2026-03-15');

        $this->assertEquals(2400 * 3, $result['interest'],
            '3 periods have started, so 3 × 2,400 interest should be charged');
    }

    public function test_early_settlement_does_not_double_charge_already_paid_interest(): void
    {
        $product = $this->makeLoanProduct(['penalty_rate' => 0]);
        $client  = $this->makeClient();
        $loan    = $this->makeLoan($product, $client, [
            'principal'             => 110000,
            'outstanding_principal' => 110000,
            'outstanding_interest'  => 26400,
            'disbursement_date'     => '2026-01-01',
        ]);

        // Installment 1 already partially paid (interest fully paid)
        LoanSchedule::create([
            'loan_id'        => $loan->id,
            'installment_no' => 1,
            'due_date'       => '2026-02-01',
            'principal_due'  => 10000,
            'interest_due'   => 2400,
            'total_due'      => 12400,
            'balance_after'  => 110000,
            'principal_paid' => 0,
            'interest_paid'  => 2400,  // interest already paid
            'status'         => 'partial',
        ]);
        for ($i = 2; $i <= 12; $i++) {
            LoanSchedule::create([
                'loan_id'        => $loan->id,
                'installment_no' => $i,
                'due_date'       => "2026-{$this->padMonth($i + 1)}-01",
                'principal_due'  => 10000,
                'interest_due'   => 2400,
                'total_due'      => 12400,
                'balance_after'  => 0,
                'principal_paid' => 0,
                'interest_paid'  => 0,
                'status'         => 'pending',
            ]);
        }

        $result = $this->loanService->calculateEarlySettlement($loan, '2026-01-20');

        $this->assertEquals(0, $result['interest'],
            'Interest already paid should not be charged again');
    }

    // -----------------------------------------------------------------------
    // Helper
    // -----------------------------------------------------------------------

    private function padMonth(int $month): string
    {
        return str_pad((string) ($month > 12 ? $month - 12 : $month), 2, '0', STR_PAD_LEFT);
    }
}
