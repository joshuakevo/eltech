<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\LoanCorrection;
use App\Models\LoanInterestCharge;
use App\Models\LoanRepayment;
use App\Models\LoanRun;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Wipes loan repayments from a date so they can be re-run afresh, leaving nothing behind:
 *
 *  - every repayment dated on/after the date, its journal (and any reversal of it)
 *  - the savings withdrawals that funded them ("Loan repayment - LN-…"), their journals,
 *    with savings balances/statements rebuilt
 *  - reversed or orphaned repayment / withdrawal journals for the loan from the date
 *  - all interest charges and Run Loans records
 *
 * then puts the loan back to its starting figures: schedule unpaid, outstanding principal =
 * the schedule's principal, and day-based interest restarting from the transfer date /
 * balance correction / disbursement (correction interest carried, otherwise nothing owing).
 *
 * Loans that still have repayments before the date are skipped (their schedule would need
 * unwinding rather than resetting) and reported.
 */
class LoanResetService
{
    public function __construct(
        protected LoanInterestService $interest,
        protected SavingsService $savings,
    ) {}

    /** Read-only: what a reset from $from would do, per loan. */
    public function plan(Carbon $from, ?string $search = null): Collection
    {
        return $this->candidates($from, $search)->map(fn (Loan $loan) => $this->planLoan($loan, $from))
            ->filter(fn ($p) => $p['has_work'])->values();
    }

    public function execute(Carbon $from, array $loanIds): array
    {
        $done = []; $skipped = [];
        foreach (Loan::with('product', 'client')->whereIn('id', $loanIds)->get() as $loan) {
            try {
                $r = DB::transaction(fn () => $this->resetLoan(Loan::whereKey($loan->id)->lockForUpdate()->with('product', 'client')->first(), $from));
                $r['skipped'] ? $skipped[] = "{$loan->loan_number}: {$r['skipped']}" : $done[] = $r;
            } catch (\Throwable $e) {
                $skipped[] = "{$loan->loan_number}: " . $e->getMessage();
            }
        }
        return [$done, $skipped];
    }

    // ── internals ───────────────────────────────────────────────────────────

    private function candidates(Carbon $from, ?string $search): Collection
    {
        $f = $from->toDateString();
        $ids = collect()
            ->merge(LoanRepayment::where('payment_date', '>=', $f)->pluck('loan_id'))
            ->merge(LoanInterestCharge::pluck('loan_id'))
            ->merge(LoanRun::pluck('loan_id'))
            ->merge(Loan::whereNotNull('interest_accrued_to')->pluck('id'))
            ->merge(DB::table('loan_schedules')->where(fn ($q) => $q->where('principal_paid', '>', 0)->orWhere('interest_paid', '>', 0))->pluck('loan_id'))
            ->unique();

        // Loans named in leftover repayment / withdrawal journals
        $numbers = DB::table('transaction_lines as l')->join('transactions as t', 't.id', '=', 'l.transaction_id')
            ->where('t.date', '>=', $f)->where('l.description', 'like', 'Loan repayment - LN-%')
            ->pluck('l.description')->map(fn ($d) => strtoupper(trim(explode(' ', substr($d, strlen('Loan repayment - ')))[0])))->unique();
        if ($numbers->isNotEmpty()) {
            $ids = $ids->merge(Loan::whereIn(DB::raw('UPPER(loan_number)'), $numbers->all())->pluck('id'))->unique();
        }

        return Loan::with('client', 'product')->whereIn('id', $ids)
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2->where('loan_number', 'like', "%{$search}%")
                ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$search}%"))))
            ->orderBy('loan_number')->get();
    }

    /** Everything linked to this loan that a reset would remove. */
    private function gather(Loan $loan, Carbon $from): array
    {
        $f   = $from->toDateString();
        $num = $loan->loan_number;

        $repayments = LoanRepayment::where('loan_id', $loan->id)->where('payment_date', '>=', $f)->orderBy('payment_date')->get();
        $withdrawals = SavingsTransaction::with('savingsAccount')
            ->where('transaction_date', '>=', $f)->where('transaction_type', 'withdrawal')
            ->where(fn ($q) => $q->where('description', $desc = "Loan repayment - {$num}")->orWhere('description', 'like', "{$desc} %"))
            ->get();

        $linked = $repayments->pluck('transaction_id')->merge($withdrawals->pluck('transaction_id'))->filter()->all();

        // Repayment/withdrawal journals for this loan not tied to a live record (reversed or orphaned).
        $loose = Transaction::where('date', '>=', $f)->whereNotIn('id', $linked ?: [0])
            ->where(fn ($q) => $q
                ->where(fn ($q2) => $q2->where('module', 'loan')->where(fn ($d) => $d->where('description', "Loan repayment: {$num}")->orWhere('description', 'like', "Loan repayment: {$num} %")))
                ->orWhere(fn ($q2) => $q2->where('module', 'savings')->whereHas('lines', fn ($l) => $l->where('description', "Loan repayment - {$num}")->orWhere('description', 'like', "Loan repayment - {$num} %"))))
            ->whereNull('reversal_of')
            ->get()
            ->filter(fn ($t) => $t->module === 'savings' ? !SavingsTransaction::where('transaction_id', $t->id)->exists() : !LoanRepayment::where('transaction_id', $t->id)->exists())
            ->values();

        return compact('repayments', 'withdrawals', 'loose');
    }

    private function planLoan(Loan $loan, Carbon $from): array
    {
        $g = $this->gather($loan, $from);
        $earlier = LoanRepayment::where('loan_id', $loan->id)->where('payment_date', '<', $from->toDateString())->count();
        $schedules = $loan->schedules()->get();
        $paidOnSchedule = round($schedules->sum(fn ($s) => $s->principal_paid + $s->interest_paid), 2);
        $leftover = round($paidOnSchedule - $g['repayments']->sum(fn ($r) => $r->principal_paid + $r->interest_paid), 2);
        $charges = LoanInterestCharge::where('loan_id', $loan->id)->get();
        $runs = LoanRun::where('loan_id', $loan->id)->count();
        $correction = LoanCorrection::where('loan_id', $loan->id)->where('status', 'applied')->latest('id')->first();
        $lockedUp = $loan->isLockedUp() || $schedules->isEmpty();

        $afterP = $lockedUp
            ? round($loan->outstanding_principal + $g['repayments']->sum('principal_paid'), 2)
            : round($schedules->sum('principal_due'), 2);
        $afterI = $lockedUp
            ? round($loan->outstanding_interest + $g['repayments']->sum('interest_paid'), 2)
            : round($correction ? $correction->interest_at_date : 0, 2);

        $hasWork = $g['repayments']->isNotEmpty() || $g['withdrawals']->isNotEmpty() || $g['loose']->isNotEmpty()
            || $charges->isNotEmpty() || $runs || $loan->interest_accrued_to !== null || abs($leftover) > 0.01;

        return [
            'loan' => $loan, 'has_work' => $hasWork,
            'repayments' => $g['repayments'], 'withdrawals' => $g['withdrawals'], 'loose' => $g['loose'],
            'charges' => $charges->count(), 'charges_total' => round($charges->sum('amount'), 2), 'runs' => $runs,
            'leftover' => $leftover, 'earlier' => $earlier, 'locked_up' => $lockedUp, 'correction' => $correction,
            'now' => ['principal' => round($loan->outstanding_principal, 2), 'interest' => round($loan->outstanding_interest, 2)],
            'after' => ['principal' => $afterP, 'interest' => $afterI],
            'skip' => $earlier && !$lockedUp ? "has {$earlier} repayment(s) before " . $from->format('d M Y') . ' — reset would need unwinding, not done' : null,
        ];
    }

    private function resetLoan(Loan $loan, Carbon $from): array
    {
        $plan = $this->planLoan($loan, $from);
        if ($plan['skip']) {
            return ['skipped' => $plan['skip']];
        }
        $g = $this->gather($loan, $from);
        $accounts = collect();

        foreach ($g['repayments'] as $rep) {
            $this->deleteJournal($rep->transaction_id);
            $rep->delete();
        }
        foreach ($g['withdrawals'] as $wd) {
            $accounts->push($wd->savings_account_id);
            $this->deleteJournal($wd->transaction_id);
            $wd->delete();
        }
        foreach ($g['loose'] as $tx) {
            $this->deleteJournal($tx->id);
        }
        foreach (SavingsAccount::whereIn('id', $accounts->unique())->get() as $acc) {
            $this->savings->recalculateLedger($acc);
        }

        LoanInterestCharge::where('loan_id', $loan->id)->delete();
        LoanRun::where('loan_id', $loan->id)->delete();

        if ($plan['locked_up']) {
            $loan->forceFill([
                'outstanding_principal' => $plan['after']['principal'],
                'outstanding_interest'  => $plan['after']['interest'],
                'outstanding_penalty'   => round((float) $loan->outstanding_penalty + $g['repayments']->sum('penalty_paid'), 2),
                'status'                => $loan->status === 'closed' ? 'defaulted' : $loan->status,
            ])->save();
        } else {
            $today = now()->toDateString();
            foreach ($loan->schedules()->get() as $s) {
                $s->forceFill([
                    'principal_paid' => 0, 'interest_paid' => 0, 'interest_charged' => false,
                    'status' => $s->due_date->toDateString() < $today ? 'overdue' : 'pending',
                ])->save();
            }
            $loan->forceFill([
                'outstanding_principal' => $plan['after']['principal'],
                'outstanding_interest'  => $plan['after']['interest'],
                'interest_accrued_to'   => null,
                'interest_carried'      => 0,
                'installment_amount'    => null,
                'status'                => $loan->status === 'closed' ? 'active' : $loan->status,
            ])->save();
            // Fresh day-based start: from 31/07/2026, the correction date, or disbursement.
            if ($loan->status === 'active') {
                $this->interest->convert($loan->fresh(), Carbon::parse(LoanInterestService::TRANSFER_DATE));
            }
        }

        $loan->refresh();
        AuditLog::record('loan_reset', "Reset {$loan->loan_number} from {$from->toDateString()}: removed {$g['repayments']->count()} repayment(s), "
            . "{$g['withdrawals']->count()} savings withdrawal(s), {$g['loose']->count()} other journal(s), {$plan['charges']} interest charge(s); "
            . 'outstanding now P ' . number_format($loan->outstanding_principal, 2) . ' / I ' . number_format($loan->outstanding_interest, 2), 'loans');

        return [
            'skipped' => null, 'loan' => $loan->loan_number, 'repayments' => $g['repayments']->count(),
            'repaid' => round($g['repayments']->sum('amount'), 2), 'withdrawals' => $g['withdrawals']->count(),
            'returned' => round($g['withdrawals']->sum(fn ($w) => abs($w->amount)), 2), 'loose' => $g['loose']->count(),
            'principal' => round($loan->outstanding_principal, 2), 'interest' => round($loan->outstanding_interest, 2),
        ];
    }

    private function deleteJournal(?int $id): void
    {
        if (!$id || !($tx = Transaction::find($id))) {
            return;
        }
        if ($tx->reversed_by) {
            Transaction::whereKey($tx->reversed_by)->delete();
        }
        $tx->delete();
    }
}
