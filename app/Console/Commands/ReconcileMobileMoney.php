<?php

namespace App\Console\Commands;

use App\Models\MobileMoneyTransaction;
use App\Services\MobileMoneyService;
use Illuminate\Console\Command;

class ReconcileMobileMoney extends Command
{
    protected $signature   = 'eltech:reconcile-mobile-money';
    protected $description = 'Re-check pending/processing mobile money transactions against MarzPay (backstop for missed webhooks)';

    public function handle(MobileMoneyService $mobileMoneyService): int
    {
        $transactions = MobileMoneyTransaction::whereIn('status', ['pending', 'processing'])
            ->where('created_at', '>=', now()->subDay())
            ->get();

        foreach ($transactions as $mm) {
            $mobileMoneyService->reconcile($mm);
        }

        $this->info("Reconciled {$transactions->count()} pending mobile money transaction(s).");

        return self::SUCCESS;
    }
}
