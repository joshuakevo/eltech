<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Client;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanRepayment;
use App\Models\LoanSchedule;
use App\Models\Transaction;
use App\Models\TransactionLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TransactionReversalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create super_admin role and user
        $role        = Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);

        // Ensure minimal GL accounts exist (seeder may have already created them)
        Account::firstOrCreate(['account_code' => '1001'], ['account_name' => 'Cash',               'account_type' => 'asset',     'is_active' => true, 'balance' => 0]);
        Account::firstOrCreate(['account_code' => '4008'], ['account_name' => 'Membership Fee Inc', 'account_type' => 'revenue',   'is_active' => true, 'balance' => 0]);
        Account::firstOrCreate(['account_code' => '4001'], ['account_name' => 'Interest Income',    'account_type' => 'revenue',   'is_active' => true, 'balance' => 0]);
        Account::firstOrCreate(['account_code' => '1002'], ['account_name' => 'Loan Portfolio',     'account_type' => 'asset',     'is_active' => true, 'balance' => 0]);
        Account::firstOrCreate(['account_code' => '2001'], ['account_name' => 'Savings Liability',  'account_type' => 'liability', 'is_active' => true, 'balance' => 0]);
    }

    // -----------------------------------------------------------------------
    // Membership fee reversal
    // -----------------------------------------------------------------------

    public function test_deleting_membership_fee_transaction_resets_client_fee_status(): void
    {
        $client = Client::create([
            'client_number'         => 'CLT-001',
            'name'                  => 'Jane Test',
            'status'                => 'active',
            'membership_fee'        => 50000,
            'membership_fee_paid'   => 50000,
            'membership_fee_status' => 'paid',
        ]);

        $membershipAccount = Account::where('account_code', '4008')->first();

        // Create the membership fee GL transaction
        $transaction = Transaction::create([
            'date'        => '2026-03-01',
            'reference'   => 'TXN-MF-001',
            'description' => 'Membership fee payment — CLT-001',
            'module'      => 'client',
            'module_id'   => $client->id,
        ]);

        TransactionLine::create([
            'transaction_id' => $transaction->id,
            'account_id'     => Account::where('account_code', '1001')->value('id'),
            'debit'          => 50000,
            'credit'         => 0,
            'description'    => 'Membership fee — Jane Test',
        ]);
        TransactionLine::create([
            'transaction_id' => $transaction->id,
            'account_id'     => $membershipAccount->id,
            'debit'          => 0,
            'credit'         => 50000,
            'description'    => 'Membership fee — Jane Test',
        ]);

        // Delete transaction via HTTP
        $response = $this->actingAs($this->admin)
            ->delete(route('transactions.destroy', $transaction));

        $response->assertRedirect(route('transactions.index'));

        $client->refresh();
        $this->assertEquals(0,        $client->membership_fee_paid);
        $this->assertEquals('unpaid', $client->membership_fee_status,
            'Client status must be rolled back to unpaid when transaction is deleted');
    }

    public function test_partial_membership_fee_deletion_rolls_back_correctly(): void
    {
        $client = Client::create([
            'client_number'         => 'CLT-002',
            'name'                  => 'John Test',
            'status'                => 'active',
            'membership_fee'        => 100000,
            'membership_fee_paid'   => 40000,
            'membership_fee_status' => 'partial',
        ]);

        $membershipAccount = Account::where('account_code', '4008')->first();

        $transaction = Transaction::create([
            'date'        => '2026-03-01',
            'reference'   => 'TXN-MF-002',
            'description' => 'Membership fee payment — CLT-002',
            'module'      => 'client',
            'module_id'   => $client->id,
        ]);

        TransactionLine::create([
            'transaction_id' => $transaction->id,
            'account_id'     => Account::where('account_code', '1001')->value('id'),
            'debit'          => 40000, 'credit' => 0, 'description' => '',
        ]);
        TransactionLine::create([
            'transaction_id' => $transaction->id,
            'account_id'     => $membershipAccount->id,
            'debit'          => 0, 'credit' => 40000, 'description' => '',
        ]);

        $this->actingAs($this->admin)->delete(route('transactions.destroy', $transaction));

        $client->refresh();
        $this->assertEquals(0,        $client->membership_fee_paid);
        $this->assertEquals('unpaid', $client->membership_fee_status);
    }

    // -----------------------------------------------------------------------
    // Loan repayment reversal
    // -----------------------------------------------------------------------

    private function makeLoanWithRepayment(): array
    {
        $product = LoanProduct::create([
            'name'            => 'Test Product',
            'code'            => 'TP01',
            'interest_rate'   => 24.0,
            'interest_method' => 'flat',
            'term_months'     => 12,
            'penalty_rate'    => 0,
            'min_amount'      => 1000,
            'is_active'       => true,
        ]);

        $client = Client::create([
            'client_number'         => 'CLT-003',
            'name'                  => 'Loan Client',
            'status'                => 'active',
            'membership_fee'        => 50000,
            'membership_fee_paid'   => 0,
            'membership_fee_status' => 'unpaid',
        ]);

        $loan = Loan::create([
            'loan_number'           => 'LN-001',
            'client_id'             => $client->id,
            'loan_product_id'       => $product->id,
            'principal'             => 120000,
            'interest_rate'         => 24,
            'interest_method'       => 'flat',
            'term_months'           => 12,
            'status'                => 'active',
            'disbursement_date'     => '2026-01-01',
            'maturity_date'         => '2027-01-01',
            'outstanding_principal' => 110000,  // after 1 repayment of 10,000 principal
            'outstanding_interest'  => 26400,   // after 2,400 interest paid
            'outstanding_penalty'   => 0,
        ]);

        // Schedule: installment 1 is paid, installment 2 is pending
        LoanSchedule::create([
            'loan_id'        => $loan->id,
            'installment_no' => 1,
            'due_date'       => '2026-02-01',
            'principal_due'  => 10000,
            'interest_due'   => 2400,
            'total_due'      => 12400,
            'balance_after'  => 110000,
            'principal_paid' => 10000,
            'interest_paid'  => 2400,
            'status'         => 'paid',
        ]);
        LoanSchedule::create([
            'loan_id'        => $loan->id,
            'installment_no' => 2,
            'due_date'       => '2026-03-01',
            'principal_due'  => 10000,
            'interest_due'   => 2400,
            'total_due'      => 12400,
            'balance_after'  => 100000,
            'principal_paid' => 0,
            'interest_paid'  => 0,
            'status'         => 'pending',
        ]);

        // Create GL transaction for the repayment
        $transaction = Transaction::create([
            'date'        => '2026-02-01',
            'reference'   => 'TXN-REP-001',
            'description' => 'Loan repayment — LN-001',
            'module'      => 'loan',
            'module_id'   => $loan->id,
        ]);
        TransactionLine::create([
            'transaction_id' => $transaction->id,
            'account_id'     => Account::where('account_code', '1001')->value('id'),
            'debit'          => 12400, 'credit' => 0, 'description' => '',
        ]);
        TransactionLine::create([
            'transaction_id' => $transaction->id,
            'account_id'     => Account::where('account_code', '1002')->value('id'),
            'debit'          => 0, 'credit' => 10000, 'description' => '',
        ]);
        TransactionLine::create([
            'transaction_id' => $transaction->id,
            'account_id'     => Account::where('account_code', '4001')->value('id'),
            'debit'          => 0, 'credit' => 2400, 'description' => '',
        ]);

        // Create LoanRepayment record
        $repayment = LoanRepayment::create([
            'loan_id'        => $loan->id,
            'payment_date'   => '2026-02-01',
            'amount'         => 12400,
            'principal_paid' => 10000,
            'interest_paid'  => 2400,
            'penalty_paid'   => 0,
            'payment_method' => 'cash',
            'transaction_id' => $transaction->id,
        ]);

        return [$loan, $transaction, $repayment];
    }

    public function test_deleting_loan_repayment_transaction_restores_outstanding_balances(): void
    {
        [$loan, $transaction] = $this->makeLoanWithRepayment();

        $this->actingAs($this->admin)->delete(route('transactions.destroy', $transaction));

        $loan->refresh();
        $this->assertEqualsWithDelta(120000, $loan->outstanding_principal, 0.01,
            'Outstanding principal must be restored');
        $this->assertEqualsWithDelta(28800, $loan->outstanding_interest, 0.01,
            'Outstanding interest must be restored');
    }

    public function test_deleting_loan_repayment_transaction_resets_schedule_status(): void
    {
        [$loan, $transaction] = $this->makeLoanWithRepayment();

        $this->actingAs($this->admin)->delete(route('transactions.destroy', $transaction));

        $schedule = $loan->schedules()->where('installment_no', 1)->first();
        $this->assertNotEquals('paid', $schedule->status,
            'Schedule must no longer be paid after repayment deletion');
        $this->assertEquals(0, $schedule->principal_paid);
        $this->assertEquals(0, $schedule->interest_paid);
    }

    public function test_deleting_loan_repayment_deletes_repayment_record(): void
    {
        [$loan, $transaction, $repayment] = $this->makeLoanWithRepayment();

        $this->actingAs($this->admin)->delete(route('transactions.destroy', $transaction));

        $this->assertDatabaseMissing('loan_repayments', ['id' => $repayment->id]);
    }

    public function test_deleting_closed_loan_repayment_reopens_loan(): void
    {
        [$loan, $transaction] = $this->makeLoanWithRepayment();

        // Simulate loan was closed by this repayment
        $loan->update(['status' => 'closed']);

        $this->actingAs($this->admin)->delete(route('transactions.destroy', $transaction));

        $loan->refresh();
        $this->assertEquals('active', $loan->status,
            'A closed loan should be re-opened when its repayment is deleted');
    }
}
