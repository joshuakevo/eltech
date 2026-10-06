<?php

namespace App\Services;

use App\Models\FinancialPeriod;
use App\Models\Loan;
use App\Models\LoanCorrection;
use App\Models\LoanInterestCharge;
use App\Models\LoanSchedule;
use App\Models\SavingsAccount;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Day-based loan interest, charged on each due date by Run Loans:
 *
 *   interest = outstanding principal × annual rate × days since last charge ÷ 365
 *
 * The charge is added to outstanding interest (no journal — interest is booked when
 * recovered). The installment is then recovered from the member's savings: the loan's
 * fixed installment amount, interest first and the rest principal; interest above the
 * installment is carried forward to the next due date.
 *
 * Loan state:
 *   interest_accrued_to   date interest is charged up to (NULL = not yet converted)
 *   interest_carried      charged interest not yet on an installment
 *   installment_amount    fixed installment
 *   schedule.interest_charged   installment's interest is real (true) or a projection (false);
 *                         projections are not owed and not part of outstanding interest.
 * outstanding_interest = unpaid interest on charged installments + interest_carried.
 */
class LoanInterestService
{
    /** Loans brought over from the old system start day-based interest from this date. */
    public const TRANSFER_DATE = '2026-07-31';

    public function __construct(
        protected LoanService $loans,
        protected SavingsService $savings,
    ) {}

    // ── Conversion ──────────────────────────────────────────────────────────

    /**
     * Moves a loan onto day-based interest. Installments due before $before keep their
     * scheduled interest as already charged; later ones become projections.
     * A balance correction dated after that point sets the baseline (its interest at the
     * as-at date becomes carried interest).
     */
    public function convert(Loan $loan, Carbon $before): array
    {
        $rows = $loan->schedules()->orderBy('installment_no')->get();
        $oldInterest = (float) $loan->outstanding_interest;

        $baseline = $loan->disbursement_date->copy()->startOfDay();
        $transfer = Carbon::parse(self::TRANSFER_DATE);
        if ($baseline->lt($transfer)) {
            $baseline = $transfer->copy();
        }
        $lastPastDue = $rows->filter(fn ($r) => $r->due_date->lt($before))->max('due_date');
        if ($lastPastDue && $lastPastDue->gt($baseline)) {
            $baseline = $lastPastDue->copy()->startOfDay();
        }

        $correction = LoanCorrection::where('loan_id', $loan->id)->where('status', 'applied')->latest('id')->first();
        $fromCorrection = $correction && $correction->as_at_date->gte($baseline);
        if ($fromCorrection) {
            $baseline = $correction->as_at_date->copy()->startOfDay();
        }

        $chargedUnpaid = 0;
        $prepaidInterest = 0;
        foreach ($rows as $row) {
            $charged = $row->due_date->lte($baseline);
            if ($charged) {
                $chargedUnpaid += max(0, $row->interest_due - $row->interest_paid);
            } elseif ($row->interest_paid > 0) {
                // Interest paid ahead on an installment not yet charged: becomes a credit
                // against the next charges.
                $prepaidInterest += $row->interest_paid;
                $row->interest_paid = 0;
            }
            $row->interest_charged = $charged;
            $row->save();
        }

        // After a correction, outstanding interest is already net of interest paid since the
        // as-at date. Otherwise interest paid ahead on uncharged installments is a credit.
        $carried = $fromCorrection
            ? round(max(0, $oldInterest - $chargedUnpaid), 2)
            : round(-$prepaidInterest, 2);

        $uncharged = $rows->filter(fn ($r) => !$r->interest_charged)->values();
        $unattached = $this->unattachedPrincipal($loan, $rows);
        if ($uncharged->isEmpty()) {
            $installment = 0;
        } elseif (!$fromCorrection && $uncharged->count() > 1) {
            $installment = (float) $uncharged->first()->total_due;   // keep the installment members know
        } else {
            $installment = $this->annuity($unattached, (float) $loan->interest_rate, $uncharged->count(), $this->stepMonths($loan));
        }

        $loan->forceFill([
            'interest_accrued_to' => $baseline->toDateString(),
            'interest_carried'    => $carried,
            'installment_amount'  => round($installment, 2),
            'outstanding_interest'=> round(max(0, $chargedUnpaid + $carried), 2),
        ])->save();

        $this->rebalance($loan);

        return [
            'baseline'     => $baseline->toDateString(),
            'old_interest' => round($oldInterest, 2),
            'new_interest' => round((float) $loan->outstanding_interest, 2),
            'carried'      => $carried,
            'installment'  => round($installment, 2),
            'from_correction' => $fromCorrection,
        ];
    }

    /** Sets up a newly disbursed loan: nothing charged yet, installments are projections. */
    public function initialiseNewLoan(Loan $loan): void
    {
        $first = $loan->schedules()->orderBy('installment_no')->first();
        $loan->forceFill([
            'interest_accrued_to'  => $loan->disbursement_date->toDateString(),
            'interest_carried'     => 0,
            'installment_amount'   => $first ? round((float) $first->total_due, 2) : 0,
            'outstanding_interest' => 0,
        ])->save();
        $loan->schedules()->update(['interest_charged' => false]);
        $this->rebalance($loan);
    }

    // ── Charging ────────────────────────────────────────────────────────────

    /** Charges interest for the period ending on this installment's due date. */
    public function charge(Loan $loan, LoanSchedule $row): array
    {
        $from    = $loan->interest_accrued_to->copy()->startOfDay();
        $to      = $row->due_date->copy()->startOfDay();
        $days    = $to->gt($from) ? $from->diffInDays($to) : 0;
        $rate    = (float) $loan->interest_rate;
        $prin    = round((float) $loan->outstanding_principal, 2);
        $accrued = round($prin * $rate / 100 * $days / 365, 2);

        $rows   = $loan->schedules()->orderBy('installment_no')->get();
        $isLast = $rows->filter(fn ($r) => !$r->interest_charged && $r->id !== $row->id)->isEmpty();
        $unattached = $this->unattachedPrincipal($loan, $rows, $row->id);

        $carriedBefore = round((float) $loan->interest_carried, 2);
        $pool = $carriedBefore + $accrued;
        $emi  = (float) $loan->installment_amount;

        $interestDue  = $isLast ? max(0, $pool) : max(0, min($pool, $emi));
        $carriedAfter = round($pool - $interestDue, 2);
        $principal    = $isLast ? $unattached : min($unattached, max(0, $emi - $interestDue));

        $row->forceFill([
            'interest_due'     => round($interestDue + $row->interest_paid, 2),
            'principal_due'    => round($principal + $row->principal_paid, 2),
            'interest_charged' => true,
        ]);
        $row->total_due = round($row->principal_due + $row->interest_due, 2);
        $row->status    = $this->status($row);
        $row->save();

        $loan->forceFill([
            'outstanding_interest' => round((float) $loan->outstanding_interest + $accrued, 2),
            'interest_carried'     => $carriedAfter,
            'interest_accrued_to'  => $to->toDateString(),
        ])->save();

        LoanInterestCharge::create([
            'loan_id' => $loan->id, 'loan_schedule_id' => $row->id,
            'from_date' => $from->toDateString(), 'to_date' => $to->toDateString(), 'days' => $days,
            'principal' => $prin, 'rate' => $rate, 'amount' => $accrued,
            'carried_before' => $carriedBefore, 'carried_after' => $carriedAfter,
            'created_by' => auth()->id(),
        ]);

        $this->rebalance($loan);

        return [
            'installment_no' => $row->installment_no, 'due_date' => $to->toDateString(),
            'from' => $from->toDateString(), 'days' => $days, 'principal' => $prin, 'accrued' => $accrued,
            'carried_before' => $carriedBefore, 'interest_due' => round($interestDue, 2),
            'principal_due' => round($principal, 2), 'carried_after' => $carriedAfter,
        ];
    }

    /**
     * Re-projects installments not yet charged so their principal sums to the loan's
     * remaining principal (fixed installment, interest projected by days; last takes the rest).
     */
    public function rebalance(Loan $loan): void
    {
        $rows = $loan->schedules()->orderBy('installment_no')->get();
        $uncharged = $rows->filter(fn ($r) => !$r->interest_charged)->values();
        if ($uncharged->isEmpty()) {
            return;
        }

        $balance = $this->unattachedPrincipal($loan, $rows);
        $prev    = $loan->interest_accrued_to->copy()->startOfDay();
        $rate    = (float) $loan->interest_rate;
        $emi     = (float) $loan->installment_amount;
        $n       = $uncharged->count();

        foreach ($uncharged as $i => $row) {
            $days = $row->due_date->gt($prev) ? $prev->diffInDays($row->due_date) : 0;
            $proj = round(max(0, $balance) * $rate / 100 * $days / 365, 2);
            $p    = $i === $n - 1 ? $balance : min($balance, max(0, round($emi - $proj, 2)));
            $p    = round(max(0, $p), 2);
            $balance = round($balance - $p, 2);

            $row->principal_due = round($p + $row->principal_paid, 2);
            $row->interest_due  = round($proj + $row->interest_paid, 2);
            $row->total_due     = round($row->principal_due + $row->interest_due, 2);
            $row->balance_after = max(0, $balance);
            $row->status        = $this->status($row);
            $row->save();
            $prev = $row->due_date->copy()->startOfDay();
        }
    }

    // ── Run Loans ───────────────────────────────────────────────────────────

    /**
     * Runs a loan for a date: converts it if needed, then for every installment due on or
     * before the date that hasn't been charged — charge interest, then recover what is due
     * from savings (dated the installment's due date).
     */
    public function run(Loan $loan, Carbon $date): array
    {
        return DB::transaction(function () use ($loan, $date) {
            $loan = Loan::with('client', 'product')->whereKey($loan->id)->lockForUpdate()->firstOrFail();
            $result = [
                'loan_id' => $loan->id, 'conversion' => null, 'steps' => [], 'errors' => [],
                'before'  => ['principal' => round($loan->outstanding_principal, 2), 'interest' => round($loan->outstanding_interest, 2)],
            ];

            if ($loan->isLockedUp() || $loan->status !== 'active') {
                $result['errors'][] = 'Only active loans (not Locked-Up) are run.';
                return $result;
            }

            if (!$loan->isDayBasedInterest()) {
                $result['conversion'] = $this->convert($loan, $date);
                $loan->refresh();
            }

            $due = $loan->schedules()->where('interest_charged', false)
                ->where('due_date', '<=', $date->toDateString())->orderBy('installment_no')->get();

            foreach ($due as $row) {
                $day = $row->due_date->toDateString();
                if (!FinancialPeriod::isOpen($day)) {
                    $result['errors'][] = 'The financial period for ' . $row->due_date->format('M Y') . ' is closed — reopen it to run this loan.';
                    break;
                }
                $step = $this->charge($loan, $row->fresh());
                $loan->refresh();
                $step['recovery'] = $this->recover($loan, $row->due_date->copy());
                $loan->refresh();
                $result['steps'][] = $step;
            }

            // Already-charged installments still unpaid (e.g. savings were short last time).
            if (!$due->count() && !$result['errors']) {
                $expected = $this->expectedDue($loan, $date);
                if ($expected > 0.01) {
                    $result['steps'][] = ['installment_no' => null, 'due_date' => $date->toDateString(), 'accrued' => 0, 'days' => 0,
                        'recovery' => $this->recover($loan, $date->copy())];
                    $loan->refresh();
                }
            }

            $result['after'] = ['principal' => round($loan->outstanding_principal, 2), 'interest' => round($loan->outstanding_interest, 2), 'status' => $loan->status];
            return $result;
        });
    }

    /** What run() would do, without saving anything (runs it and rolls back). */
    public function preview(Loan $loan, Carbon $date): array
    {
        DB::beginTransaction();
        try {
            return $this->run($loan, $date);
        } catch (\Throwable $e) {
            return ['loan_id' => $loan->id, 'steps' => [], 'conversion' => null, 'errors' => [$e->getMessage()],
                'before' => ['principal' => round($loan->outstanding_principal, 2), 'interest' => round($loan->outstanding_interest, 2)]];
        } finally {
            DB::rollBack();
        }
    }

    /** Unpaid principal + interest on charged installments due on or before the date. */
    public function expectedDue(Loan $loan, Carbon $asOf): float
    {
        return round($loan->schedules()->where('interest_charged', true)
            ->where('due_date', '<=', $asOf->toDateString())->get()
            ->sum(fn ($r) => max(0, $r->principal_due - $r->principal_paid) + max(0, $r->interest_due - $r->interest_paid)), 2);
    }

    /** Recovers what is due from the member's savings (as much as the balance on that date allows). */
    private function recover(Loan $loan, Carbon $date): array
    {
        $expected = $this->expectedDue($loan, $date);
        $out = ['expected' => $expected, 'available' => 0, 'recovered' => 0, 'account' => null, 'note' => null];
        if ($expected < 0.01) {
            $out['note'] = 'Nothing due';
            return $out;
        }

        $account = $this->savingsAccountFor($loan, $date);
        if (!$account) {
            $out['note'] = 'No active savings account';
            return $out;
        }
        $available = max(0, round($this->savings->balanceAsOf($account, $date->toDateString()) - (float) ($account->product->minimum_balance ?? 0), 2));
        $out['available'] = $available;
        $out['account']   = $account->account_number;

        $total  = (float) $loan->outstanding_principal + (float) $loan->outstanding_interest + (float) $loan->outstanding_penalty;
        $amount = round(min($expected, $available, $total), 2);
        if ($amount < 1) {
            $out['note'] = 'Insufficient savings';
            return $out;
        }

        $this->savings->withdraw($account, $amount, $date->toDateString(), "Loan repayment - {$loan->loan_number}", null, 0.0);
        $this->loans->processRepayment($loan->fresh(), [
            'amount'         => $amount,
            'payment_date'   => $date->toDateString(),
            'payment_method' => 'savings',
            'reference'      => 'LR-' . $loan->loan_number . '-' . $date->format('Ymd') . '-' . now()->format('His') . substr((string) microtime(true), -3),
            'notes'          => 'Run Loans recovery',
        ]);
        $this->savings->recalculateLedger($account->fresh());

        $out['recovered'] = $amount;
        $out['note'] = $amount + 0.01 < $expected ? 'Partly recovered' : 'Recovered';
        return $out;
    }

    /** The member's active savings account with the most available on the date. */
    public function savingsAccountFor(Loan $loan, Carbon $date): ?SavingsAccount
    {
        $accounts = SavingsAccount::with('product')->where('client_id', $loan->client_id)->where('status', 'active')->get();
        return $accounts->sortByDesc(fn ($a) => $this->savings->balanceAsOf($a, $date->toDateString()) - (float) ($a->product->minimum_balance ?? 0))->first();
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** Principal not yet assigned to a charged installment. */
    private function unattachedPrincipal(Loan $loan, Collection $rows, ?int $excludeId = null): float
    {
        $onCharged = $rows->filter(fn ($r) => $r->interest_charged && $r->id !== $excludeId)
            ->sum(fn ($r) => max(0, $r->principal_due - $r->principal_paid));
        return round(max(0, (float) $loan->outstanding_principal - $onCharged), 2);
    }

    private function status(LoanSchedule $row): string
    {
        $paid = $row->principal_paid >= $row->principal_due - 0.01 && $row->interest_paid >= $row->interest_due - 0.01;
        return match (true) {
            $paid                                               => 'paid',
            $row->due_date->lt(now()->startOfDay())             => 'overdue',
            $row->principal_paid > 0 || $row->interest_paid > 0 => 'partial',
            default                                             => 'pending',
        };
    }

    private function stepMonths(Loan $loan): int
    {
        return ($loan->repayment_frequency ?? 'monthly') === 'quarterly' ? 3 : 1;
    }

    private function annuity(float $principal, float $annualRate, int $n, int $step): float
    {
        if ($n <= 0) return $principal;
        $r = $annualRate / 100 * $step / 12;
        return $r == 0 ? $principal / $n : $principal * ($r * pow(1 + $r, $n)) / (pow(1 + $r, $n) - 1);
    }
}
