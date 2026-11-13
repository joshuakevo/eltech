<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Member account closure (MemberClosureService): the savings balances pay the account closure fee
 * (default 30,000, includes the withdrawal fee → 4104 Account Close Fee) and the rest is paid out; if they
 * don't cover the fee, everything goes to the fee. Overdrawn accounts are written off to 5122 Overdrawn
 * Savings Write-Off (expense).
 */
return new class extends Migration
{
    private const SETTINGS = ['account_closure_fee', 'account_closure_fee_account', 'overdrawn_writeoff_account'];

    public function up(): void
    {
        if (!Schema::hasTable('member_closures')) {
            Schema::create('member_closures', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
                $table->date('closure_date');
                $table->decimal('total_balance', 18, 2);                 // positive balances
                $table->decimal('closure_fee', 18, 2)->default(0);
                $table->decimal('payout', 18, 2)->default(0);
                $table->decimal('written_off', 18, 2)->default(0);       // overdrawn balances written off
                $table->unsignedBigInteger('payout_account_id')->nullable();
                $table->unsignedBigInteger('transaction_id')->nullable(); // journal (module member_closure)
                $table->json('snapshot');                                 // client status, account statuses, statement rows
                $table->string('reason', 500)->nullable();
                $table->string('status', 10)->default('applied');         // applied | reversed
                $table->timestamp('reversed_at')->nullable();
                $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
        if (!Schema::hasColumn('clients', 'closed_at')) {
            Schema::table('clients', fn (Blueprint $t) => $t->date('closed_at')->nullable()->after('status'));
        }

        $closeCode = DB::table('accounts')->where('account_code', '4104')->exists()
            ? '4104'
            : (DB::table('accounts')->where('account_name', 'like', 'Account Clos%')->value('account_code') ?? '4007');
        $writeOffCode = DB::table('accounts')->where('account_code', '5122')->exists()
            ? '5122'
            : (DB::table('accounts')->where('account_name', 'like', 'Overdrawn Savings Write%')->value('account_code') ?? '');

        foreach (['account_closure_fee' => '30000', 'account_closure_fee_account' => $closeCode, 'overdrawn_writeoff_account' => $writeOffCode] as $key => $value) {
            if (!DB::table('system_settings')->where('key', $key)->exists()) {
                DB::table('system_settings')->insert(['key' => $key, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]);
            }
            Cache::forget("setting_{$key}");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('member_closures');
        if (Schema::hasColumn('clients', 'closed_at')) {
            Schema::table('clients', fn (Blueprint $t) => $t->dropColumn('closed_at'));
        }
        DB::table('system_settings')->whereIn('key', self::SETTINGS)->delete();
        foreach (self::SETTINGS as $key) {
            Cache::forget("setting_{$key}");
        }
    }
};
