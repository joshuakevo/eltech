<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $parentId = DB::table('accounts')->where('account_code', '1000')->value('id');

        // Rename 1001 to "Cash At Hand" — keep as payment source
        DB::table('accounts')->where('account_code', '1001')->update([
            'account_name' => 'Cash At Hand',
        ]);

        // Remove is_payment_source from the old generic accounts
        DB::table('accounts')->whereIn('account_code', ['1002', '1003'])->update([
            'is_payment_source' => false,
        ]);

        // Add specific payment source accounts
        $accounts = [
            ['account_code' => '1004', 'account_name' => 'MTN Mobile Money',    'account_type' => 'asset', 'parent_id' => $parentId, 'is_active' => true, 'is_payment_source' => true],
            ['account_code' => '1005', 'account_name' => 'Airtel Money',         'account_type' => 'asset', 'parent_id' => $parentId, 'is_active' => true, 'is_payment_source' => true],
            ['account_code' => '1006', 'account_name' => 'Stanbic Bank',         'account_type' => 'asset', 'parent_id' => $parentId, 'is_active' => true, 'is_payment_source' => true],
            ['account_code' => '1007', 'account_name' => 'DFCU Bank',            'account_type' => 'asset', 'parent_id' => $parentId, 'is_active' => true, 'is_payment_source' => true],
            ['account_code' => '1008', 'account_name' => 'MTN Collection Lines', 'account_type' => 'asset', 'parent_id' => $parentId, 'is_active' => true, 'is_payment_source' => true],
        ];

        foreach ($accounts as $acc) {
            DB::table('accounts')->updateOrInsert(
                ['account_code' => $acc['account_code']],
                array_merge($acc, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    public function down(): void
    {
        // Restore original names and flags
        DB::table('accounts')->where('account_code', '1001')->update(['account_name' => 'Cash on Hand']);
        DB::table('accounts')->whereIn('account_code', ['1002', '1003'])->update(['is_payment_source' => true]);
        DB::table('accounts')->whereIn('account_code', ['1004', '1005', '1006', '1007', '1008'])->delete();
    }
};
