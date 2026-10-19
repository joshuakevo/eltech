<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\SavingsTransaction;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Keeps loan repayments paid from savings and the savings withdrawals that funded them together.
 *
 * A recovery from savings is two journals: the savings withdrawal ("Loan repayment - LN-…" on the member's
 * statement) and the loan repayment. Removing the repayment (journal delete / reversal) must remove the
 * withdrawal too, or the money leaves the member's savings without reaching the loan.
 * Link: loan_repayments.savings_transaction_id; older rows are paired by loan number, date and amount.
 */
class LoanRecoveryLinkService
{
    public function __construct(protected SavingsService $savings) {}

    private const PREFIX = 'Loan repayment - ';

    /** The savings withdrawal that funded this repayment, if any. */
    public function fundingWithdrawal(LoanRepayment $repayment): ?SavingsTransaction
    {
        if ($repayment->savings_transaction_id) {
            return SavingsTransaction::find($repayment->savings_transaction_id);
        }
        $loan = Loan::withTrashed()->find($repayment->loan_id);
        if (!$loan) {
            return null;
        }

        // Withdrawals already claimed by another repayment of this loan
        $claimed = LoanRepayment::where('loan_id', $loan->id)->where('id', '!=', $repayment->id)
            ->whereNotNull('savings_transaction_id')->pluck('savings_transaction_id');

        return $this->withdrawalsFor($loan)
            ->whereNotIn('id', $claimed)
            ->filter(fn ($w) => $w->transaction_date->toDateString() === $repayment->payment_date->toDateString()
                && abs((float) $w->amount - (float) $repayment->amount) < 0.01)
            ->first();
    }

    /** The live repayment this savings withdrawal paid, if any. */
    public function repaymentFor(SavingsTransaction $withdrawal): ?LoanRepayment
    {
        if ($withdrawal->transaction_type !== 'withdrawal' || !str_starts_with((string) $withdrawal->description, self::PREFIX)) {
            return null;
        }
        if ($linked = LoanRepayment::where('savings_transaction_id', $withdrawal->id)->first()) {
            return $linked;
        }
        $number = trim(explode(' ', substr($withdrawal->description, strlen(self::PREFIX)))[0]);
        $loan = Loan::withTrashed()->where('loan_number', $number)->first();
        if (!$loan) {
            return null;
        }
        foreach (LoanRepayment::where('loan_id', $loan->id)->whereNull('savings_transaction_id')
            ->whereDate('payment_date', $withdrawal->transaction_date->toDateString())->get() as $r) {
            if (abs((float) $r->amount - (float) $withdrawal->amount) < 0.01
                && (int) optional($this->fundingWithdrawal($r))->id === (int) $withdrawal->id) {
                return $r;
            }
        }
        return null;
    }

    /** Deletes a funding withdrawal outright: its statement row, its journal (and any reversal of it); rebuilds the savings ledger. */
    public function removeWithdrawal(SavingsTransaction $withdrawal, string $why): void
    {
        DB::transaction(function () use ($withdrawal, $why) {
            $account = $withdrawal->savingsAccount;
            if ($withdrawal->transaction_id && ($tx = Transaction::find($withdrawal->transaction_id))) {
                if ($tx->reversed_by) {
                    Transaction::whereKey($tx->reversed_by)->delete();
                }
                // Other statement rows on the same journal (none for recoveries, but never leave them dangling)
                SavingsTransaction::where('transaction_id', $tx->id)->where('id', '!=', $withdrawal->id)->get()
                    ->each(fn ($o) => $o->delete());
                $tx->delete();
            }
            LoanRepayment::where('savings_transaction_id', $withdrawal->id)->update(['savings_transaction_id' => null]);
            $withdrawal->delete();
            if ($account) {
                $this->savings->recalculateLedger($account);
            }
            AuditLog::record('loan_recovery_withdrawal_removed',
                "Removed savings withdrawal " . number_format((float) $withdrawal->amount, 2) . " ({$withdrawal->description}, {$withdrawal->transaction_date->format('d M Y')}) from " . ($account->account_number ?? '?') . " — {$why}",
                'savings');
        });
    }

    /**
     * Withdrawals described "Loan repayment - <loan>" with no live repayment behind them:
     * each withdrawal is matched to a repayment of that loan (by link, else same date and amount); the rest are leftovers.
     */
    public function orphans(): Collection
    {
        $withdrawals = SavingsTransaction::with('savingsAccount.client')
            ->where('transaction_type', 'withdrawal')
            ->where('description', 'like', self::PREFIX . '%')
            ->orderBy('transaction_date')->orderBy('id')->get();
        if ($withdrawals->isEmpty()) {
            return collect();
        }

        $numberOf = fn ($w) => strtoupper(trim(explode(' ', substr($w->description, strlen(self::PREFIX)))[0]));
        $loans = Loan::withTrashed()->whereIn('loan_number', $withdrawals->map($numberOf)->unique()->values())->get()->keyBy(fn ($l) => strtoupper($l->loan_number));
        $repayments = LoanRepayment::whereIn('loan_id', $loans->pluck('id'))->get()->groupBy('loan_id');

        $orphans = collect();
        foreach ($withdrawals->groupBy($numberOf) as $number => $group) {
            $loan = $loans->get($number);
            $pool = $loan ? ($repayments->get($loan->id) ?? collect())->values() : collect();
            $linked = $pool->pluck('savings_transaction_id')->filter()->map(fn ($id) => (int) $id)->all();
            $free = $pool->filter(fn ($r) => !$r->savings_transaction_id)->values()->all();

            foreach ($group as $w) {
                if (in_array((int) $w->id, $linked, true)) {
                    continue;
                }
                $hit = null;
                foreach ($free as $k => $r) {
                    if ($r->payment_date->toDateString() === $w->transaction_date->toDateString() && abs((float) $r->amount - (float) $w->amount) < 0.01) {
                        $hit = $k;
                        break;
                    }
                }
                if ($hit !== null) {
                    unset($free[$hit]);
                    continue;
                }
                $w->setAttribute('loan', $loan);
                $orphans->push($w);
            }
        }

        return $orphans;
    }

    /** Repayments recorded for more than was applied to principal + interest + penalty (the extra was posted nowhere). */
    public function overpaid(): Collection
    {
        return LoanRepayment::with('loan.client')
            ->whereRaw('amount - (principal_paid + interest_paid + penalty_paid) > 0.01')
            ->orderBy('payment_date')->get()
            ->each(function ($r) {
                $r->setAttribute('excess', round((float) $r->amount - (float) $r->principal_paid - (float) $r->interest_paid - (float) $r->penalty_paid, 2));
                $r->setAttribute('funding', $this->fundingWithdrawal($r));
            });
    }

    /**
     * Brings an overpaid repayment down to what was applied, and its savings withdrawal (statement row + journal)
     * down by the same amount, so the extra goes back on the member's savings and cash agrees with the repayment.
     */
    public function fixOverpaid(LoanRepayment $repayment): float
    {
        $excess = round((float) $repayment->amount - (float) $repayment->principal_paid - (float) $repayment->interest_paid - (float) $repayment->penalty_paid, 2);
        if ($excess <= 0.01) {
            return 0.0;
        }

        return DB::transaction(function () use ($repayment, $excess) {
            $withdrawal = $this->fundingWithdrawal($repayment);   // matched on the current amount, so look it up first
            $old = (float) $repayment->amount;
            $repayment->update(['amount' => round($old - $excess, 2)]);

            if ($withdrawal) {
                $wOld = (float) $withdrawal->amount;
                $withdrawal->update(['amount' => round($wOld - $excess, 2)]);
                if ($withdrawal->transaction_id && ($tx = Transaction::with('lines')->find($withdrawal->transaction_id))) {
                    $dr = $tx->lines->first(fn ($l) => abs((float) $l->debit - $wOld) < 0.01);
                    $cr = $tx->lines->first(fn ($l) => abs((float) $l->credit - $wOld) < 0.01);
                    if (!$dr || !$cr) {
                        throw new \RuntimeException("Withdrawal journal {$tx->reference} doesn't have the expected lines — fix it by hand.");
                    }
                    $dr->update(['debit' => round($wOld - $excess, 2)]);
                    $cr->update(['credit' => round($wOld - $excess, 2)]);
                }
                $repayment->update(['savings_transaction_id' => $withdrawal->id]);
                if ($withdrawal->savingsAccount) {
                    $this->savings->recalculateLedger($withdrawal->savingsAccount);
                }
            }

            AuditLog::record('loan_repayment_overpayment_fixed',
                'Repayment on ' . ($repayment->loan->loan_number ?? '?') . " of {$repayment->payment_date->format('d M Y')}: " . number_format($old, 2) . ' → ' . number_format($old - $excess, 2)
                . ' (only that was applied); ' . ($withdrawal ? number_format($excess, 2) . ' returned to ' . ($withdrawal->savingsAccount->account_number ?? 'savings') : 'no savings withdrawal'),
                'loans');

            return $excess;
        });
    }

    private function withdrawalsFor(Loan $loan): Collection
    {
        $desc = self::PREFIX . $loan->loan_number;
        return SavingsTransaction::where('transaction_type', 'withdrawal')
            ->where(fn ($q) => $q->where('description', $desc)->orWhere('description', 'like', "{$desc} %"))
            ->whereHas('savingsAccount', fn ($q) => $q->where('client_id', $loan->client_id))
            ->orderBy('transaction_date')->orderBy('id')->get();
    }
}
