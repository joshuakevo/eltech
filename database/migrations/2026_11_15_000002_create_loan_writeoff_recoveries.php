<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bad debts recovered: money collected on a written-off loan. DR cash / bank (or the member's savings liability, with a
 * statement line) / CR "Bad Debts Recovered" income (created on the first free code from 4116), module loan_writeoff_recovery.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_writeoff_recoveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_writeoff_id')->constrained('loan_writeoffs')->cascadeOnDelete();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->date('recovery_date');
            $table->decimal('amount', 18, 2);
            $table->string('source', 20);                               // cash | savings
            $table->unsignedBigInteger('payment_account_id')->nullable();
            $table->unsignedBigInteger('savings_account_id')->nullable();
            $table->unsignedBigInteger('savings_transaction_id')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->string('notes', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index('loan_id');
        });

        $code = DB::table('accounts')->where('account_name', 'Bad Debts Recovered')->value('account_code');
        if (!$code) {
            $code = '4116';
            while (DB::table('accounts')->where('account_code', $code)->exists()) {
                $code = (string) ((int) $code + 1);
            }
            DB::table('accounts')->insert([
                'account_code' => $code, 'account_name' => 'Bad Debts Recovered', 'account_type' => 'revenue',
                'parent_id' => DB::table('accounts')->where('account_code', '4104')->value('parent_id'), 'is_active' => 1,
                'description' => 'Money collected on loans that were written off.',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        if (!DB::table('system_settings')->where('key', 'bad_debts_recovered_account')->exists()) {
            DB::table('system_settings')->insert(['key' => 'bad_debts_recovered_account', 'value' => $code, 'group' => 'loans',
                'label' => 'Bad debts recovered (income)', 'created_at' => now(), 'updated_at' => now()]);
        }
        Cache::forget('setting_bad_debts_recovered_account');
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_writeoff_recoveries');
        DB::table('system_settings')->where('key', 'bad_debts_recovered_account')->delete();
    }
};
