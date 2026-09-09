<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Client;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\SavingsTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CrmClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'view crm', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view clients', 'guard_name' => 'web']);
    }

    private function userWithCrmAccess(): User
    {
        $role = Role::firstOrCreate(['name' => 'crm_tester', 'guard_name' => 'web']);
        $role->givePermissionTo('view crm');
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    private function userWithoutCrmAccess(): User
    {
        $role = Role::firstOrCreate(['name' => 'no_crm', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    public function test_user_without_view_crm_permission_is_forbidden(): void
    {
        $user = $this->userWithoutCrmAccess();

        $this->actingAs($user)->get(route('crm.clients.index'))->assertForbidden();
    }

    public function test_user_with_view_crm_permission_can_see_clients_index(): void
    {
        $user = $this->userWithCrmAccess();
        Client::create([
            'client_number' => 'CLT-100', 'name' => 'Alice Example',
            'status' => 'active', 'membership_fee' => 0, 'membership_fee_paid' => 0,
            'membership_fee_status' => 'unpaid',
        ]);

        $response = $this->actingAs($user)->get(route('crm.clients.index'));

        $response->assertOk();
        $response->assertSee('Alice Example');
    }

    public function test_client_360_page_shows_financial_summary_and_product_row(): void
    {
        $user = $this->userWithCrmAccess();

        Account::firstOrCreate(['account_code' => '2001'], ['account_name' => 'Savings Liability', 'account_type' => 'liability', 'is_active' => true, 'balance' => 0]);
        $product = SavingsProduct::create([
            'name' => 'Regular Savings', 'code' => 'RS01', 'interest_rate' => 0,
            'interest_method' => 'flat', 'interest_frequency' => 'monthly',
            'minimum_balance' => 0, 'withdrawal_fee' => 0, 'is_active' => true,
            'savings_liability_account_id' => Account::where('account_code', '2001')->value('id'),
        ]);
        $client = Client::create([
            'client_number' => 'CLT-200', 'name' => 'Bob Example',
            'status' => 'active', 'membership_fee' => 0, 'membership_fee_paid' => 0,
            'membership_fee_status' => 'unpaid',
        ]);
        $account = SavingsAccount::create([
            'client_id' => $client->id, 'product_id' => $product->id,
            'account_number' => 'SAV-200', 'balance' => 250000, 'status' => 'active',
            'opened_date' => '2026-01-01',
        ]);
        SavingsTransaction::create([
            'savings_account_id' => $account->id, 'transaction_type' => 'deposit',
            'amount' => 250000, 'balance_before' => 0, 'balance_after' => 250000,
            'transaction_date' => '2026-01-01', 'description' => 'Opening deposit',
        ]);

        $response = $this->actingAs($user)->get(route('crm.clients.show', $client));

        $response->assertOk();
        $response->assertSee('Bob Example');
        $response->assertSee('Regular Savings');
        $response->assertSeeText('1 of 4');
    }

    public function test_product_filter_excludes_clients_without_matching_product(): void
    {
        $user = $this->userWithCrmAccess();

        Account::firstOrCreate(['account_code' => '2001'], ['account_name' => 'Savings Liability', 'account_type' => 'liability', 'is_active' => true, 'balance' => 0]);
        $product = SavingsProduct::create([
            'name' => 'Regular Savings', 'code' => 'RS01', 'interest_rate' => 0,
            'interest_method' => 'flat', 'interest_frequency' => 'monthly',
            'minimum_balance' => 0, 'withdrawal_fee' => 0, 'is_active' => true,
            'savings_liability_account_id' => Account::where('account_code', '2001')->value('id'),
        ]);

        $withSavings = Client::create([
            'client_number' => 'CLT-300', 'name' => 'Has Savings',
            'status' => 'active', 'membership_fee' => 0, 'membership_fee_paid' => 0,
            'membership_fee_status' => 'unpaid',
        ]);
        SavingsAccount::create([
            'client_id' => $withSavings->id, 'product_id' => $product->id,
            'account_number' => 'SAV-300', 'balance' => 1000, 'status' => 'active',
            'opened_date' => '2026-01-01',
        ]);

        Client::create([
            'client_number' => 'CLT-301', 'name' => 'Charlie Zeroholdings',
            'status' => 'active', 'membership_fee' => 0, 'membership_fee_paid' => 0,
            'membership_fee_status' => 'unpaid',
        ]);

        $response = $this->actingAs($user)->get(route('crm.clients.index', ['product' => 'savings']));

        $response->assertOk();
        $response->assertSee('Has Savings');
        $response->assertDontSee('Charlie Zeroholdings');
    }
}
