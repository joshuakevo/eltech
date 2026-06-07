<?php

namespace App\Console\Commands;

use App\Models\SavingsAccount;
use App\Services\SavingsService;
use Illuminate\Console\Command;

class PostSavingsInterest extends Command
{
    protected $signature   = 'eltech:post-interest {--date= : Date to post interest (default: today)} {--account= : Only post for a specific account number}';
    protected $description = 'Post periodic interest to all eligible savings accounts';

    public function handle(SavingsService $savingsService): int
    {
        $date    = $this->option('date') ?: today()->toDateString();
        $acctNum = $this->option('account');

        $query = SavingsAccount::with('product')
            ->where('status', 'active')
            ->whereHas('product', fn($q) => $q->where('interest_rate', '>', 0));

        if ($acctNum) {
            $query->where('account_number', $acctNum);
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->warn('No eligible accounts found.');
            return self::SUCCESS;
        }

        $posted  = 0;
        $skipped = 0;
        $errors  = 0;

        foreach ($accounts as $account) {
            try {
                $txn = $savingsService->postInterest($account, $date);
                if ($txn) {
                    $this->line("  [POSTED]  {$account->account_number} — Interest: {$txn->amount}  Balance: {$txn->balance_after}");
                    $posted++;
                } else {
                    $skipped++;
                }
            } catch (\InvalidArgumentException $e) {
                $this->warn("  [SKIPPED] {$account->account_number} — {$e->getMessage()}");
                $skipped++;
            } catch (\Throwable $e) {
                $this->error("  [ERROR]   {$account->account_number} — {$e->getMessage()}");
                $errors++;
            }
        }

        $this->line('');
        $this->info("Done. Posted: {$posted}  |  Skipped: {$skipped}  |  Errors: {$errors}");

        return self::SUCCESS;
    }
}
