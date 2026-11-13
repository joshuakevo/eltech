<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\OverdraftCharge;
use App\Models\OverdraftFeeRecognition;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Models\SystemSetting;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Overdraft charges on savings accounts.
 *
 *  Charge — when a withdrawal takes the account negative, staff are shown the charge (setting
 *  overdraft_charge_rate, default 1%, of the negative balance; at least overdraft_charge_minimum) and
 *  may change or waive it. It is added to the member's negative balance:
 *      DR member savings (product liability) / CR Accrued Overdraft Fees (setting; created as "Accrued Overdraft Fees" on the first free code from 2016)
 *  plus a statement line "Overdraft charge …" (module overdraft_charge).
 *
 *  Payment — money paid into the account reduces the negative balance as usual. Any accrued charges
 *  on that account are then recognised as income, oldest first, up to the amount paid:
 *      DR Accrued Overdraft Fees / CR Overdraft Fee Income (setting, default 4111)   (module overdraft_fee)
 *  Triggered by SavingsTransaction created events (deposits and incoming transfers from any screen).
 *  If that payment is later deleted/reversed, its recognition is reversed too.
 */
class OverdraftService
{
    public function __construct(protected AccountingService $accounting, protected SavingsService $savings) {}

    public function rate(): float
    {
        return (float) SystemSetting::get('overdraft_charge_rate', 1);
    }

    public function minimum(): float
    {
        return (float) SystemSetting::get('overdraft_charge_minimum', 0);
    }

    /** Suggested charge for a balance: rate % of the negative part (at least the minimum); 0 when not negative. */
    public function suggestedCharge(float $balanceAfter): float
    {
        if ($balanceAfter >= 0) {
            return 0.0;
        }

        return round(max($this->minimum(), abs($balanceAfter) * $this->rate() / 100), 2);
    }

    public function accruedAccountId(): ?int
    {
        $code = SystemSetting::get('overdraft_accrued_account');

        return $code
            ? Account::where('account_code', $code)->value('id')
            : Account::where('account_name', 'Accrued Overdraft Fees')->value('id');
    }

    public function incomeAccountId(): ?int
    {
        return Account::where('account_code', SystemSetting::get('overdraft_income_account', '4111'))->value('id');
    }

    /**
     * Charge an overdrawn account. $balanceAfter = the negative balance the charge is based on
     * (after the withdrawal). Returns null when the amount is 0 (waived).
     */
    public function charge(SavingsAccount $account, float $amount, string $date, float $balanceAfter, ?SavingsTransaction $withdrawal = null): ?OverdraftCharge
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            return null;
        }
        $accrued = $this->accruedAccountId();
        if (!$accrued) {
            throw new \InvalidArgumentException('The Accrued Overdraft Fees account (' . SystemSetting::get('overdraft_accrued_account', '') . ') was not found. Check Settings → Overdraft Charges.');
        }

        return DB::transaction(function () use ($account, $amount, $date, $balanceAfter, $withdrawal, $accrued) {
            $account = SavingsAccount::with('product')->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $rate = $this->rate();
            $charge = OverdraftCharge::create([
                'savings_account_id' => $account->id, 'client_id' => $account->client_id, 'charge_date' => $date,
                'overdrawn_balance' => round($balanceAfter, 2), 'rate' => $rate, 'amount' => $amount,
                'withdrawal_savings_transaction_id' => $withdrawal?->id, 'created_by' => auth()->id(),
            ]);

            $suggested = $this->suggestedCharge($balanceAfter);
            $desc = 'Overdraft charge' . (abs($amount - $suggested) < 0.01
                ? ' (' . rtrim(rtrim(number_format($rate, 4), '0'), '.') . '% of ' . number_format(abs($balanceAfter), 0) . ' overdrawn)'
                : ' on ' . number_format(abs($balanceAfter), 0) . ' overdrawn');
            $tx = $this->accounting->post($date, "{$desc} - {$account->account_number}", [
                ['account_id' => $account->product->savings_liability_account_id, 'debit' => $amount, 'credit' => 0, 'client_id' => $account->client_id, 'description' => "{$desc} - {$account->account_number}"],
                ['account_id' => $accrued, 'debit' => 0, 'credit' => $amount, 'client_id' => $account->client_id, 'description' => "Accrued overdraft fee - {$account->account_number}"],
            ], 'overdraft_charge', $charge->id);

            $row = SavingsTransaction::create([
                'savings_account_id' => $account->id, 'transaction_type' => 'withdrawal', 'amount' => $amount,
                'balance_before' => $account->balance, 'balance_after' => round($account->balance - $amount, 2),
                'transaction_date' => $date, 'reference' => $tx->reference, 'description' => $desc,
                'transaction_id' => $tx->id, 'created_by' => auth()->id(),
            ]);
            $this->savings->recalculateLedger($account->fresh());
            $charge->update(['savings_transaction_id' => $row->id, 'transaction_id' => $tx->id]);

            AuditLog::record('overdraft_charge', "{$account->account_number}: overdraft charge " . number_format($amount, 2) . " on " . number_format($balanceAfter, 2)
                . " (suggested " . number_format($suggested, 2) . ", {$rate}%)", 'savings');

            return $charge;
        });
    }

    /**
     * After an overdraft withdrawal: charge the account if it is now negative. $entered = the amount on the
     * form (blank = the suggested charge, 0 = waived). Based on the account balance right after the withdrawal.
     */
    public function chargeForWithdrawal(SavingsAccount $account, SavingsTransaction $withdrawal, $entered): ?OverdraftCharge
    {
        $balanceAfter = round($this->savings->balanceAsOf($account->fresh(), $withdrawal->transaction_date->toDateString()), 2);
        if ($balanceAfter >= 0) {
            return null;
        }
        $amount = ($entered === null || $entered === '') ? $this->suggestedCharge($balanceAfter) : (float) $entered;

        return $this->charge($account, $amount, $withdrawal->transaction_date->toDateString(), $balanceAfter, $withdrawal);
    }

    /** A statement row that puts money into the account (a payment towards the overdraft). */
    public static function isPayment(SavingsTransaction $row): bool
    {
        if (str_starts_with((string) $row->description, 'Overdraft charge')) {
            return false;
        }

        return $row->transaction_type === 'deposit'
            || ($row->transaction_type === 'transfer' && (float) $row->balance_after > (float) $row->balance_before);
    }

    /** Recognise the account's accrued overdraft fees as income, up to the amount of this payment (oldest charge first). */
    public function recogniseForPayment(SavingsTransaction $payment): ?OverdraftFeeRecognition
    {
        $charges = OverdraftCharge::where('savings_account_id', $payment->savings_account_id)
            ->whereColumn('recognised_amount', '<', 'amount')->orderBy('charge_date')->orderBy('id')->lockForUpdate()->get();
        if ($charges->isEmpty()) {
            return null;
        }
        $accrued = $this->accruedAccountId();
        $income  = $this->incomeAccountId();
        if (!$accrued || !$income) {
            return null;   // accounts missing: leave accrued; Settings page shows the problem
        }

        $left = round(abs((float) $payment->amount), 2);
        $allocations = [];
        foreach ($charges as $charge) {
            if ($left < 0.01) {
                break;
            }
            $take = round(min($left, $charge->unrecognised()), 2);
            if ($take < 0.01) {
                continue;
            }
            $charge->update(['recognised_amount' => round($charge->recognised_amount + $take, 2)]);
            $allocations[] = ['charge_id' => $charge->id, 'amount' => $take];
            $left = round($left - $take, 2);
        }
        $total = round(array_sum(array_column($allocations, 'amount')), 2);
        if ($total < 0.01) {
            return null;
        }

        $account = SavingsAccount::find($payment->savings_account_id);
        $date = $payment->transaction_date->toDateString();
        $rec = OverdraftFeeRecognition::create([
            'savings_account_id' => $payment->savings_account_id, 'savings_transaction_id' => $payment->id,
            'recognised_on' => $date, 'amount' => $total, 'allocations' => $allocations,
        ]);
        $tx = $this->accounting->post($date, "Overdraft fees earned - {$account->account_number}", [
            ['account_id' => $accrued, 'debit' => $total, 'credit' => 0, 'client_id' => $account->client_id, 'description' => "Accrued overdraft fee paid - {$account->account_number}"],
            ['account_id' => $income, 'debit' => 0, 'credit' => $total, 'client_id' => $account->client_id, 'description' => "Overdraft fee income - {$account->account_number}"],
        ], 'overdraft_fee', $rec->id);
        $rec->update(['transaction_id' => $tx->id]);

        return $rec;
    }

    /** Undo a recognition: give the amounts back to their charges; reverse its journal unless the caller is reversing it. */
    public function unrecognise(OverdraftFeeRecognition $rec, bool $reverseJournal = true): void
    {
        DB::transaction(function () use ($rec, $reverseJournal) {
            foreach ($rec->allocations ?? [] as $a) {
                if ($charge = OverdraftCharge::find($a['charge_id'])) {
                    $charge->update(['recognised_amount' => round(max(0, $charge->recognised_amount - (float) $a['amount']), 2)]);
                }
            }
            if ($reverseJournal && $rec->transaction_id) {
                $this->reverseJournal($rec->transaction_id, 'Overdraft fee payment reversed');
            }
            $rec->delete();
        });
    }

    /** Payment row deleted (its journal reversed/deleted): reverse what it recognised. */
    public function paymentRemoved(SavingsTransaction $payment): void
    {
        OverdraftFeeRecognition::where('savings_transaction_id', $payment->id)->get()->each(fn ($rec) => $this->unrecognise($rec));
    }

    /** Throws when a charge can't be reversed: part of it was already earned from the member's payments. */
    public function assertChargeReversible(OverdraftCharge $charge): void
    {
        if ($charge->recognised_amount > 0.005) {
            throw new \InvalidArgumentException(number_format($charge->recognised_amount, 2) . ' of this overdraft charge was already paid and moved to income. Reverse those payments first.');
        }
    }

    private function reverseJournal(int $txId, string $reason): void
    {
        $tx = Transaction::with('lines')->find($txId);
        if (!$tx || $tx->reversed_by) {
            return;
        }
        $reversal = $this->accounting->post(now()->toDateString(), "REVERSAL of {$tx->reference}: {$reason}",
            $tx->lines->map(fn ($l) => ['account_id' => $l->account_id, 'debit' => $l->credit, 'credit' => $l->debit, 'client_id' => $l->client_id, 'description' => 'Reversal: ' . $l->description])->all(),
            'reversal', $tx->id);
        $reversal->update(['reversal_of' => $tx->id, 'reversal_reason' => $reason]);
        $tx->update(['reversed_by' => $reversal->id]);
    }
}
