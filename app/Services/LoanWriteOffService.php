<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\FinancialPeriod;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\LoanWriteOff;
use App\Models\LoanWriteOffRecovery;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Models\SystemSetting;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Writes off a Locked-Up loan. One journal, module `loan_writeoff`, lines tagged to the member:
 *   principal  DR Bad Loan Write-Off (setting loan_writeoff_expense_account, 5121) / CR the Locked-Up receivable (product's, 1104)
 *   interest   DR Accrued Interest (loan_writeoff_interest_debit_account, 1106)   / CR Locked-Up Loans Interest Receivable (…credit_account, 1112)
 * The loan's outstanding principal / interest / penalty b/f go to 0 and its status becomes `written_off` (no recoveries,
 * no interest charges). The penalty brought forward is off the books, so it is cleared with no journal.
 * Undo (loan page) or reversing / deleting the journal restores the loan from the snapshot.
 */
class LoanWriteOffService
{
    public function __construct(protected AccountingService $accounting) {}

    public function accounts(): array
    {
        $find = fn (string $key, string $default) => Account::where('account_code', SystemSetting::get($key, $default) ?: $default)->first();

        return [
            'expense'         => $find('loan_writeoff_expense_account', '5121'),
            'interest_debit'  => $find('loan_writeoff_interest_debit_account', '1106'),
            'interest_credit' => $find('loan_writeoff_interest_credit_account', '1112'),
        ];
    }

    /** Reasons the loan can't be written off ([] = ok). */
    public function blockers(Loan $loan): array
    {
        $errors = [];
        if (!$loan->isLockedUp()) {
            $errors[] = 'Only Locked-Up loans can be written off. Lock the loan up first.';
        }
        if ($loan->status !== 'defaulted') {
            $errors[] = $loan->status === 'written_off' ? 'This loan is already written off.' : "This loan is {$loan->status}.";
        }
        if ((float) $loan->outstanding_principal + (float) $loan->outstanding_interest < 0.01) {
            $errors[] = 'Nothing is owed on this loan.';
        }
        if (!optional($loan->product)->receivable_account_id) {
            $errors[] = 'The Locked-Up Loans product has no receivable account.';
        }
        foreach ($this->accounts() as $key => $acc) {
            if (!$acc) {
                $errors[] = 'The write-off ' . str_replace('_', ' ', $key) . ' account is not set up (Settings → Loan Write-Off).';
            }
        }
        return $errors;
    }

    /** $in: writeoff_date, reason. */
    public function writeOff(Loan $loan, array $in): LoanWriteOff
    {
        return DB::transaction(function () use ($loan, $in) {
            $loan = Loan::with('product', 'client')->whereKey($loan->id)->lockForUpdate()->firstOrFail();
            $errors = $this->blockers($loan);

            $date = !empty($in['writeoff_date']) ? Carbon::parse($in['writeoff_date'])->startOfDay() : null;
            if (!$date) {
                $errors[] = 'Choose the write-off date.';
            } else {
                if ($date->gt(today())) {
                    $errors[] = 'The write-off date cannot be in the future.';
                }
                $lastPaid = LoanRepayment::where('loan_id', $loan->id)->max('payment_date');
                if ($lastPaid && $date->lt(Carbon::parse($lastPaid)->startOfDay())) {
                    $errors[] = 'The write-off date cannot be before the last recovery (' . Carbon::parse($lastPaid)->format('d M Y') . ').';
                }
                // Interest written off = interest charged and unpaid as at the date, so no charge may be dated after it
                $lastCharge = \App\Models\LockedUpInterestCharge::where('loan_id', $loan->id)->max('charge_date');
                if ($lastCharge && $date->lt(Carbon::parse($lastCharge)->startOfDay())) {
                    $errors[] = 'The write-off date cannot be before the last interest charge (' . Carbon::parse($lastCharge)->format('d M Y') . ').';
                }
                if (!FinancialPeriod::isOpen($date->toDateString())) {
                    $errors[] = 'The financial period for ' . $date->format('M Y') . ' is closed.';
                }
            }
            $reason = trim((string) ($in['reason'] ?? ''));
            if ($reason === '') {
                $errors[] = 'Give the reason for the write-off.';
            }
            if ($errors) {
                throw ValidationException::withMessages(['writeoff' => $errors]);
            }

            $principal = round((float) $loan->outstanding_principal, 2);
            $interest  = round((float) $loan->outstanding_interest, 2);
            $penalty   = round((float) $loan->penalty_brought_forward, 2);
            $acc = $this->accounts();

            $wo = LoanWriteOff::create([
                'loan_id' => $loan->id, 'writeoff_date' => $date->toDateString(),
                'principal' => $principal, 'interest' => $interest, 'penalty' => $penalty, 'reason' => $reason,
                'snapshot' => [
                    'loan' => collect($loan->getAttributes())->only(['status', 'outstanding_principal', 'outstanding_interest',
                        'outstanding_penalty', 'penalty_brought_forward', 'notes'])->all(),
                    'repayment_ids' => LoanRepayment::where('loan_id', $loan->id)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all(),
                ],
                'created_by' => auth()->id(),
            ]);

            $desc  = "Loan write-off - {$loan->loan_number}";
            $lines = [];
            $line  = fn ($accountId, $dr, $cr, $text) => ['account_id' => $accountId, 'debit' => $dr, 'credit' => $cr, 'client_id' => $loan->client_id, 'description' => $text];
            if ($principal > 0) {
                $lines[] = $line($acc['expense']->id, $principal, 0, "{$desc}: principal");
                $lines[] = $line($loan->product->receivable_account_id, 0, $principal, "{$desc}: principal");
            }
            if ($interest > 0) {
                $lines[] = $line($acc['interest_debit']->id, $interest, 0, "{$desc}: interest");
                $lines[] = $line($acc['interest_credit']->id, 0, $interest, "{$desc}: interest");
            }
            if ($lines) {
                $tx = $this->accounting->post($date->toDateString(), $desc, $lines, 'loan_writeoff', $wo->id);
                $wo->update(['transaction_id' => $tx->id]);
            }

            $loan->update([
                'status' => 'written_off', 'outstanding_principal' => 0, 'outstanding_interest' => 0,
                'outstanding_penalty' => 0, 'penalty_brought_forward' => 0,
                'notes' => trim(($loan->notes ? $loan->notes . "\n" : '') . "Written off on {$date->format('d/m/Y')}: principal "
                    . number_format($principal, 2) . ', interest ' . number_format($interest, 2) . ". {$reason}"),
            ]);

            AuditLog::record('loan_written_off', "Wrote off {$loan->loan_number} ({$loan->client->name}) on {$date->toDateString()}: principal "
                . number_format($principal, 2) . ", interest " . number_format($interest, 2)
                . ($penalty > 0 ? ', penalty b/f ' . number_format($penalty, 2) : '') . ". Reason: {$reason}", 'loans');

            return $wo;
        });
    }

    public function assertUndoable(LoanWriteOff $wo): void
    {
        $fail = fn ($m) => throw ValidationException::withMessages(['writeoff' => $m]);
        if ($wo->status !== 'applied') {
            $fail('This write-off has already been undone.');
        }
        $loan = Loan::find($wo->loan_id);
        if (!$loan || $loan->status !== 'written_off') {
            $fail('The loan is no longer written off.');
        }
        $now  = LoanRepayment::where('loan_id', $wo->loan_id)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $then = collect($wo->snapshot['repayment_ids'] ?? [])->map(fn ($id) => (int) $id)->sort()->values()->all();
        if ($now !== $then) {
            $fail('Recoveries have been added or removed since the write-off. Reverse those first.');
        }
        if (LoanWriteOffRecovery::where('loan_writeoff_id', $wo->id)->exists()) {
            $fail('Bad debts have been recovered on this write-off. Undo those recoveries first.');
        }
    }

    public function recoveredAccount(): ?Account
    {
        return Account::where('account_code', SystemSetting::get('bad_debts_recovered_account', '4116') ?: '4116')->first();
    }

    /**
     * Money collected on a written-off loan. $in: recovery_date, amount, source (cash|savings), payment_account_id
     * or savings_account_id, notes. DR cash / member savings (statement line) / CR Bad Debts Recovered.
     */
    public function recover(Loan $loan, array $in): LoanWriteOffRecovery
    {
        return DB::transaction(function () use ($loan, $in) {
            $loan = Loan::with('client')->whereKey($loan->id)->lockForUpdate()->firstOrFail();
            $wo = LoanWriteOff::where('loan_id', $loan->id)->where('status', 'applied')->latest('id')->lockForUpdate()->first();
            $errors = [];
            if (!$wo || $loan->status !== 'written_off') {
                throw ValidationException::withMessages(['recovery' => 'This loan is not written off.']);
            }
            $income = $this->recoveredAccount();
            if (!$income) {
                $errors[] = 'The bad debts recovered income account is not set up (Loans → Written Off).';
            }
            $amount = round((float) ($in['amount'] ?? 0), 2);
            $left = round($wo->total() - $wo->recovered(), 2);
            if ($amount <= 0) {
                $errors[] = 'Enter the amount recovered.';
            } elseif ($amount > $left + 0.005) {
                $errors[] = 'Only ' . number_format($left, 2) . ' of the ' . number_format($wo->total(), 2) . ' written off is still to be recovered.';
            }
            $date = !empty($in['recovery_date']) ? Carbon::parse($in['recovery_date'])->startOfDay() : null;
            if (!$date) {
                $errors[] = 'Choose the recovery date.';
            } else {
                if ($date->gt(today())) $errors[] = 'The recovery date cannot be in the future.';
                if ($date->lt($wo->writeoff_date)) $errors[] = 'The recovery date cannot be before the write-off (' . $wo->writeoff_date->format('d M Y') . ').';
                if (!FinancialPeriod::isOpen($date->toDateString())) $errors[] = 'The financial period for ' . $date->format('M Y') . ' is closed.';
            }

            $source = ($in['source'] ?? 'cash') === 'savings' ? 'savings' : 'cash';
            $payAcc = $savings = null;
            if ($source === 'cash') {
                $payAcc = Account::where('is_payment_source', true)->find($in['payment_account_id'] ?? null);
                if (!$payAcc) $errors[] = 'Choose the account the money was received into.';
            } else {
                $savings = SavingsAccount::with('product')->where('client_id', $loan->client_id)->where('status', 'active')->whereKey($in['savings_account_id'] ?? 0)->lockForUpdate()->first();
                if (!$savings || !$savings->product?->savings_liability_account_id) {
                    $errors[] = "Choose one of the member's active savings accounts.";
                } elseif ($date && $amount > 0) {
                    $available = min((float) $savings->balance, app(SavingsService::class)->balanceAsOf($savings, $date->toDateString()));
                    if ($amount > $available + 0.005) {
                        $errors[] = "{$savings->account_number} only has " . number_format(max(0, $available), 2) . ' available.';
                    }
                }
            }
            if ($errors) {
                throw ValidationException::withMessages(['recovery' => $errors]);
            }

            $rec = LoanWriteOffRecovery::create([
                'loan_writeoff_id' => $wo->id, 'loan_id' => $loan->id, 'recovery_date' => $date->toDateString(), 'amount' => $amount,
                'source' => $source, 'payment_account_id' => $payAcc?->id, 'savings_account_id' => $savings?->id,
                'notes' => $in['notes'] ?? null, 'created_by' => auth()->id(),
            ]);
            $desc = "Bad debt recovered - {$loan->loan_number}";
            $tx = $this->accounting->post($date->toDateString(), $desc, [
                ['account_id' => $payAcc ? $payAcc->id : $savings->product->savings_liability_account_id, 'debit' => $amount, 'credit' => 0,
                    'client_id' => $loan->client_id, 'description' => $savings ? "{$desc} — from savings {$savings->account_number}" : $desc],
                ['account_id' => $income->id, 'debit' => 0, 'credit' => $amount, 'client_id' => $loan->client_id, 'description' => $desc],
            ], 'loan_writeoff_recovery', $rec->id);
            $rec->transaction_id = $tx->id;

            if ($savings) {
                $row = SavingsTransaction::create([
                    'savings_account_id' => $savings->id, 'transaction_type' => 'withdrawal', 'amount' => $amount,
                    'balance_before' => $savings->balance, 'balance_after' => round($savings->balance - $amount, 2),
                    'transaction_date' => $date->toDateString(), 'reference' => $tx->reference, 'description' => $desc,
                    'transaction_id' => $tx->id, 'created_by' => auth()->id(),
                ]);
                $savings->update(['balance' => round($savings->balance - $amount, 2)]);
                app(SavingsService::class)->recalculateLedger($savings->fresh());
                $rec->savings_transaction_id = $row->id;
            }
            $rec->save();

            AuditLog::record('bad_debt_recovered', "{$loan->loan_number} ({$loan->client->name}): " . number_format($amount, 2) . " recovered on {$date->toDateString()} from "
                . ($savings ? "savings {$savings->account_number}" : "{$payAcc->account_code} {$payAcc->account_name}") . " (CR {$income->account_code} Bad Debts Recovered)", 'loans');

            return $rec;
        });
    }

    /** Remove a recovery. $reverseJournal = false when called from a journal reversal/delete (that already undoes GL + savings rows). */
    public function undoRecovery(LoanWriteOffRecovery $rec, bool $reverseJournal = true): void
    {
        DB::transaction(function () use ($rec, $reverseJournal) {
            $rec = LoanWriteOffRecovery::with('loan')->whereKey($rec->id)->lockForUpdate()->firstOrFail();

            if ($reverseJournal) {
                if ($rec->savings_transaction_id && ($row = SavingsTransaction::find($rec->savings_transaction_id))) {
                    $account = SavingsAccount::find($row->savings_account_id);
                    $row->delete();
                    if ($account) app(SavingsService::class)->recalculateLedger($account);
                }
                $tx = $rec->transaction_id ? Transaction::with('lines')->find($rec->transaction_id) : null;
                if ($tx && !$tx->reversed_by) {
                    $reversal = $this->accounting->post(now()->toDateString(), "REVERSAL of {$tx->reference}: Undo bad debt recovery",
                        $tx->lines->map(fn ($l) => ['account_id' => $l->account_id, 'debit' => $l->credit, 'credit' => $l->debit, 'client_id' => $l->client_id, 'description' => 'Reversal: ' . $l->description])->all(),
                        'reversal', $tx->id);
                    $reversal->update(['reversal_of' => $tx->id, 'reversal_reason' => 'Undo bad debt recovery']);
                    $tx->update(['reversed_by' => $reversal->id]);
                }
            }
            $rec->delete();
            AuditLog::record('bad_debt_recovery_undone', "Undid bad debt recovery of " . number_format($rec->amount, 2) . ' on ' . ($rec->loan->loan_number ?? "loan #{$rec->loan_id}"), 'loans');
        });
    }

    /** Restore the loan. $reverseJournal = false when called from a journal reversal/delete. */
    public function undo(LoanWriteOff $wo, bool $reverseJournal = true): void
    {
        DB::transaction(function () use ($wo, $reverseJournal) {
            $wo = LoanWriteOff::whereKey($wo->id)->lockForUpdate()->firstOrFail();
            $this->assertUndoable($wo);
            $loan = Loan::whereKey($wo->loan_id)->lockForUpdate()->firstOrFail();
            $loan->forceFill($wo->snapshot['loan'])->save();

            if ($reverseJournal && $wo->transaction_id) {
                $tx = Transaction::with('lines')->find($wo->transaction_id);
                if ($tx && !$tx->reversed_by) {
                    $reversal = $this->accounting->post(now()->toDateString(), "REVERSAL of {$tx->reference}: Undo loan write-off",
                        $tx->lines->map(fn ($l) => ['account_id' => $l->account_id, 'debit' => $l->credit, 'credit' => $l->debit, 'client_id' => $l->client_id, 'description' => 'Reversal: ' . $l->description])->all(),
                        'reversal', $tx->id);
                    $reversal->update(['reversal_of' => $tx->id, 'reversal_reason' => 'Undo loan write-off']);
                    $tx->update(['reversed_by' => $reversal->id]);
                }
            }

            $wo->update(['status' => 'reversed', 'reversed_at' => now(), 'reversed_by' => auth()->id()]);
            AuditLog::record('loan_writeoff_undone', "Undid write-off of {$loan->loan_number} (principal " . number_format($wo->principal, 2)
                . ', interest ' . number_format($wo->interest, 2) . ' restored)', 'loans');
        });
    }
}
