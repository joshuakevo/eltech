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
                $penalty = app(LoanService::class)->penaltyFor($loan, $s, $tiers);   // tier of the installment at disbursement
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

    /**
     * Overdue periods since the transfer on loans past maturity and still owing: each due day after maturity
     * (maturity + n × the repayment step) up to yesterday, where less than one installment was paid in the period
     * and no overdue penalty is recorded yet. Today's period is left to Run Loans.
     */
    public function planOverdue(?string $today = null): Collection
    {
        $today = $today ?? now()->toDateString();
        $transfer = LoanInterestService::TRANSFER_DATE;
        $svc = app(LoanService::class);
        $terms = app(LoanCorrectionService::class);
        $rows = collect();
        $loans = Loan::with(['client', 'product', 'schedules' => fn ($q) => $q->reorder()->orderByDesc('installment_no')])
            ->whereIn('status', ['active', 'defaulted'])->where('outstanding_principal', '>', 0.01)->get()
            ->reject(fn ($l) => $l->isLockedUp());
        foreach ($loans as $loan) {
            $last = $loan->schedules->first();
            if (!$last || $last->due_date->toDateString() >= $today) {
                continue;
            }
            $step = Loan::stepMonthsFor($loan->repayment_frequency ?? 'monthly');
            $installment = $terms->termsInstallment($loan) ?? (float) $last->total_due;
            $done = \App\Models\LoanOverduePenalty::where('loan_id', $loan->id)->pluck('period_date')->map(fn ($d) => Carbon::parse($d)->toDateString())->all();
            $prev = $last->due_date->copy();
            for ($k = 1; $k <= 600; $k++) {
                $period = $last->due_date->copy()->addMonthsNoOverflow($k * $step);
                if ($period->toDateString() >= $today) {
                    break;
                }
                if ($period->toDateString() > $transfer && !in_array($period->toDateString(), $done, true)) {
                    $paid = (float) $loan->repayments()->reorder()->where('payment_date', '>', $prev->toDateString())->where('payment_date', '<=', $period->toDateString())
                        ->sum(DB::raw('principal_paid + interest_paid'));
                    if ($paid + 0.01 < $installment) {
                        $rows->push((object) [
                            'loan' => $loan, 'period' => $period->toDateString(), 'previous' => $prev->toDateString(),
                            'paid' => round($paid, 2), 'installment' => round($installment, 2), 'penalty' => $svc->penaltyFor($loan, $last),
                        ]);
                    }
                }
                $prev = $period;
            }
        }
        return $rows->sortBy(fn ($r) => $r->loan->loan_number . '|' . $r->period)->values();
    }

    public function apply(): array
    {
        $plan = $this->plan();
        $overdue = $this->planOverdue();
        $svc = app(LoanService::class);
        $overdueCharged = 0.0;
        DB::transaction(function () use ($overdue, $svc, &$overdueCharged) {
            foreach ($overdue as $r) {
                $row = $svc->chargeOverduePeriodPenalty($r->loan->fresh(), $r->period, $r->previous, 'backfill');
                $overdueCharged += $row->amount ?? 0;
            }
            if ($overdue->isNotEmpty()) {
                AuditLog::record('penalty_backfill_overdue', 'Charged ' . $overdue->count() . ' overdue-period penalties (' . number_format($overdueCharged, 2) . ') on loans past maturity, 31/07/2026 → ' . now()->toDateString(), 'loans');
            }
        });
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
        return ['count' => $plan->count() + $overdue->count(), 'total' => $plan->sum('penalty') + $overdueCharged,
            'loans' => $plan->pluck('loan.id')->merge($overdue->pluck('loan.id'))->unique()->count()];
    }

    /** Removes back-filled charges that haven't been paid (paid ones are kept). */
    public function undo(): int
    {
        return DB::transaction(function () {
            $n = 0;
            // Overdue-period catch-up: take each amount back off the last installment (never below what was paid)
            foreach (\App\Models\LoanOverduePenalty::where('source', 'backfill')->get() as $op) {
                // always on the loan's last installment (schedule ids change when Run Loans undo restores a snapshot)
                $s = LoanSchedule::where('loan_id', $op->loan_id)->orderByDesc('installment_no')->first();
                if ($s && (float) $s->penalty_charged - $op->amount + 0.005 < (float) $s->penalty_paid) {
                    continue;   // already paid — keep it
                }
                if ($s) {
                    $s->update(['penalty_charged' => round(max(0, (float) $s->penalty_charged - $op->amount), 2)]);
                }
                $op->delete();
                $n++;
            }
            // Installment catch-up (unpaid ones); an overdue-period amount still on the same installment stays
            foreach (LoanSchedule::where('penalty_source', 'backfill')->where('penalty_paid', '<=', 0)->get() as $s) {
                $isLast = (int) LoanSchedule::where('loan_id', $s->loan_id)->max('installment_no') === (int) $s->installment_no;
                $keep = $isLast ? (float) \App\Models\LoanOverduePenalty::where('loan_id', $s->loan_id)->sum('amount') : 0.0;
                $s->update(['penalty_charged' => $keep, 'penalty_charged_on' => $keep > 0 ? $s->penalty_charged_on : null, 'penalty_source' => $keep > 0 ? 'overdue' : null]);
                $n++;
            }
            AuditLog::record('penalty_backfill_undone', "Removed {$n} back-filled penalties (unpaid ones)", 'loans');
            return $n;
        });
    }

    public function appliedCount(): array
    {
        $q = LoanSchedule::where('penalty_source', 'backfill');
        $o = \App\Models\LoanOverduePenalty::where('source', 'backfill');
        return ['count' => (clone $q)->count() + (clone $o)->count(), 'total' => (float) (clone $q)->sum('penalty_charged') + (float) (clone $o)->sum('amount'),
            'paid' => (float) (clone $q)->sum('penalty_paid')];
    }
}
