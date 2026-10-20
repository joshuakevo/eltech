<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\LoanPenaltyTier;
use App\Models\LoanSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Catch-up of penalties between the 31/07/2026 transfer and the go-live of charged penalties: every installment due
 * after the transfer and before today that was not fully paid by its due date (paid late, Run Loans short, or still
 * unpaid) is charged its flat tier penalty, dated the day after the due date, penalty_source = 'backfill'.
 *
 * The date each installment was completed is found by replaying the loan's repayments (principal + interest, by date)
 * over the installments oldest first — the order repayments are allocated in. Amounts sitting on installments without
 * a repayment (imports, corrections) count as paid from the start; interest paid beyond the installments (carried
 * interest from the transfer) is placed right after the first installment due after the transfer, where repayments
 * pay it. Installments already charged and Locked-Up loans are left alone. Undo removes unpaid back-filled charges.
 */
class LoanPenaltyBackfillService
{
    public function plan(?string $today = null): Collection
    {
        $today = $today ?? now()->toDateString();
        $transfer = LoanInterestService::TRANSFER_DATE;
        $tiers = LoanPenaltyTier::orderBy('min_installment')->get();
        $resolve = function (float $amount) use ($tiers) {
            foreach ($tiers as $t) {
                if ($amount >= $t->min_installment && ($t->max_installment === null || $amount <= $t->max_installment)) {
                    return (float) $t->penalty_amount;
                }
            }
            return 0.0;
        };

        $loans = Loan::with(['client', 'product', 'schedules' => fn ($q) => $q->reorder()->orderBy('installment_no'), 'repayments' => fn ($q) => $q->reorder()->orderBy('payment_date')->orderBy('id')])
            ->whereIn('status', ['active', 'defaulted', 'closed'])
            ->whereHas('schedules', fn ($q) => $q->where('due_date', '>', $transfer)->where('due_date', '<', $today)->where('penalty_charged', '<=', 0))
            ->get()
            ->reject(fn ($l) => $l->isLockedUp());

        $rows = collect();
        foreach ($loans as $loan) {
            $dayBased = $loan->isDayBasedInterest();
            $schedules = $loan->schedules;
            $onSchedules = $schedules->sum(fn ($s) => (float) $s->principal_paid + (float) $s->interest_paid);
            $viaRepayments = $loan->repayments->sum(fn ($r) => (float) $r->principal_paid + (float) $r->interest_paid);
            $opening = max(0, $onSchedules - $viaRepayments);    // paid on installments without a repayment record
            $carried = max(0, $viaRepayments - $onSchedules);    // paid beyond the installments (carried interest)

            // Targets in allocation order; carried interest right after the first installment due after the transfer
            $targets = [];
            $carriedPlaced = false;
            foreach ($schedules as $s) {
                $owed = (float) $s->principal_due + ((!$dayBased || $s->interest_charged) ? (float) $s->interest_due : (float) $s->interest_paid);
                $targets[] = ['schedule' => $s, 'amount' => $owed];
                if (!$carriedPlaced && $carried > 0.005 && $s->due_date->toDateString() > $transfer) {
                    $targets[] = ['schedule' => null, 'amount' => $carried];
                    $carriedPlaced = true;
                }
            }
            if (!$carriedPlaced && $carried > 0.005) {
                array_unshift($targets, ['schedule' => null, 'amount' => $carried]);
            }

            // Payments by date: opening amounts first, then each repayment
            $payments = [['date' => '0000-00-00', 'amount' => $opening]];
            foreach ($loan->repayments as $r) {
                $payments[] = ['date' => $r->payment_date->toDateString(), 'amount' => (float) $r->principal_paid + (float) $r->interest_paid];
            }

            // Walk: when is each target completed?
            $pi = 0;
            $left = $payments[0]['amount'];
            foreach ($targets as $t) {
                $need = $t['amount'];
                $completed = null;
                while ($need > 0.005) {
                    if ($left <= 0.005) {
                        $pi++;
                        if (!isset($payments[$pi])) {
                            break;
                        }
                        $left = $payments[$pi]['amount'];
                        continue;
                    }
                    $take = min($need, $left);
                    $need -= $take;
                    $left -= $take;
                    if ($need <= 0.005) {
                        $completed = $payments[$pi]['date'];
                    }
                }
                if ($t['amount'] <= 0.005) {
                    $completed = '0000-00-00';
                }
                $s = $t['schedule'];
                if (!$s) {
                    continue;
                }
                $due = $s->due_date->toDateString();
                if ($due <= $transfer || $due >= $today || (float) $s->penalty_charged > 0) {
                    continue;
                }
                if ($completed !== null && $completed <= $due) {
                    continue;   // fully paid by its due date
                }
                $penalty = $resolve((float) $s->total_due);
                if ($penalty <= 0) {
                    continue;
                }
                $rows->push((object) [
                    'loan' => $loan, 'schedule' => $s, 'due' => $due, 'completed' => $completed,
                    'reason' => $completed === null ? 'still unpaid' : 'paid ' . Carbon::parse($completed)->format('d M Y'),
                    'penalty' => $penalty,
                ]);
            }
        }

        return $rows->sortBy(fn ($r) => $r->loan->loan_number . '|' . str_pad($r->schedule->installment_no, 4, '0', STR_PAD_LEFT))->values();
    }

    public function apply(): array
    {
        $plan = $this->plan();
        DB::transaction(function () use ($plan) {
            foreach ($plan as $r) {
                LoanSchedule::whereKey($r->schedule->id)->where('penalty_charged', '<=', 0)->update([
                    'penalty_charged'    => $r->penalty,
                    'penalty_charged_on' => Carbon::parse($r->due)->addDay()->toDateString(),
                    'penalty_source'     => 'backfill',
                ]);
            }
            AuditLog::record('penalty_backfill', 'Charged ' . $plan->count() . ' penalties (' . number_format($plan->sum('penalty'), 2) . ') on installments paid late / short / unpaid between 31/07/2026 and ' . now()->toDateString() . ' across ' . $plan->pluck('loan.id')->unique()->count() . ' loans', 'loans');
        });
        return ['count' => $plan->count(), 'total' => $plan->sum('penalty'), 'loans' => $plan->pluck('loan.id')->unique()->count()];
    }

    /** Removes back-filled charges that haven't been paid (paid ones are kept). */
    public function undo(): int
    {
        $n = LoanSchedule::where('penalty_source', 'backfill')->where('penalty_paid', '<=', 0)
            ->update(['penalty_charged' => 0, 'penalty_charged_on' => null, 'penalty_source' => null]);
        AuditLog::record('penalty_backfill_undone', "Removed {$n} back-filled penalties (unpaid ones)", 'loans');
        return $n;
    }

    public function appliedCount(): array
    {
        $q = LoanSchedule::where('penalty_source', 'backfill');
        return ['count' => (clone $q)->count(), 'total' => (float) (clone $q)->sum('penalty_charged'), 'paid' => (float) (clone $q)->sum('penalty_paid')];
    }
}
