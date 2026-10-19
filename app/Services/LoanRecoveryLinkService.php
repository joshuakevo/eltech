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

    private function withdrawalsFor(Loan $loan): Collection
    {
        $desc = self::PREFIX . $loan->loan_number;
        return SavingsTransaction::where('transaction_type', 'withdrawal')
            ->where(fn ($q) => $q->where('description', $desc)->orWhere('description', 'like', "{$desc} %"))
            ->whereHas('savingsAccount', fn ($q) => $q->where('client_id', $loan->client_id))
            ->orderBy('transaction_date')->orderBy('id')->get();
    }
}
