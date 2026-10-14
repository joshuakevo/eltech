<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\LoanLockup;
use App\Models\LoanProduct;
use App\Models\LoanRepayment;
use App\Models\LoanRun;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Four-eyes lock-up of a normal loan.
 *
 *  - request(): anyone with "initiate loan lockup" asks for a loan to be locked up (date + reason).
 *  - approve(): one approval is needed from each approver role (setting lockup_approver_roles,
 *    default ceo_manager,credit_manager) — two different people, neither the initiator.
 *    The last approval executes the lock-up.
 *  - reject(): any approver can reject (with a reason); the initiator can cancel while pending.
 *  - execute(): interest is charged by days to the lock-up date (kept as interest owed — not income),
 *    principal moves DR Locked-Up receivable (1104) / CR product receivable (module loan_lockup),
 *    the loan becomes a Locked-Up loan (rate 0, no schedule, status defaulted), raw snapshot kept.
 *  - undo(): restores the loan and reverses the journal, while no recoveries have been made since.
 */
class LoanLockupService
{
    public function __construct(protected AccountingService $accounting, protected LoanInterestService $interest) {}

    /** Approver roles, each of which must approve once (role name => label). */
    public function approverRoles(): array
    {
        $names = array_filter(array_map('trim', explode(',', (string) SystemSetting::get('lockup_approver_roles', 'ceo_manager,credit_manager'))));
        return collect($names)->mapWithKeys(fn ($r) => [$r => ucfirst(str_replace('_', ' ', $r))])->all();
    }

    public function pending(Loan $loan): ?LoanLockup
    {
        return LoanLockup::where('loan_id', $loan->id)->where('status', 'pending')->latest('id')->first();
    }

    /** Approver roles this user can still approve for on this request. */
    public function rolesUserCanApprove(LoanLockup $lockup, User $user): array
    {
        if ($lockup->status !== 'pending' || (int) $lockup->requested_by === (int) $user->id) {
            return [];
        }
        $approvals = collect($lockup->approvals ?? []);
        if ($approvals->contains(fn ($a) => (int) $a['user_id'] === (int) $user->id)) {
            return [];   // one person approves once
        }
        $done = $approvals->pluck('role')->all();
        return collect($this->approverRoles())->keys()->filter(fn ($r) => !in_array($r, $done, true) && $user->hasRole($r))->values()->all();
    }

    public function request(Loan $loan, string $date, string $reason): LoanLockup
    {
        return DB::transaction(function () use ($loan, $date, $reason) {
            $loan = Loan::with('product')->whereKey($loan->id)->lockForUpdate()->firstOrFail();
            $d = Carbon::parse($date)->startOfDay();
            $errors = [];
            if ($loan->isLockedUp()) {
                $errors[] = 'This loan is already a Locked-Up loan.';
            }
            if (!in_array($loan->status, ['active', 'defaulted'], true)) {
                $errors[] = 'Only running loans can be locked up.';
            }
            if ($this->pending($loan)) {
                $errors[] = 'A lock-up request for this loan is already waiting for approval.';
            }
            if ($d->gt(today())) {
                $errors[] = 'The lock-up date cannot be in the future.';
            }
            $lastPaid = LoanRepayment::where('loan_id', $loan->id)->max('payment_date');
            if ($lastPaid && $d->lt(Carbon::parse($lastPaid)->startOfDay())) {
                $errors[] = 'The lock-up date cannot be before the last repayment (' . Carbon::parse($lastPaid)->format('d M Y') . ').';
            }
            if (!LoanProduct::where('name', 'Locked-Up Loans')->value('receivable_account_id')) {
                $errors[] = 'The Locked-Up Loans product (with a receivable account) was not found.';
            }
            if ($errors) {
                throw ValidationException::withMessages(['lockup' => $errors]);
            }

            $lockup = LoanLockup::create([
                'loan_id' => $loan->id, 'lockup_date' => $d->toDateString(), 'reason' => $reason,
                'status' => 'pending', 'requested_by' => auth()->id(), 'approvals' => [],
            ]);
            AuditLog::record('loan_lockup_requested', "Requested lock-up of {$loan->loan_number} as at {$d->toDateString()}: {$reason}", 'loans');
            return $lockup;
        });
    }

    public function approve(LoanLockup $lockup, User $user, ?string $note = null): LoanLockup
    {
        return DB::transaction(function () use ($lockup, $user, $note) {
            $lockup = LoanLockup::whereKey($lockup->id)->lockForUpdate()->firstOrFail();
            $roles = $this->rolesUserCanApprove($lockup, $user);
            if (!$roles) {
                throw ValidationException::withMessages(['lockup' => $lockup->status !== 'pending'
                    ? 'This request is no longer waiting for approval.'
                    : ((int) $lockup->requested_by === (int) $user->id ? 'You started this request, so you cannot approve it.' : 'You cannot approve this request (already approved by you, or you do not hold an approver role that is still needed).')]);
            }
            $approvals = $lockup->approvals ?? [];
            $approvals[] = ['user_id' => $user->id, 'name' => $user->name, 'role' => $roles[0], 'at' => now()->toDateTimeString(), 'note' => $note];
            $lockup->update(['approvals' => $approvals]);
            $loan = Loan::find($lockup->loan_id);
            AuditLog::record('loan_lockup_approved', "Approved lock-up of {$loan->loan_number} as " . $this->approverRoles()[$roles[0]], 'loans');

            $done = collect($approvals)->pluck('role')->unique();
            if (collect($this->approverRoles())->keys()->every(fn ($r) => $done->contains($r))) {
                $this->execute($lockup->fresh());
            }
            return $lockup->fresh();
        });
    }

    public function reject(LoanLockup $lockup, User $user, string $reason): void
    {
        $isApprover = collect($this->approverRoles())->keys()->contains(fn ($r) => $user->hasRole($r));
        $isRequester = (int) $lockup->requested_by === (int) $user->id;
        if ($lockup->status !== 'pending' || (!$isApprover && !$isRequester)) {
            throw ValidationException::withMessages(['lockup' => 'You cannot reject or cancel this request.']);
        }
        $lockup->update(['status' => $isRequester && !$isApprover ? 'cancelled' : 'rejected', 'rejected_by' => $user->id, 'rejected_at' => now(), 'rejection_reason' => $reason]);
        $loan = Loan::find($lockup->loan_id);
        AuditLog::record('loan_lockup_' . $lockup->status, ucfirst($lockup->status) . " lock-up request for {$loan->loan_number}: {$reason}", 'loans');
    }

    /** Move the loan to Locked-Up Loans (called on the last approval). */
    private function execute(LoanLockup $lockup): void
    {
        $loan = Loan::with('product', 'schedules')->whereKey($lockup->loan_id)->lockForUpdate()->firstOrFail();
        $date = $lockup->lockup_date->copy()->startOfDay();
        if (!\App\Models\FinancialPeriod::isOpen($date->toDateString())) {
            throw ValidationException::withMessages(['lockup' => 'The financial period for ' . $date->format('M Y') . ' is closed — reopen it to complete this lock-up.']);
        }
        $locked = LoanProduct::where('name', 'Locked-Up Loans')->firstOrFail();
        $from = $loan->product;

        $snapshot = [
            'loan' => collect($loan->getAttributes())->only(['loan_product_id', 'principal', 'interest_rate', 'interest_method', 'repayment_frequency',
                'term_months', 'disbursement_date', 'maturity_date', 'outstanding_principal', 'outstanding_interest', 'outstanding_penalty',
                'status', 'interest_accrued_to', 'interest_carried', 'installment_amount'])->all(),
            'schedules' => $loan->schedules()->orderBy('installment_no')->get()->map(fn ($s) => collect($s->getAttributes())->except(['id'])->all())->all(),
            'repayment_ids' => LoanRepayment::where('loan_id', $loan->id)->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'charge_ids' => [],
        ];

        // Interest by days to the lock-up date (no journal — income only when recovered)
        if (!$loan->isDayBasedInterest() && $loan->disbursement_date && $loan->schedules()->exists()) {
            $this->interest->convert($loan, $date);
            $loan->refresh();
        }
        if ($loan->isDayBasedInterest()) {
            $before = \App\Models\LoanInterestCharge::where('loan_id', $loan->id)->max('id');
            $this->interest->chargeForSettlement($loan, $date);
            $snapshot['charge_ids'] = \App\Models\LoanInterestCharge::where('loan_id', $loan->id)->where('id', '>', (int) $before)->pluck('id')->all();
            $loan->refresh();
        }
        $principal = round((float) $loan->outstanding_principal, 2);
        $interest  = round(max(0, (float) $loan->outstanding_interest), 2);

        $tx = null;
        if ($principal > 0) {
            $desc = "Loan locked up - {$loan->loan_number}";
            $tx = $this->accounting->post($date->toDateString(), $desc, [
                ['account_id' => $locked->receivable_account_id, 'debit' => $principal, 'credit' => 0, 'client_id' => $loan->client_id, 'description' => $desc],
                ['account_id' => $from->receivable_account_id ?: \App\Models\Account::where('account_code', '1101')->value('id'), 'debit' => 0, 'credit' => $principal, 'client_id' => $loan->client_id, 'description' => $desc],
            ], 'loan_lockup', $lockup->id);
        }

        $loan->schedules()->delete();
        $loan->forceFill([
            'loan_product_id' => $locked->id, 'interest_rate' => 0, 'status' => 'defaulted',
            'outstanding_principal' => $principal, 'outstanding_interest' => $interest,
            'interest_accrued_to' => null, 'interest_carried' => 0, 'installment_amount' => null,
        ])->save();

        $lockup->update([
            'status' => 'executed', 'executed_at' => now(), 'principal' => $principal, 'interest' => $interest,
            'from_product_id' => $from?->id, 'transaction_id' => $tx?->id, 'snapshot' => $snapshot,
        ]);
        AuditLog::record('loan_locked_up', "Locked up {$loan->loan_number} as at {$date->toDateString()}: principal " . number_format($principal, 2)
            . ' moved to Locked-Up Loans, interest owed ' . number_format($interest, 2), 'loans');
    }

    public function assertUndoable(LoanLockup $lockup): void
    {
        if ($lockup->status !== 'executed') {
            throw ValidationException::withMessages(['lockup' => 'Only a completed lock-up can be undone.']);
        }
        $now = LoanRepayment::where('loan_id', $lockup->loan_id)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $then = collect($lockup->snapshot['repayment_ids'] ?? [])->map(fn ($id) => (int) $id)->sort()->values()->all();
        if ($now !== $then) {
            throw ValidationException::withMessages(['lockup' => 'Recoveries have been recorded since the loan was locked up. Reverse those first.']);
        }
        if (\App\Models\LoanActivation::where('loan_id', $lockup->loan_id)->where('status', 'applied')->where('created_at', '>', $lockup->executed_at)->exists()) {
            throw ValidationException::withMessages(['lockup' => 'The loan has since been moved back to active loans. Undo that first.']);
        }
    }

    /** Restore the loan as it was. $reverseJournal = false when called from a journal reversal/delete. */
    public function undo(LoanLockup $lockup, bool $reverseJournal = true): void
    {
        DB::transaction(function () use ($lockup, $reverseJournal) {
            $lockup = LoanLockup::whereKey($lockup->id)->lockForUpdate()->firstOrFail();
            $this->assertUndoable($lockup);
            $loan = Loan::whereKey($lockup->loan_id)->lockForUpdate()->firstOrFail();

            \App\Models\LoanInterestCharge::whereIn('id', $lockup->snapshot['charge_ids'] ?? [])->delete();
            $loan->schedules()->delete();
            foreach ($lockup->snapshot['schedules'] ?? [] as $attrs) {
                \App\Models\LoanSchedule::create(collect($attrs)->except(['created_at', 'updated_at'])->all());
            }
            $loan->forceFill($lockup->snapshot['loan'])->save();

            if ($reverseJournal && $lockup->transaction_id) {
                $tx = Transaction::with('lines')->find($lockup->transaction_id);
                if ($tx && !$tx->reversed_by) {
                    $reversal = $this->accounting->post(now()->toDateString(), "REVERSAL of {$tx->reference}: Undo loan lock-up",
                        $tx->lines->map(fn ($l) => ['account_id' => $l->account_id, 'debit' => $l->credit, 'credit' => $l->debit, 'client_id' => $l->client_id, 'description' => 'Reversal: ' . $l->description])->all(),
                        'reversal', $tx->id);
                    $reversal->update(['reversal_of' => $tx->id, 'reversal_reason' => 'Undo loan lock-up']);
                    $tx->update(['reversed_by' => $reversal->id]);
                }
            }
            $lockup->update(['status' => 'reversed', 'reversed_at' => now(), 'reversed_by' => auth()->id()]);
            AuditLog::record('loan_lockup_undone', "Undid lock-up of {$loan->loan_number} (request #{$lockup->id})", 'loans');
        });
    }
}
