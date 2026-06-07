<?php
use App\Models\Account;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up() {
        $expenseParentId = Account::where('account_code', '5000')->value('id');
        Account::updateOrCreate(
            ['account_code' => '5001'],
            ['account_name' => 'Salary Expense', 'account_type' => 'expense', 'parent_id' => $expenseParentId, 'is_active' => true]
        );
    }
    public function down() { Account::where('account_code', '5001')->delete(); }
};
