<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Overdraft charges (OverdraftService): when a withdrawal takes a savings account negative, a charge
 * (default 1% of the negative balance) is added to the overdraft — DR member savings / CR 2016 Accrued
 * Overdraft Fees. When the member pays money in, the accrued fees are recognised as income up to the
 * amount paid — DR 2016 / CR 4111 Overdraft Income.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('overdraft_charges')) {
            Schema::create('overdraft_charges', function (Blueprint $table) {
                $table->id();
                $table->foreignId('savings_account_id')->constrained('savings_accounts')->cascadeOnDelete();
                $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
                $table->date('charge_date');
                $table->decimal('overdrawn_balance', 18, 2);              // negative balance the charge was worked out on
                $table->decimal('rate', 8, 4)->nullable();               // % used for the suggested charge
                $table->decimal('amount', 18, 2);                        // charged (may differ from the suggestion)
                $table->decimal('recognised_amount', 18, 2)->default(0); // moved to income so far
                $table->unsignedBigInteger('withdrawal_savings_transaction_id')->nullable(); // the withdrawal that overdrew the account
                $table->unsignedBigInteger('savings_transaction_id')->nullable();            // the "Overdraft charge" statement line
                $table->unsignedBigInteger('transaction_id')->nullable();                    // journal (module overdraft_charge)
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['savings_account_id', 'charge_date']);
            });
        }
        if (!Schema::hasTable('overdraft_fee_recognitions')) {
            Schema::create('overdraft_fee_recognitions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('savings_account_id')->constrained('savings_accounts')->cascadeOnDelete();
                $table->unsignedBigInteger('savings_transaction_id')->nullable();   // the payment that triggered it
                $table->date('recognised_on');
                $table->decimal('amount', 18, 2);
                $table->json('allocations');                                        // [{charge_id, amount}] oldest charge first
                $table->unsignedBigInteger('transaction_id')->nullable();           // journal (module overdraft_fee)
                $table->timestamps();
                $table->index('savings_transaction_id');
            });
        }

        // Accrued Overdraft Fees (liability — fees charged but not yet earned), next to the other payables:
        // reuse an account with that name, otherwise create it on the first free code from 2016 up.
        $accruedCode = DB::table('accounts')->where('account_name', 'Accrued Overdraft Fees')->value('account_code');
        if (!$accruedCode) {
            $accruedCode = '2016';
            while (DB::table('accounts')->where('account_code', $accruedCode)->exists()) {
                $accruedCode = (string) ((int) $accruedCode + 1);
            }
            $parent = DB::table('accounts')->where('account_code', '2015')->value('parent_id')
                ?? DB::table('accounts')->where('account_code', '2000')->value('id');
            DB::table('accounts')->insert([
                'account_code' => $accruedCode, 'account_name' => 'Accrued Overdraft Fees', 'account_type' => 'liability',
                'parent_id' => $parent, 'is_active' => 1,
                'description' => 'Overdraft charges added to members\' negative savings balances, not yet paid. Moved to overdraft income when the member pays.',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        foreach (['overdraft_charge_rate' => '1', 'overdraft_charge_minimum' => '0',
                  'overdraft_accrued_account' => $accruedCode, 'overdraft_income_account' => '4111'] as $key => $value) {
            if (!DB::table('system_settings')->where('key', $key)->exists()) {
                DB::table('system_settings')->insert(['key' => $key, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]);
            }
            \Illuminate\Support\Facades\Cache::forget("setting_{$key}");   // SystemSetting::get caches values
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('overdraft_fee_recognitions');
        Schema::dropIfExists('overdraft_charges');
        DB::table('system_settings')->whereIn('key', ['overdraft_charge_rate', 'overdraft_charge_minimum', 'overdraft_accrued_account', 'overdraft_income_account'])->delete();
        foreach (['overdraft_charge_rate', 'overdraft_charge_minimum', 'overdraft_accrued_account', 'overdraft_income_account'] as $key) {
            \Illuminate\Support\Facades\Cache::forget("setting_{$key}");
        }
    }
};
