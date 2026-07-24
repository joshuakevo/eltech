<?php

namespace App\Console\Commands;

use App\Models\FixedDeposit;
use App\Services\FixedDepositService;
use Illuminate\Console\Command;

class AccrueFixedDepositInterest extends Command
{
    protected $signature   = 'eltech:accrue-fd-interest {--date= : Date to accrue interest to (default: today)} {--deposit= : Only accrue for a specific deposit number}';
    protected $description = 'Accrue periodic interest expense on all active fixed deposits';

    public function handle(FixedDepositService $fdService): int
    {
        $date       = $this->option('date') ?: today()->toDateString();
        $depositNum = $this->option('deposit');

        $query = FixedDeposit::with('product')->where('status', 'active');

        if ($depositNum) {
            $query->where('deposit_number', $depositNum);
        }

        $deposits = $query->get();

        if ($deposits->isEmpty()) {
            $this->warn('No active fixed deposits found.');
            return self::SUCCESS;
        }

        $posted  = 0;
        $skipped = 0;
        $errors  = 0;

        foreach ($deposits as $deposit) {
            try {
                $before = $deposit->accrued_interest;
                $fdService->accrueInterest($deposit, $date);
                $deposit->refresh();
                $newAccrual = $deposit->accrued_interest - $before;

                if ($newAccrual > 0) {
                    $this->line("  [POSTED]  {$deposit->deposit_number} — Accrued: {$newAccrual}  Total accrued: {$deposit->accrued_interest}");
                    $posted++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $this->error("  [ERROR]   {$deposit->deposit_number} — {$e->getMessage()}");
                $errors++;
            }
        }

        $this->line('');
        $this->info("Done. Posted: {$posted}  |  Skipped: {$skipped}  |  Errors: {$errors}");

        return self::SUCCESS;
    }
}
