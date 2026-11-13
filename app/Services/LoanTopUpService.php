<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\FinancialPeriod;
use App\Models\Loan;
use App\Models\LoanCorrection;
use App\Models\LoanInterestCharge;
use App\Models\LoanLockup;
use App\Models\LoanRepayment;
use App\Models\LoanRun;
use App\Models\LoanSchedule;
use App\Models\LoanTopUp;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Top-up: lend more money on a running loan and restart it on new terms.
 *
 *  1. The loan is brought up to the top-up date on its old terms: overdue installments are charged
 *     their late penalty, and interest is charged by days from the last charge to the top-up date.
 *  2. The new money is paid out: DR the product's loan receivable / CR a cash, bank or mobile-money
 *     account, or CR the member's savings (statement row "Loan top-up - LN-…"); module `loan_topup`.
 *  3. The loan restarts on the top-up date with principal = balance + top-up and the new rate, method
 *     and number of months (disbursement date = top-up date, so installments fall on that day). The
 *     schedule is rebuilt by LoanCorrectionService (no journal); interest owed at the top-up is carried
 *     into the first installments and unpaid penalties move to installment 1.
 *  4. Everything is snapshotted: Undo (or reversing the journal) restores the loan exactly, while no
 *     repayments, Run Loans steps, corrections or lock-ups have happened since.
 */
class LoanTopUpService
{
    public function __construct(
        protected AccountingService $accounting,
        protected LoanCorrectionService $corrections,
        protected LoanInterestService $interest,
        protected LoanService $loans,
        protected SavingsService $savings,
    ) {}

    /** Dry run: top up inside a transaction that is rolled back. */
    public function preview(Loan $loan, array $in): array
    {
        DB::beginTransaction();
        try {
            $topUp = $this->topUp($loan, $in, $in['reason'] ?? 'Preview');
            $after = Loan::with('schedules')->find($loan->id);
            $journal = Transaction::with('lines.account')->whereIn('id', array_filter([$topUp->transaction_id, $topUp->fee_transaction_id]))->orderBy('id')->get()
                ->flatMap(fn ($tx) => $tx->lines)
                ->map(fn ($l) => ['code' => $l->account->account_code, 'name' => $l->account->account_name, 'debit' => (float) $l->debit, 'credit' => (float) $l->credit])->values()->all();

            return [
                'ok'          => true,
                'balance'     => $topUp->balance_before,
                'amount'      => $topUp->amount,
                'fees'        => ['application' => $topUp->application_fee, 'management' => $topUp->management_fee, 'insurance' => $topUp->insurance_fee,
                    'from_loan' => round($topUp->amount - $topUp->net_payout, 2), 'from_savings' => round($topUp->totalFees() - ($topUp->amount - $topUp->net_payout), 2)],
                'net_payout'  => $topUp->net_payout,
                'principal'   => (float) $after->outstanding_principal,
                'interest'    => $topUp->interest_owed,
                'penalty'     => $topUp->penalty_carried,
                'installment' => (float) ($after->installment_amount ?: optional($after->schedules->first())->total_due),
                'maturity'    => optional($after->maturity_date)->format('d M Y'),
                'journal'     => $journal,
                'schedule'    => $after->schedules->sortBy('installment_no')->map(fn ($s) => [
                    'no' => $s->installment_no, 'due' => Carbon::parse($s->due_date)->format('d M Y'),
                    'principal' => (float) $s->principal_due, 'interest' => (float) $s->interest_due,
                    'penalty' => max(0, (float) $s->penalty_charged - (float) $s->penalty_paid),
                    'total' => (float) $s->total_due, 'balance' => (float) $s->balance_after,
                ])->values()->all(),
            ];
        } catch (ValidationException $e) {
            return ['ok' => false, 'errors' => collect($e->errors())->flatten()->values()->all()];
        } finally {
            DB::rollBack();
        }
    }

    public function topUp(Loan $loan, array $in, string $reason): LoanTopUp
    {
        return DB::transaction(function () use ($loan, $in, $reason) {
            $loan = Loan::with('product', 'client')->whereKey($loan->id)->lockForUpdate()->firstOrFail();
            $v = $this->validate($loan, $in);
            $date = $v['date'];

            $snapshot = [
                // Raw stored values (casting dates through JSON shifts them by the timezone).
                'loan'          => $loan->getAttributes(),
                'schedules'     => $loan->schedules()->orderBy('installment_no')->get()->map(fn ($s) => $s->getAttributes())->all(),
                'repayment_ids' => LoanRepayment::where('loan_id', $loan->id)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all(),
                'last_run_id'   => (int) LoanRun::where('loan_id', $loan->id)->max('id'),
            ];
            $chargesBefore = (int) LoanInterestCharge::where('loan_id', $loan->id)->max('id');

            // 1. Bring the loan up to the top-up date on its old terms
            if (!$loan->isDayBasedInterest()) {
                $this->interest->convert($loan, $date);
                $loan->refresh();
            }
            $this->loans->chargeLatePenalties($loan, $date->toDateString());
            $this->interest->chargeForSettlement($loan, $date);   // by days, old rate
            $loan->refresh();
            $snapshot['charge_ids'] = LoanInterestCharge::where('loan_id', $loan->id)->where('id', '>', $chargesBefore)->pluck('id')->all();

            $balance     = round((float) $loan->outstanding_principal, 2);
            $interestOwed = round(max(0, (float) $loan->outstanding_interest), 2);
            $penalty     = round($loan->schedules()->get()->sum(fn ($s) => max(0, (float) $s->penalty_charged - (float) $s->penalty_paid)), 2);
            $amount      = $v['amount'];
            $newPrincipal = round($balance + $amount, 2);

            $topUp = LoanTopUp::create([
                'loan_id' => $loan->id, 'topup_date' => $date->toDateString(), 'amount' => $amount,
                'balance_before' => $balance, 'interest_owed' => $interestOwed, 'penalty_carried' => $penalty,
                'new_principal' => $newPrincipal, 'interest_rate' => $v['rate'], 'interest_method' => $v['method'],
                'term_months' => $v['term'], 'installment_amount' => $v['installment'], 'payout' => $v['payout'],
                'payout_account_id' => $v['payout'] === 'account' ? $v['payout_account']?->id : null,
                'savings_account_id' => $v['savings_account']?->id,
                'net_payout' => round($amount - $v['loanFees'], 2),
                'snapshot' => $snapshot, 'reason' => $reason, 'created_by' => auth()->id(),
            ] + $v['fees']);

            // 2. Pay out the new money, less fees taken from the loan (as at disbursement)
            $desc = "Loan top-up - {$loan->loan_number}";
            $net  = round($amount - $v['loanFees'], 2);
            $fees = $v['fees'];
            $feeLines = function (string $from) use ($fees, $loan) {
                $lines = [];
                foreach ([['application_fee', $this->loans->getApplicationFeeAccount(), 'Application fee'],
                          ['management_fee', $this->loans->getManagementFeeAccount(), 'Management fee'],
                          ['insurance_fee', $this->loans->getInsurancePayableAccount(), 'Insurance payable']] as [$key, $accountId, $label]) {
                    if ($fees[$key . '_method'] === $from && $fees[$key] > 0) {
                        $lines[] = ['account_id' => $accountId, 'debit' => 0, 'credit' => $fees[$key], 'client_id' => $loan->client_id, 'description' => "{$label} (top-up) - {$loan->loan_number}"];
                    }
                }
                return $lines;
            };
            $creditAccountId = $v['payout'] === 'savings' ? $v['savings_account']->product->savings_liability_account_id : $v['payout_account']->id;
            $tx = $this->accounting->post($date->toDateString(), "Loan top-up: {$loan->loan_number} (+" . number_format($amount, 2) . ')', array_merge([
                ['account_id' => $v['receivable_id'], 'debit' => $amount, 'credit' => 0, 'client_id' => $loan->client_id, 'description' => "Loan receivable - {$loan->loan_number} (top-up)"],
                ['account_id' => $creditAccountId, 'debit' => 0, 'credit' => $net, 'client_id' => $loan->client_id,
                    'description' => $v['loanFees'] > 0 ? "{$desc} (net of fees)" : $desc],
            ], $feeLines('loan')), 'loan_topup', $topUp->id);
            $topUp->transaction_id = $tx->id;

            $savingsRow = function (string $type, float $amt, string $description, Transaction $journal) use ($v, $date) {
                $account = SavingsAccount::whereKey($v['savings_account']->id)->lockForUpdate()->first();
                $after = round($account->balance + ($type === 'deposit' ? $amt : -$amt), 2);
                $row = SavingsTransaction::create([
                    'savings_account_id' => $account->id, 'transaction_type' => $type, 'amount' => $amt,
                    'balance_before' => $account->balance, 'balance_after' => $after,
                    'transaction_date' => $date->toDateString(), 'reference' => $journal->reference, 'description' => $description,
                    'transaction_id' => $journal->id, 'created_by' => auth()->id(),
                ]);
                $account->update(['balance' => $after]);
                $this->savings->recalculateLedger($account->fresh());
                return $row;
            };
            if ($v['payout'] === 'savings') {
                $topUp->savings_transaction_id = $savingsRow('deposit', $net, $v['loanFees'] > 0 ? "{$desc} (net of fees)" : $desc, $tx)->id;
            }

            // Fees taken from savings: their own journal and statement line (as at disbursement)
            if ($v['savingsFees'] > 0) {
                $feeDesc = "Loan top-up fees - {$loan->loan_number}";
                $feeTx = $this->accounting->post($date->toDateString(), "Loan top-up fees deducted from savings: {$loan->loan_number}", array_merge([
                    ['account_id' => $v['savings_account']->product->savings_liability_account_id, 'debit' => $v['savingsFees'], 'credit' => 0, 'client_id' => $loan->client_id,
                        'description' => "{$feeDesc} — {$v['savings_account']->account_number}"],
                ], $feeLines('savings')), 'loan_topup', $topUp->id);
                $topUp->fee_transaction_id = $feeTx->id;
                $topUp->fee_savings_transaction_id = $savingsRow('withdrawal', $v['savingsFees'], $feeDesc, $feeTx)->id;
            }

            // 3. Restart the loan on the new terms from the top-up date
            $loan->update([
                'principal' => $newPrincipal, 'interest_rate' => $v['rate'], 'interest_method' => $v['method'],
                'repayment_frequency' => 'monthly', 'term_months' => $v['term'],
                'disbursement_date' => $date->toDateString(), 'maturity_date' => $date->copy()->addMonthsNoOverflow($v['term'])->toDateString(),
                'status' => 'active', 'interest_accrued_to' => null, 'interest_carried' => 0, 'installment_amount' => null,
                'notes' => trim(($loan->notes ? $loan->notes . "\n" : '') . 'Topped up by ' . number_format($amount, 2) . " on {$date->format('d/m/Y')}: balance "
                    . number_format($balance, 2) . ' → ' . number_format($newPrincipal, 2) . ", {$v['rate']}% {$v['method']}, {$v['term']} months (was disbursed "
                    . ($snapshot['loan']['disbursement_date'] ?? '—') . ', principal ' . number_format((float) ($snapshot['loan']['principal'] ?? 0), 2) . ').'),
            ]);
            $correction = $this->corrections->apply($loan->fresh(), [
                'as_at_date' => $date->toDateString(), 'principal' => $newPrincipal, 'interest' => $interestOwed,
                'installment' => $v['installment'], 'no_journal' => true,
            ], "Top-up of " . number_format($amount, 2) . ": {$reason}");
            $topUp->correction_id = $correction->id;

            // Unpaid penalties stay owed: on the first new installment
            if ($penalty > 0) {
                $first = LoanSchedule::where('loan_id', $loan->id)->orderBy('installment_no')->first();
                $first?->forceFill(['penalty_charged' => $penalty, 'penalty_paid' => 0, 'penalty_charged_on' => $date->toDateString(), 'penalty_source' => 'topup'])->save();
            }
            $topUp->save();

            AuditLog::record('loan_topup', "Topped up {$loan->loan_number} by " . number_format($amount, 2) . " on {$date->toDateString()} (paid to "
                . ($v['payout'] === 'savings' ? "savings {$v['savings_account']->account_number}" : $v['payout_account']->account_code . ' ' . $v['payout_account']->account_name)
                . "): fees " . number_format($topUp->totalFees(), 2) . ' (from loan ' . number_format($v['loanFees'], 2) . ', from savings ' . number_format($v['savingsFees'], 2)
                . '), net paid out ' . number_format($net, 2) . "; principal " . number_format($balance, 2) . ' → ' . number_format($newPrincipal, 2) . ", interest owed " . number_format($interestOwed, 2)
                . ", {$v['rate']}% {$v['method']}, {$v['term']} months" . ($v['installment'] ? ', installment ' . number_format($v['installment'], 2) : '') . ". Reason: {$reason}", 'loans');

            return $topUp;
        });
    }

    public function assertUndoable(LoanTopUp $topUp): void
    {
        $fail = fn ($m) => throw ValidationException::withMessages(['topup' => $m]);
        if ($topUp->status !== 'applied') {
            $fail('This top-up has already been undone.');
        }
        if (LoanTopUp::where('loan_id', $topUp->loan_id)->where('status', 'applied')->where('id', '>', $topUp->id)->exists()) {
            $fail('A later top-up exists on this loan. Undo that one first.');
        }
        $now = LoanRepayment::where('loan_id', $topUp->loan_id)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        if ($now !== collect($topUp->snapshot['repayment_ids'] ?? [])->map(fn ($id) => (int) $id)->sort()->values()->all()) {
            $fail('Repayments have been added or removed since the top-up. Reverse those first.');
        }
        if (LoanRun::where('loan_id', $topUp->loan_id)->where('status', 'applied')->where('id', '>', (int) ($topUp->snapshot['last_run_id'] ?? 0))->exists()) {
            $fail('Run Loans has processed this loan since the top-up. Undo those runs first.');
        }
        if (LoanCorrection::where('loan_id', $topUp->loan_id)->where('status', 'applied')->where('id', '>', (int) $topUp->correction_id)->exists()) {
            $fail('A later balance correction exists on this loan. Undo that first.');
        }
        if (LoanLockup::where('loan_id', $topUp->loan_id)->where('status', 'executed')->where('executed_at', '>', $topUp->created_at)->exists()) {
            $fail('The loan was locked up after the top-up. Undo the lock-up first.');
        }
        if ($topUp->savings_transaction_id && $topUp->savings_account_id) {
            // Undo takes the deposit back and gives any fees from savings back
            $deposit = (float) SavingsTransaction::whereKey($topUp->savings_transaction_id)->value('amount');
            $feeBack = (float) SavingsTransaction::whereKey($topUp->fee_savings_transaction_id)->value('amount');
            $balance = (float) SavingsAccount::whereKey($topUp->savings_account_id)->value('balance');
            if ($balance - $deposit + $feeBack < -0.005) {
                $fail('The member has already used the top-up money in savings (balance ' . number_format($balance, 2) . '). Undoing it would overdraw the account.');
            }
        }
    }

    /**
     * Restore the loan as it was before the top-up and reverse its journals (payout and fees from savings).
     * $reverseJournal = false when called from a journal reversal/delete: $reversingTxId is that journal,
     * which the caller unwinds itself; the top-up's other journal is still reversed here.
     */
    public function undo(LoanTopUp $topUp, bool $reverseJournal = true, ?int $reversingTxId = null): void
    {
        DB::transaction(function () use ($topUp, $reverseJournal, $reversingTxId) {
            $topUp = LoanTopUp::whereKey($topUp->id)->lockForUpdate()->firstOrFail();
            $this->assertUndoable($topUp);
            $loan = Loan::whereKey($topUp->loan_id)->lockForUpdate()->firstOrFail();

            if ($topUp->correction_id) {
                LoanCorrection::whereKey($topUp->correction_id)->where('status', 'applied')
                    ->update(['status' => 'reversed', 'reversed_at' => now(), 'reversed_by' => auth()->id()]);
            }
            LoanInterestCharge::whereIn('id', $topUp->snapshot['charge_ids'] ?? [])->delete();
            $loan->schedules()->delete();
            foreach ($topUp->snapshot['schedules'] ?? [] as $attrs) {
                LoanSchedule::forceCreate(collect($attrs)->except(['created_at', 'updated_at'])->all());
            }
            $loan->forceFill(collect($topUp->snapshot['loan'])->except(['id', 'created_at', 'updated_at'])->all())->save();

            $accounts = [];
            foreach (SavingsTransaction::whereIn('id', array_filter([$topUp->savings_transaction_id, $topUp->fee_savings_transaction_id]))->get() as $row) {
                $accounts[$row->savings_account_id] = true;
                $row->delete();
            }
            foreach (array_keys($accounts) as $accountId) {
                if ($account = SavingsAccount::find($accountId)) {
                    $this->savings->recalculateLedger($account);
                }
            }

            foreach (array_filter([$topUp->transaction_id, $topUp->fee_transaction_id]) as $txId) {
                if (!$reverseJournal && (int) $txId === (int) $reversingTxId) {
                    continue;   // the journal being reversed/deleted by the caller
                }
                $tx = Transaction::with('lines')->find($txId);
                if ($tx && !$tx->reversed_by) {
                    $reversal = $this->accounting->post(now()->toDateString(), "REVERSAL of {$tx->reference}: Undo loan top-up",
                        $tx->lines->map(fn ($l) => ['account_id' => $l->account_id, 'debit' => $l->credit, 'credit' => $l->debit, 'client_id' => $l->client_id, 'description' => 'Reversal: ' . $l->description])->all(),
                        'reversal', $tx->id);
                    $reversal->update(['reversal_of' => $tx->id, 'reversal_reason' => 'Undo loan top-up']);
                    $tx->update(['reversed_by' => $reversal->id]);
                }
            }

            $topUp->update(['status' => 'reversed', 'reversed_at' => now(), 'reversed_by' => auth()->id()]);
            AuditLog::record('loan_topup_undone', "Undid top-up of " . number_format($topUp->amount, 2) . " on {$loan->loan_number} (top-up #{$topUp->id})", 'loans');
        });
    }

    /** Payout accounts offered on the form: cash, bank and mobile-money accounts. */
    public function payoutAccounts()
    {
        return Account::where('is_payment_source', true)->where('is_active', true)->orderBy('account_code')->get();
    }

    private function validate(Loan $loan, array $in): array
    {
        $errors = [];
        if ($loan->isLockedUp()) {
            $errors[] = 'Locked-Up loans cannot be topped up. Move it to active loans first.';
        }
        if (!in_array($loan->status, ['active', 'defaulted'], true) || !$loan->disbursement_date) {
            $errors[] = 'Only running loans can be topped up.';
        }
        if (LoanLockup::where('loan_id', $loan->id)->where('status', 'pending')->exists()) {
            $errors[] = 'A lock-up request is pending on this loan. Withdraw or reject it first.';
        }

        $amount = round((float) ($in['amount'] ?? 0), 2);
        if ($amount <= 0) {
            $errors[] = 'Enter the top-up amount.';
        }

        $date = !empty($in['topup_date']) ? Carbon::parse($in['topup_date'])->startOfDay() : null;
        if (!$date) {
            $errors[] = 'Choose the top-up date.';
        } else {
            if ($date->gt(today())) {
                $errors[] = 'The top-up date cannot be in the future.';
            }
            $lastPaid = LoanRepayment::where('loan_id', $loan->id)->max('payment_date');
            if ($lastPaid && $date->lt(Carbon::parse($lastPaid)->startOfDay())) {
                $errors[] = 'The top-up date cannot be before the last repayment (' . Carbon::parse($lastPaid)->format('d M Y') . ').';
            }
            if ($loan->disbursement_date && $date->lt($loan->disbursement_date->copy()->startOfDay())) {
                $errors[] = 'The top-up date cannot be before the loan was disbursed (' . $loan->disbursement_date->format('d M Y') . ').';
            }
            if ($loan->interest_accrued_to && $date->lt($loan->interest_accrued_to->copy()->startOfDay())) {
                $errors[] = 'Interest is already charged up to ' . $loan->interest_accrued_to->format('d M Y') . '. Choose that date or later.';
            }
            if (!FinancialPeriod::isOpen($date->toDateString())) {
                $errors[] = 'The financial period for ' . $date->format('M Y') . ' is closed.';
            }
        }

        $rate = isset($in['interest_rate']) && $in['interest_rate'] !== '' ? round((float) $in['interest_rate'], 4) : null;
        if ($rate === null || $rate < 0 || $rate > 200) {
            $errors[] = 'Enter the new interest rate (% per year).';
        }
        $method = in_array($in['interest_method'] ?? '', ['reducing', 'flat'], true) ? $in['interest_method'] : null;
        if (!$method) {
            $errors[] = 'Choose the interest method.';
        }
        $term = (int) ($in['term_months'] ?? 0);
        if ($term < 1 || $term > 240) {
            $errors[] = 'Enter the new period in months (1–240).';
        }
        $installment = isset($in['installment']) && $in['installment'] !== '' && $in['installment'] !== null ? round((float) $in['installment'], 2) : null;
        if ($installment !== null && $installment <= 0) {
            $errors[] = 'The installment must be more than zero (or leave it blank).';
        }

        // Fees, as for a new loan, on the top-up amount: application = amount, management / insurance = % of the top-up
        $method = fn ($k) => ($in[$k] ?? 'loan') === 'savings' ? 'savings' : 'loan';
        $fees = [
            'application_fee'        => round(max(0, (float) ($in['application_fee_amount'] ?? 0)), 2),
            'application_fee_method' => $method('application_fee_method'),
            'management_fee_rate'    => round(max(0, (float) ($in['management_fee_rate'] ?? 0)), 4),
            'management_fee_method'  => $method('management_fee_method'),
            'insurance_fee_rate'     => round(max(0, (float) ($in['insurance_fee_rate'] ?? 0)), 4),
            'insurance_fee_method'   => $method('insurance_fee_method'),
        ];
        if ($fees['management_fee_rate'] > 100 || $fees['insurance_fee_rate'] > 100) {
            $errors[] = 'Fee rates must be between 0 and 100%.';
        }
        $fees['management_fee'] = round($amount * $fees['management_fee_rate'] / 100, 2);
        $fees['insurance_fee']  = round($amount * $fees['insurance_fee_rate'] / 100, 2);
        $byMethod = fn ($m) => ($fees['application_fee_method'] === $m ? $fees['application_fee'] : 0)
            + ($fees['management_fee_method'] === $m ? $fees['management_fee'] : 0)
            + ($fees['insurance_fee_method'] === $m ? $fees['insurance_fee'] : 0);
        $loanFees    = round($byMethod('loan'), 2);
        $savingsFees = round($byMethod('savings'), 2);
        if ($amount > 0 && $loanFees >= $amount) {
            $errors[] = 'The fees taken from the loan are more than the top-up.';
        }

        $payout = ($in['payout'] ?? '') === 'savings' ? 'savings' : 'account';
        $payoutAccount = null;
        $savingsAccount = null;
        if ($payout === 'savings' || $savingsFees > 0) {
            $savingsAccount = SavingsAccount::with('product')->where('client_id', $loan->client_id)->where('status', 'active')->find($in['savings_account_id'] ?? null);
            if (!$savingsAccount) {
                $errors[] = $payout === 'savings' ? "Choose the member's savings account to pay the top-up into." : "Choose the member's savings account the fees are taken from.";
            } elseif (!$savingsAccount->product?->savings_liability_account_id) {
                $errors[] = "The {$savingsAccount->product->name} product has no savings liability account.";
            } elseif ($savingsFees > 0) {
                // Fees from savings come out after the top-up is paid in (when it is paid to savings)
                $available = (float) $savingsAccount->balance + ($payout === 'savings' ? $amount - $loanFees : 0);
                if ($available + 0.005 < $savingsFees) {
                    $errors[] = 'Not enough in savings for the fees (' . number_format($savingsFees, 2) . '): balance ' . number_format((float) $savingsAccount->balance, 2) . '.';
                }
            }
        }
        if ($payout !== 'savings') {
            $payoutAccount = Account::where('is_payment_source', true)->where('is_active', true)->find($in['payout_account_id'] ?? null);
            if (!$payoutAccount) {
                $errors[] = 'Choose the cash, bank or mobile-money account the top-up is paid from.';
            }
        }

        $receivableId = $loan->product?->receivable_account_id && Account::find($loan->product->receivable_account_id)
            ? (int) $loan->product->receivable_account_id
            : Account::where('account_code', '1101')->value('id');
        if (!$receivableId) {
            $errors[] = 'The loan product has no loan receivable account.';
        }

        if ($errors) {
            throw ValidationException::withMessages(['topup' => $errors]);
        }

        return compact('amount', 'date', 'rate', 'term', 'installment', 'payout', 'fees', 'loanFees', 'savingsFees') + [
            'method' => $in['interest_method'], 'payout_account' => $payoutAccount, 'savings_account' => $savingsAccount, 'receivable_id' => $receivableId,
        ];
    }
}
