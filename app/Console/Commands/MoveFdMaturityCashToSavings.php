<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\FixedDeposit;
use App\Models\SavingsTransaction;
use App\Models\Transaction;
use App\Services\AccountingService;
use App\Services\FixedDepositService;
use App\Services\SavingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off correction for fixed deposits that were matured before maturity payouts
 * were routed to savings: their payout journal credited Cash (1001). For each
 * deposit this posts DR Cash / CR the client's savings liability (module `savings`,
 * dated on the original payout) and adds the matching savings statement line linked
 * via transaction_id, so reversing the correction unwinds the statement too.
 *
 * Dry run unless --confirm is passed.
 */
class MoveFdMaturityCashToSavings extends Command
{
    protected $signature = 'eltech:fd-maturity-cash-to-savings
        {deposits* : Deposit numbers, e.g. FD-MK00085 FD-BK00028-1}
        {--confirm : Post the corrections (otherwise a dry run)}';

    protected $description = 'Move closed FD maturity payouts that went to Cash into the client\'s savings account';

    public function handle(AccountingService $accounting, FixedDepositService $fdService, SavingsService $savingsService): int
    {
        $cashId  = Account::where('account_code', '1001')->value('id');
        $confirm = (bool) $this->option('confirm');
        $failed  = false;

        foreach ($this->argument('deposits') as $number) {
            $this->line('');
            $this->info("== {$number}");

            $deposit = FixedDeposit::where('deposit_number', $number)->first();
            if (!$deposit) {
                $this->error('  Deposit not found - skipped.');
                $failed = true;
                continue;
            }
            $deposit->load('client', 'savingsAccount');
            $this->line("  Client: {$deposit->client?->name} | status: {$deposit->status} | maturity amount: " . number_format($deposit->maturity_amount, 2));

            $payout = Transaction::where('module', 'fixed_deposit')
                ->where('module_id', $deposit->id)
                ->where('description', 'like', 'Fixed deposit maturity payout%')
                ->whereNull('reversed_by')
                ->with('lines')
                ->latest('id')
                ->first();
            if (!$payout) {
                $this->warn('  No un-reversed cash maturity journal found (it may have gone to savings already, or been closed another way) - skipped.');
                continue;
            }

            $amount = (float) $payout->lines->where('account_id', $cashId)->sum('credit');
            $this->line("  Cash payout journal: {$payout->reference} on {$payout->date->format('d M Y')} - Cash credited " . number_format($amount, 2));
            if ($amount <= 0) {
                $this->warn('  Journal did not credit Cash 1001 - skipped.');
                continue;
            }

            $tag = "Fixed deposit maturity moved from cash to savings - {$deposit->deposit_number}";
            if (SavingsTransaction::where('description', $tag)->exists()) {
                $this->warn('  Already corrected - skipped.');
                continue;
            }

            $savings = $fdService->payoutSavingsAccount($deposit);
            if (!$savings) {
                $this->error('  Client has no active savings account - open one, then re-run.');
                $failed = true;
                continue;
            }
            $savings->load('product');
            $this->line("  Credit to savings: {$savings->account_number} (balance now " . number_format($savings->balance, 2) . ')');
            $this->line('  Journal: DR Cash 1001 / CR savings liability ' . number_format($amount, 2) . ", dated {$payout->date->format('d M Y')}");

            if (!$confirm) {
                continue;
            }

            DB::transaction(function () use ($accounting, $savingsService, $deposit, $payout, $savings, $amount, $cashId, $tag) {
                $date = $payout->date->toDateString();

                $journal = $accounting->post(
                    $date,
                    $tag,
                    [
                        [
                            'account_id'  => $cashId,
                            'debit'       => $amount,
                            'credit'      => 0,
                            'description' => "Reverse FD cash payout {$payout->reference} - {$deposit->deposit_number}",
                        ],
                        [
                            'account_id'  => $savings->product->savings_liability_account_id,
                            'debit'       => 0,
                            'credit'      => $amount,
                            'description' => "FD maturity credited to {$savings->account_number}",
                            'client_id'   => $deposit->client_id,
                        ],
                    ],
                    'savings',
                    $savings->id,
                    'FDFIX-' . $deposit->deposit_number
                );

                SavingsTransaction::create([
                    'savings_account_id' => $savings->id,
                    'transaction_type'   => 'deposit',
                    'amount'             => $amount,
                    'balance_before'     => 0,
                    'balance_after'      => 0,
                    'transaction_date'   => $date,
                    'reference'          => $journal->reference,
                    'description'        => $tag,
                    'transaction_id'     => $journal->id,
                    'created_by'         => auth()->id(),
                ]);

                // Back-dated row: rebuild running balances and the account balance.
                $savingsService->recalculateLedger($savings);

                $this->info("  Posted {$journal->reference}; savings balance now " . number_format($savings->fresh()->balance, 2));
            });
        }

        if (!$confirm) {
            $this->line('');
            $this->comment('Dry run - nothing posted. Re-run with --confirm to post.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
