<?php

use App\Models\Loan;
use App\Services\LoanInterestService;
use Illuminate\Database\Migrations\Migration;

/**
 * Flat loans now charge the same flat interest every installment (LoanInterestService::periodInterest)
 * instead of interest by days. Re-project the installments not yet charged on running flat loans so
 * their schedules show equal installments now. Projections only: nothing owed, no balances, no journals.
 */
return new class extends Migration
{
    public function up(): void
    {
        $service = app(LoanInterestService::class);
        Loan::with('product')
            ->whereIn('status', ['active', 'defaulted'])
            ->where('interest_method', 'flat')
            ->whereNotNull('interest_accrued_to')
            ->get()
            ->filter(fn (Loan $loan) => $loan->isFlatInterest() && $loan->schedules()->where('interest_charged', false)->exists())
            ->each(fn (Loan $loan) => $service->rebalance($loan));
    }

    public function down(): void
    {
        // Projections are recalculated on every charge and repayment; nothing to restore.
    }
};
