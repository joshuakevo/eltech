<?php

use App\Models\Loan;
use App\Models\LoanPenaltyTier;
use App\Models\LoanSchedule;
use App\Services\LoanService;
use Illuminate\Database\Migrations\Migration;

/**
 * Penalties are sized by the tier of the loan's installment at disbursement, not the installment's current (grown)
 * amount. Re-size the penalties already charged; never below what has already been paid on them.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tiers = LoanPenaltyTier::orderBy('min_installment')->get();
        $service = app(LoanService::class);
        $changed = 0;
        $before = 0.0;
        $after = 0.0;
        foreach (LoanSchedule::where('penalty_charged', '>', 0)->get()->groupBy('loan_id') as $loanId => $rows) {
            $loan = Loan::find($loanId);
            if (!$loan) {
                continue;
            }
            foreach ($rows as $s) {
                $new = max($service->penaltyFor($loan, $s, $tiers), (float) $s->penalty_paid);
                if (abs($new - (float) $s->penalty_charged) > 0.005) {
                    $before += (float) $s->penalty_charged;
                    $after += $new;
                    $s->update(['penalty_charged' => $new]);
                    $changed++;
                }
            }
        }
        if ($changed) {
            \App\Models\AuditLog::record('penalty_rebased', "Re-sized {$changed} charged penalties to the tier of the installment at disbursement: " . number_format($before, 2) . ' → ' . number_format($after, 2), 'loans');
        }
    }

    public function down(): void
    {
        // Not reversible (previous amounts not kept)
    }
};
