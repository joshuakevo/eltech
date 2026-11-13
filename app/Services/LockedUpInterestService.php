<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\LoanActivation;
use App\Models\LoanCorrection;
use App\Models\LoanRepayment;
use App\Models\LockedUpInterestCharge;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Interest charged by hand on Locked-Up loans. These loans carry rate 0 and no schedule, so Run Loans never
 * charges them; staff add a dated charge instead. The charge is added to outstanding_interest with NO journal —
 * like all Locked-Up interest it becomes income (4001) only when recovered (recoveries: principal, then interest).
 * Each charge is a row shown as "Interest charged" on the loan page and statement. Undo: newest charge only,
 * while the recoveries, balance corrections and Locked-Up status are as they were.
 */
class LockedUpInterestService
{
    /** principal × rate % p.a. × days ÷ 365, rounded to 2 dp. */
    public static function calculate(float $principal, float $ratePa, int $days): float
    {
        return round($principal * $ratePa / 100 * $days / 365, 2);
    }

    /** Date the next charge should count from: the last charge's date (or null when none yet). */
    public function nextFromDate(Loan $loan): ?Carbon
    {
        $last = LockedUpInterestCharge::where('loan_id', $loan->id)->orderByDesc('charge_date')->orderByDesc('id')->first();

        return $last?->charge_date->copy();
    }

    /**
     * $in: charge_date (required), amount (optional when from_date + rate given), from_date, rate, principal, notes.
     * Amount typed wins over the calculation.
     */
    public function charge(Loan $loan, array $in): LockedUpInterestCharge
    {
        return DB::transaction(function () use ($loan, $in) {
            $loan = Loan::with('product')->whereKey($loan->id)->lockForUpdate()->firstOrFail();
            $fail = fn ($m) => throw ValidationException::withMessages(['charge' => $m]);

            if (!$loan->isLockedUp()) {
                $fail('Interest can only be charged by hand on Locked-Up loans. Other loans are charged by Run Loans.');
            }
            if ($loan->status === 'closed') {
                $fail('This loan is closed.');
            }

            $date = Carbon::parse($in['charge_date'])->startOfDay();
            if ($date->gt(today())) {
                $fail('The charge date cannot be in the future.');
            }
            $from = !empty($in['from_date']) ? Carbon::parse($in['from_date'])->startOfDay() : null;
            if ($from && $from->gte($date)) {
                $fail('"From" must be before the charge date.');
            }
            $last = $this->nextFromDate($loan);
            if ($from && $last && $from->lt($last)) {
                $fail('Interest is already charged up to ' . $last->format('d M Y') . '. Start the period on or after that date.');
            }

            $rate      = isset($in['rate']) && $in['rate'] !== '' ? round((float) $in['rate'], 4) : null;
            $principal = isset($in['principal']) && $in['principal'] !== '' ? round((float) $in['principal'], 2) : round((float) $loan->outstanding_principal, 2);
            $days      = $from ? (int) $from->diffInDays($date) : null;

            $amount = isset($in['amount']) && $in['amount'] !== '' ? round((float) $in['amount'], 2) : null;
            if ($amount === null) {
                if (!$from || $rate === null) {
                    $fail('Enter the amount, or a period and rate to calculate it.');
                }
                $amount = self::calculate($principal, $rate, $days);
            }
            if ($amount <= 0) {
                $fail('The interest to charge must be more than zero.');
            }

            $before = round((float) $loan->outstanding_interest, 2);
            $charge = LockedUpInterestCharge::create([
                'loan_id'         => $loan->id,
                'charge_date'     => $date->toDateString(),
                'from_date'       => $from?->toDateString(),
                'days'            => $days,
                'principal'       => $from ? $principal : null,
                'rate'            => $from ? $rate : null,
                'amount'          => $amount,
                'notes'           => $in['notes'] ?? null,
                'interest_before' => $before,
                'repayment_ids'   => LoanRepayment::where('loan_id', $loan->id)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all(),
                'created_by'      => auth()->id(),
            ]);
            $loan->update(['outstanding_interest' => round($before + $amount, 2)]);

            AuditLog::record('locked_up_interest_charged', "{$loan->loan_number}: {$charge->narration()} — " . number_format($amount, 2)
                . ' added to interest owed (' . number_format($before, 2) . ' → ' . number_format($before + $amount, 2) . '; no journal, income when recovered)'
                . (!empty($in['notes']) ? " — {$in['notes']}" : ''), 'loans');

            return $charge;
        });
    }

    /** Throws if the charge can no longer be undone safely. */
    public function assertUndoable(LockedUpInterestCharge $charge): void
    {
        $fail = fn ($m) => throw ValidationException::withMessages(['charge' => $m]);
        $loan = Loan::with('product')->findOrFail($charge->loan_id);

        $newest = LockedUpInterestCharge::where('loan_id', $charge->loan_id)->orderByDesc('id')->value('id');
        if ((int) $newest !== (int) $charge->id) {
            $fail('Undo the newest interest charge first.');
        }
        if (!$loan->isLockedUp()) {
            $fail('The loan is no longer a Locked-Up loan. Undo the move to active loans first.');
        }
        $now = LoanRepayment::where('loan_id', $charge->loan_id)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        if ($now !== collect($charge->repayment_ids ?? [])->map(fn ($id) => (int) $id)->sort()->values()->all()) {
            $fail('Recoveries have been recorded or reversed since this charge. Reverse those first, or use Correct Balance.');
        }
        if (LoanCorrection::where('loan_id', $charge->loan_id)->where('status', 'applied')->where('created_at', '>', $charge->created_at)->exists()
            || LoanActivation::where('loan_id', $charge->loan_id)->where('status', 'applied')->where('created_at', '>', $charge->created_at)->exists()) {
            $fail('The loan balance was corrected or moved after this charge. Undo that first.');
        }
    }

    public function undo(LockedUpInterestCharge $charge): void
    {
        DB::transaction(function () use ($charge) {
            $charge = LockedUpInterestCharge::whereKey($charge->id)->lockForUpdate()->firstOrFail();
            $this->assertUndoable($charge);
            $loan = Loan::whereKey($charge->loan_id)->lockForUpdate()->firstOrFail();

            $before = round((float) $loan->outstanding_interest, 2);
            $loan->update(['outstanding_interest' => round(max(0, $before - $charge->amount), 2)]);
            $narration = $charge->narration();
            $charge->delete();

            AuditLog::record('locked_up_interest_undone', "{$loan->loan_number}: undid {$narration} — " . number_format($charge->amount, 2)
                . ' taken off interest owed (' . number_format($before, 2) . ' → ' . number_format(max(0, $before - $charge->amount), 2) . ')', 'loans');
        });
    }

    /** True when a manual charge was added after $since (guards other undos that restore interest from a snapshot). */
    public static function chargedSince(int $loanId, $since): bool
    {
        return LockedUpInterestCharge::where('loan_id', $loanId)->where('created_at', '>', $since)->exists();
    }
}
