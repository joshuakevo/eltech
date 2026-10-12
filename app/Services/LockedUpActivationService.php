<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\LoanActivation;
use App\Models\LoanCorrection;
use App\Models\LoanProduct;
use App\Models\LoanRepayment;
use App\Models\LoanRun;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves a Locked-Up loan into the active book so Run Loans picks it up.
 *
 *  1. Principal is transferred between receivables: DR new product receivable / CR Locked-Up
 *     receivable (1104), module `loan_activation`, dated the start date.
 *  2. The loan takes the new product, rate, method and term; it restarts on the start date
 *     (disbursement date = start date, so installments fall on that day each month; principal =
 *     outstanding principal) and becomes `active`.
 *  3. The schedule is built by LoanCorrectionService as at the start date with the current
 *     principal and interest (interest owed = charged, carried — not income), optional fixed
 *     installment; that also switches the loan to day-based interest.
 *  4. Everything is snapshotted: undo (or reversing the journal) restores the Locked-Up loan,
 *     as long as no Run Loans steps or repayments have happened since.
 */
class LockedUpActivationService
{
    public function __construct(protected AccountingService $accounting, protected LoanCorrectionService $corrections) {}

    /** Dry run: activate inside a transaction that is rolled back. */
    public function preview(Loan $loan, array $in): array
    {
        DB::beginTransaction();
        try {
            $activation = $this->activate($loan, $in, $in['reason'] ?? 'Preview');
            $after = Loan::with('schedules', 'product')->find($loan->id);
            $journal = $activation->transaction_id
                ? Transaction::with('lines.account')->find($activation->transaction_id)->lines
                    ->map(fn ($l) => ['code' => $l->account->account_code, 'name' => $l->account->account_name, 'debit' => (float) $l->debit, 'credit' => (float) $l->credit])->all()
                : [];
            return [
                'ok' => true,
                'product' => $after->product->name,
                'principal' => (float) $after->outstanding_principal,
                'interest' => (float) $after->outstanding_interest,
                'installment' => (float) ($after->installment_amount ?: optional($after->schedules->first())->total_due),
                'maturity' => optional($after->maturity_date)->format('d M Y'),
                'journal' => $journal,
                'schedule' => $after->schedules->sortBy('installment_no')->map(fn ($s) => [
                    'no' => $s->installment_no, 'due' => Carbon::parse($s->due_date)->format('d M Y'),
                    'principal' => (float) $s->principal_due, 'interest' => (float) $s->interest_due, 'total' => (float) $s->total_due, 'balance' => (float) $s->balance_after,
                ])->values()->all(),
            ];
        } catch (ValidationException $e) {
            return ['ok' => false, 'errors' => collect($e->errors())->flatten()->values()->all()];
        } finally {
            DB::rollBack();
        }
    }

    public function activate(Loan $loan, array $in, string $reason): LoanActivation
    {
        return DB::transaction(function () use ($loan, $in, $reason) {
            $loan = Loan::with('product')->whereKey($loan->id)->lockForUpdate()->firstOrFail();
            [$product, $start, $rate, $method, $term, $installment] = $this->validate($loan, $in);

            $principal = round((float) $loan->outstanding_principal, 2);
            $interest  = round((float) $loan->outstanding_interest, 2);
            $fromProduct = $loan->product;

            $activation = LoanActivation::create([
                'loan_id' => $loan->id, 'from_product_id' => $loan->loan_product_id, 'to_product_id' => $product->id,
                'start_date' => $start->toDateString(), 'interest_rate' => $rate, 'interest_method' => $method, 'term_months' => $term,
                'installment_amount' => $installment, 'principal' => $principal, 'interest' => $interest, 'reason' => $reason,
                'snapshot' => [
                    'loan' => collect($loan->getAttributes())->only(['loan_product_id', 'principal', 'interest_rate', 'interest_method', 'repayment_frequency',
                        'term_months', 'disbursement_date', 'maturity_date', 'outstanding_principal', 'outstanding_interest', 'outstanding_penalty',
                        'status', 'interest_accrued_to', 'interest_carried', 'installment_amount', 'notes'])->all(),
                    'repayment_ids' => LoanRepayment::where('loan_id', $loan->id)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all(),
                ],
                'created_by' => auth()->id(),
            ]);

            // 1. Move the principal to the new product's receivable
            if ($principal > 0) {
                $desc = "Locked-Up loan moved to {$product->name} - {$loan->loan_number}";
                $tx = $this->accounting->post($start->toDateString(), $desc, [
                    ['account_id' => $product->receivable_account_id, 'debit' => $principal, 'credit' => 0, 'client_id' => $loan->client_id, 'description' => $desc],
                    ['account_id' => $fromProduct->receivable_account_id, 'debit' => 0, 'credit' => $principal, 'client_id' => $loan->client_id, 'description' => $desc],
                ], 'loan_activation', $activation->id);
                $activation->update(['transaction_id' => $tx->id]);
            }

            // 2. New terms; the loan restarts on the start date
            $loan->update([
                'loan_product_id' => $product->id, 'principal' => $principal, 'interest_rate' => $rate, 'interest_method' => $method,
                'repayment_frequency' => 'monthly', 'term_months' => $term,
                'disbursement_date' => $start->toDateString(), 'maturity_date' => $start->copy()->addMonthsNoOverflow($term)->toDateString(),
                'status' => 'active', 'interest_accrued_to' => null, 'interest_carried' => 0, 'installment_amount' => null,
                'notes' => trim(($loan->notes ? $loan->notes . "\n" : '') . "Moved from Locked-Up Loans to {$product->name} on {$start->format('d/m/Y')} (was disbursed " . ($loan->getRawOriginal('disbursement_date') ?: '—') . ').'),
            ]);

            // 3. Schedule + day-based interest from the start date (no journal: principal unchanged)
            $correction = $this->corrections->apply($loan->fresh(), [
                'as_at_date' => $start->toDateString(), 'principal' => $principal, 'interest' => $interest,
                'installment' => $installment, 'no_journal' => true,
            ], "Moved from Locked-Up Loans to {$product->name}: {$reason}");
            $activation->update(['correction_id' => $correction->id]);

            AuditLog::record('loan_activated', "Moved {$loan->loan_number} from Locked-Up Loans to {$product->name} from {$start->toDateString()}: "
                . "principal " . number_format($principal, 2) . ", interest " . number_format($interest, 2) . ", {$rate}% {$method}, {$term} months"
                . ($installment ? ', installment ' . number_format($installment, 2) : '') . ". Reason: {$reason}", 'loans');

            return $activation;
        });
    }

    public function assertUndoable(LoanActivation $activation): void
    {
        $fail = fn ($m) => throw ValidationException::withMessages(['activation' => $m]);
        if ($activation->status !== 'applied') {
            $fail('This move has already been undone.');
        }
        if (LoanRun::where('loan_id', $activation->loan_id)->where('status', 'applied')->where('created_at', '>=', $activation->created_at)->exists()) {
            $fail('Run Loans has processed this loan since it was moved. Undo those runs first.');
        }
        $now = LoanRepayment::where('loan_id', $activation->loan_id)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $then = collect($activation->snapshot['repayment_ids'] ?? [])->map(fn ($id) => (int) $id)->sort()->values()->all();
        if ($now !== $then) {
            $fail('Repayments have been added or removed since the loan was moved. Reverse those first.');
        }
        if (LoanCorrection::where('loan_id', $activation->loan_id)->where('status', 'applied')->where('id', '>', (int) $activation->correction_id)->exists()) {
            $fail('A later balance correction exists on this loan. Undo that first.');
        }
    }

    /** Restore the Locked-Up loan. $reverseJournal = false when called from a journal reversal/delete. */
    public function undo(LoanActivation $activation, bool $reverseJournal = true): void
    {
        DB::transaction(function () use ($activation, $reverseJournal) {
            $activation = LoanActivation::whereKey($activation->id)->lockForUpdate()->firstOrFail();
            $this->assertUndoable($activation);
            $loan = Loan::whereKey($activation->loan_id)->lockForUpdate()->firstOrFail();

            if ($activation->correction_id && ($c = LoanCorrection::find($activation->correction_id)) && $c->status === 'applied') {
                $this->corrections->undo($c, false);
            }
            $loan->schedules()->delete();
            $loan->forceFill($activation->snapshot['loan'])->save();

            if ($reverseJournal && $activation->transaction_id) {
                $tx = Transaction::with('lines')->find($activation->transaction_id);
                if ($tx && !$tx->reversed_by) {
                    $date = now()->toDateString();
                    $reversal = $this->accounting->post($date, "REVERSAL of {$tx->reference}: Undo move from Locked-Up Loans",
                        $tx->lines->map(fn ($l) => ['account_id' => $l->account_id, 'debit' => $l->credit, 'credit' => $l->debit, 'client_id' => $l->client_id, 'description' => 'Reversal: ' . $l->description])->all(),
                        'reversal', $tx->id);
                    $reversal->update(['reversal_of' => $tx->id, 'reversal_reason' => 'Undo move from Locked-Up Loans']);
                    $tx->update(['reversed_by' => $reversal->id]);
                }
            }

            $activation->update(['status' => 'reversed', 'reversed_at' => now(), 'reversed_by' => auth()->id()]);
            AuditLog::record('loan_activation_undone', "Undid move of {$loan->loan_number} from Locked-Up Loans (activation #{$activation->id})", 'loans');
        });
    }

    /** @return array{0: LoanProduct, 1: Carbon, 2: float, 3: string, 4: int, 5: ?float} */
    private function validate(Loan $loan, array $in): array
    {
        $errors = [];
        if (!$loan->isLockedUp()) {
            $errors[] = 'Only Locked-Up loans can be moved to active loans.';
        }
        if ($loan->status === 'closed') {
            $errors[] = 'This loan is closed.';
        }
        if ((float) $loan->outstanding_principal + (float) $loan->outstanding_interest < 0.01) {
            $errors[] = 'Nothing is owed on this loan.';
        }
        $product = LoanProduct::find($in['loan_product_id'] ?? null);
        if (!$product || $product->name === 'Locked-Up Loans') {
            $errors[] = 'Choose the loan product to move it to.';
        } elseif (!$product->receivable_account_id || !Account::find($product->receivable_account_id)) {
            $errors[] = "The {$product->name} product has no loan receivable account.";
        }
        if (!optional($loan->product)->receivable_account_id) {
            $errors[] = 'The Locked-Up Loans product has no receivable account.';
        }
        $start = !empty($in['start_date']) ? Carbon::parse($in['start_date'])->startOfDay() : null;
        if (!$start) {
            $errors[] = 'Choose the start date.';
        } else {
            if ($start->gt(today())) {
                $errors[] = 'The start date cannot be in the future.';
            }
            $lastPaid = LoanRepayment::where('loan_id', $loan->id)->max('payment_date');
            if ($lastPaid && $start->lt(Carbon::parse($lastPaid)->startOfDay())) {
                $errors[] = 'The start date cannot be before the last recovery (' . Carbon::parse($lastPaid)->format('d M Y') . ').';
            }
            if ($loan->disbursement_date && $start->lt($loan->disbursement_date->copy()->startOfDay())) {
                $errors[] = 'The start date cannot be before the loan was transferred (' . $loan->disbursement_date->format('d M Y') . ').';
            }
            if (!\App\Models\FinancialPeriod::isOpen($start->toDateString())) {
                $errors[] = 'The financial period for ' . $start->format('M Y') . ' is closed.';
            }
        }
        $rate = isset($in['interest_rate']) && $in['interest_rate'] !== '' ? round((float) $in['interest_rate'], 4) : null;
        if ($rate === null || $rate < 0 || $rate > 200) {
            $errors[] = 'Enter the interest rate (% per year).';
        }
        $method = in_array($in['interest_method'] ?? '', ['reducing', 'flat'], true) ? $in['interest_method'] : null;
        if (!$method) {
            $errors[] = 'Choose the interest method.';
        }
        $term = (int) ($in['term_months'] ?? 0);
        if ($term < 1 || $term > 240) {
            $errors[] = 'Enter the number of monthly installments (1–240).';
        }
        $installment = isset($in['installment']) && $in['installment'] !== '' && $in['installment'] !== null ? round((float) $in['installment'], 2) : null;
        if ($installment !== null && $installment <= 0) {
            $errors[] = 'The installment must be more than zero (or leave it blank).';
        }
        if ($errors) {
            throw ValidationException::withMessages(['activation' => $errors]);
        }
        return [$product, $start, $rate, $method, $term, $installment];
    }
}
