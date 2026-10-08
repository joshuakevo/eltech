<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\LoanCorrection;
use App\Models\LoanRepayment;
use App\Models\LoanSchedule;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Corrects a loan's outstanding principal / interest as at a date (typically the
 * 31/07/2026 transfer date) and rebuilds its schedule:
 *
 *  1. Installments are regenerated from the as-at date to the existing maturity,
 *     on the loan's own due-day pattern, rate and interest method. The corrected
 *     principal is amortized over them; the corrected interest is spread on the
 *     natural interest curve (any excess over it is arrears, due on the first
 *     installment).
 *  2. Repayments dated after the as-at date are replayed onto the new installments
 *     (repayment records themselves are not changed), so paid/partial/overdue
 *     statuses are right and current balances = corrected − paid since.
 *  3. The principal difference is posted to the loan receivable account against the
 *     offset account (default 3004 Opening Balance Equity). Interest is recognised
 *     only when received, so an interest correction needs no journal.
 *  4. The previous loan figures + schedule are snapshotted so the correction can be
 *     undone exactly (also when its journal is reversed or deleted).
 *
 * With an installment amount, every installment is that amount (interest -- arrears first --
 * then principal) and the last one takes whatever is left; Run Loans recovers that amount and
 * keeps charging interest on anything unpaid after maturity. Without one, the installment is
 * worked out so the corrected balance clears by maturity.
 */
class LoanCorrectionService
{
    public const DEFAULT_OFFSET_CODE = '3004';
    public const OFFSET_CODES        = ['3004', '3002'];

    public function __construct(protected AccountingService $accounting) {}

    public function offsetAccounts()
    {
        return Account::whereIn('account_code', self::OFFSET_CODES)->orderBy('account_code')->get(['id', 'account_code', 'account_name']);
    }

    /**
     * Works out the full correction without writing anything.
     *
     * @param array{as_at_date:string, principal:float|string, interest:float|string|null, installment?:float|string|null, journal_date?:string, offset_account_id?:int, no_journal?:bool} $in
     *
     * no_journal: loan figures only — for when the GL already shows the right balance and only the
     * loan record drifted (e.g. a repayment journal was reversed without unwinding the loan).
     */
    public function build(Loan $loan, array $in): array
    {
        $loan->loadMissing('product', 'schedules', 'repayments');
        $errors   = [];
        $warnings = [];
        $today    = now()->startOfDay();

        $asAt      = Carbon::parse($in['as_at_date'])->startOfDay();
        $principal = round((float) $in['principal'], 2);
        $interestIn = ($in['interest'] ?? '') === '' || $in['interest'] === null ? null : round((float) $in['interest'], 2);
        $installment = ($in['installment'] ?? '') === '' || $in['installment'] === null ? null : round((float) $in['installment'], 2);

        if ($loan->status === 'pending' || !$loan->disbursement_date) {
            $errors[] = 'This loan has not been disbursed yet.';
        }
        if ($loan->disbursement_date && $asAt->lt($loan->disbursement_date->copy()->startOfDay())) {
            $errors[] = 'The as-at date cannot be before the disbursement date (' . $loan->disbursement_date->format('d M Y') . ').';
        }
        if ($asAt->gt($today)) {
            $errors[] = 'The as-at date cannot be in the future.';
        }
        if ($principal < 0 || ($interestIn !== null && $interestIn < 0)) {
            $errors[] = 'Principal and interest cannot be negative.';
        }
        if ($installment !== null && $installment <= 0) {
            $errors[] = 'The installment must be more than zero (or leave it blank to fit the balance to maturity).';
        }

        // ── 1. New installments ─────────────────────────────────────────────
        $lockedUp = $loan->isLockedUp();
        $rows = [];
        $naturalInterest = 0;
        if (!$lockedUp && $loan->disbursement_date) {
            [$rows, $naturalInterest] = $this->amortize($loan, $asAt, $principal);
        }
        $interest = $interestIn ?? round($naturalInterest, 2);
        if ($rows && $installment !== null && $installment > 0) {
            $this->fixedInstallment($loan, $rows, $asAt, $principal, $interest, $installment);
            $last = end($rows);
            if (count($rows) > 1 && $rows[0]['principal_due'] < 0.01) {
                $warnings[] = 'An installment of ' . number_format($installment, 0) . ' does not cover the interest, so no principal is repaid until the last installment.';
            }
            if ($last['total_due'] - $installment > 0.5) {
                $warnings[] = 'The last installment (' . Carbon::parse($last['due_date'])->format('d M Y') . ') is ' . number_format($last['total_due'], 0)
                    . ': it takes the balance the installment leaves. Anything unpaid then stays overdue and keeps being charged interest.';
            }
        } elseif ($rows) {
            $installment = null;
            $this->spreadInterest($rows, $interest, $naturalInterest);
        }

        // ── 2. Replay repayments made after the as-at date ──────────────────
        $later = $loan->repayments->filter(fn ($r) => Carbon::parse($r->payment_date)->gt($asAt))
            ->sortBy(fn ($r) => Carbon::parse($r->payment_date)->format('Ymd') . str_pad($r->id, 10, '0', STR_PAD_LEFT))->values();
        $paidP = round($later->sum('principal_paid'), 2);
        $paidI = round($later->sum('interest_paid'), 2);

        if ($paidP - $principal > 0.01) {
            $errors[] = 'Repayments after ' . $asAt->format('d M Y') . ' already cover ' . number_format($paidP, 0)
                . ' of principal — more than the corrected principal of ' . number_format($principal, 0) . '.';
        }
        if ($paidI - $interest > 0.01) {
            $warnings[] = 'Interest repaid after the as-at date (' . number_format($paidI, 0) . ') is more than the corrected interest; outstanding interest will show 0.';
        }

        if ($rows) {
            $poolP = $paidP;
            $poolI = $paidI;
            foreach ($rows as &$row) {
                $row['interest_paid']  = round(min($poolI, $row['interest_due']), 2);
                $poolI                -= $row['interest_paid'];
                $row['principal_paid'] = round(min($poolP, $row['principal_due']), 2);
                $poolP                -= $row['principal_paid'];

                $fullyPaid = $row['principal_due'] - $row['principal_paid'] < 0.01 && $row['interest_due'] - $row['interest_paid'] < 0.01;
                $row['status'] = match (true) {
                    $fullyPaid                                      => 'paid',
                    Carbon::parse($row['due_date'])->lt($today)     => 'overdue',
                    $row['principal_paid'] + $row['interest_paid'] > 0 => 'partial',
                    default                                         => 'pending',
                };
            }
            unset($row);
        }

        $newPrincipal = round(max(0, $principal - $paidP), 2);
        $newInterest  = round(max(0, $interest - $paidI), 2);
        $oldPrincipal = round((float) $loan->outstanding_principal, 2);
        $oldInterest  = round((float) $loan->outstanding_interest, 2);
        $adjustment   = round($newPrincipal - $oldPrincipal, 2);

        // ── 3. GL adjustment for the principal difference ───────────────────
        $receivable = $this->receivableAccount($loan);
        $offset     = !empty($in['offset_account_id'])
            ? Account::whereIn('account_code', self::OFFSET_CODES)->find($in['offset_account_id'])
            : Account::where('account_code', self::DEFAULT_OFFSET_CODE)->first();
        if (abs($adjustment) >= 0.01 && !$offset) {
            $errors[] = 'Offset account ' . self::DEFAULT_OFFSET_CODE . ' (Opening Balance Equity) was not found in the chart of accounts.';
        }
        if (abs($adjustment) >= 0.01 && !$receivable) {
            $errors[] = 'No loan receivable account found for this loan product.';
        }

        $noJournal = filter_var($in['no_journal'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($noJournal) {
            $errors = array_values(array_filter($errors, fn ($e) => !str_contains($e, 'Offset account') && !str_contains($e, 'receivable account')));
            if (abs($adjustment) >= 0.01) {
                $warnings[] = 'No journal will be posted for the ' . number_format(abs($adjustment), 0) . ' principal change. Use this only when the books already show the correct balance.';
            }
        }

        $journal = [];
        if (!$noJournal && abs($adjustment) >= 0.01 && $offset && $receivable) {
            $amt = abs($adjustment);
            $journal = $adjustment > 0
                ? [['account' => $receivable, 'debit' => $amt, 'credit' => 0], ['account' => $offset, 'debit' => 0, 'credit' => $amt]]
                : [['account' => $offset, 'debit' => $amt, 'credit' => 0], ['account' => $receivable, 'debit' => 0, 'credit' => $amt]];
        }

        $journalDate = $in['journal_date'] ?? $asAt->toDateString();
        if ($journal && !\App\Models\FinancialPeriod::isOpen($journalDate)) {
            $errors[] = 'The financial period for the journal date (' . Carbon::parse($journalDate)->format('M Y') . ') is closed. Reopen it under Financial Periods, or choose another journal date.';
        }
        if ($journal && Carbon::parse($journalDate)->gt($today)) {
            $errors[] = 'The journal date cannot be in the future.';
        }

        if (abs($adjustment) < 0.01 && abs($newInterest - $oldInterest) < 0.01 && !$rows) {
            $warnings[] = 'These figures match the loan\'s current balances.';
        }
        if ($lockedUp) {
            $warnings[] = 'Locked-Up Loans have no schedule — only the balances will be corrected.';
        }

        return [
            'as_at'             => $asAt->toDateString(),
            'principal_at_date' => $principal,
            'interest_at_date'  => $interest,
            'natural_interest'  => round($naturalInterest, 2),
            'installment'       => $installment,
            'terms_installment' => $this->termsInstallment($loan),
            'installments'      => $rows,
            'replayed'          => $later->map(fn ($r) => [
                'date' => Carbon::parse($r->payment_date)->toDateString(), 'reference' => $r->reference,
                'principal' => (float) $r->principal_paid, 'interest' => (float) $r->interest_paid,
            ])->values()->all(),
            'paid_after'        => ['principal' => $paidP, 'interest' => $paidI],
            'old'               => ['principal' => $oldPrincipal, 'interest' => $oldInterest],
            'new'               => ['principal' => $newPrincipal, 'interest' => $newInterest],
            'principal_adjustment' => $adjustment,
            'journal_date'      => $journalDate,
            'no_journal'        => $noJournal,
            'offset_account_id' => $offset?->id,
            'journal'           => array_map(fn ($l) => [
                'account_id' => $l['account']->id, 'code' => $l['account']->account_code, 'name' => $l['account']->account_name,
                'debit' => $l['debit'], 'credit' => $l['credit'],
            ], $journal),
            'errors'            => $errors,
            'warnings'          => $warnings,
        ];
    }

    /** Applies a correction: posts the adjustment journal, rebuilds the schedule, updates the loan. */
    public function apply(Loan $loan, array $in, string $reason): LoanCorrection
    {
        return DB::transaction(function () use ($loan, $in, $reason) {
            $loan = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();
            $plan = $this->build($loan, $in);
            if ($plan['errors']) {
                throw ValidationException::withMessages(['correction' => $plan['errors']]);
            }

            $correction = LoanCorrection::create([
                'loan_id'              => $loan->id,
                'as_at_date'           => $plan['as_at'],
                'old_principal'        => $plan['old']['principal'],
                'old_interest'         => $plan['old']['interest'],
                'principal_at_date'    => $plan['principal_at_date'],
                'interest_at_date'     => $plan['interest_at_date'],
                'installment_amount'   => $plan['installment'],
                'new_principal'        => $plan['new']['principal'],
                'new_interest'         => $plan['new']['interest'],
                'principal_adjustment' => $plan['principal_adjustment'],
                'offset_account_id'    => $plan['journal'] ? $plan['offset_account_id'] : null,
                'reason'               => $reason,
                'snapshot'             => [
                    // Raw stored values (casting dates through JSON shifts them by the timezone).
                    'loan'      => collect($loan->getAttributes())->only(['outstanding_principal', 'outstanding_interest', 'outstanding_penalty', 'status',
                        'interest_accrued_to', 'interest_carried', 'installment_amount'])->all(),
                    // Repayments that existed at correction time: undo is only safe while this set is unchanged.
                    'repayment_ids' => LoanRepayment::where('loan_id', $loan->id)->orderBy('id')->pluck('id')->all(),
                    'schedules' => $loan->schedules()->orderBy('installment_no')->get()
                        ->map(fn ($s) => collect($s->getAttributes())->except(['id'])->all())->all(),
                ],
                'created_by'           => auth()->id(),
            ]);

            if ($plan['journal']) {
                $tx = $this->accounting->post(
                    $plan['journal_date'],
                    "Loan balance correction: {$loan->loan_number} as at " . Carbon::parse($plan['as_at'])->format('d/m/Y') . " — {$reason}",
                    array_map(fn ($l) => [
                        'account_id'  => $l['account_id'],
                        'debit'       => $l['debit'],
                        'credit'      => $l['credit'],
                        'description' => "Principal correction - {$loan->loan_number}",
                    ], $plan['journal']),
                    'loan_correction',
                    $correction->id
                );
                $correction->update(['transaction_id' => $tx->id]);
            }

            if (!$loan->isLockedUp()) {
                $loan->schedules()->delete();
                foreach ($plan['installments'] as $row) {
                    LoanSchedule::create([
                        'loan_id'        => $loan->id,
                        'installment_no' => $row['installment_no'],
                        'due_date'       => $row['due_date'],
                        'principal_due'  => $row['principal_due'],
                        'interest_due'   => $row['interest_due'],
                        'total_due'      => $row['total_due'],
                        'balance_after'  => $row['balance_after'],
                        'principal_paid' => $row['principal_paid'],
                        'interest_paid'  => $row['interest_paid'],
                        'status'         => $row['status'],
                    ]);
                }
            }

            $settled = $plan['new']['principal'] < 0.01 && $plan['new']['interest'] < 0.01;
            $status  = $loan->status;
            if ($settled && !$loan->isLockedUp()) {
                $status = 'closed';
            } elseif (!$settled && $status === 'closed') {
                $status = $loan->isLockedUp() ? 'defaulted' : 'active';
            }
            $loan->update([
                'outstanding_principal' => $plan['new']['principal'],
                'outstanding_interest'  => $plan['new']['interest'],
                'status'                => $status,
                'interest_accrued_to'   => null,   // re-based below from the as-at date
            ]);

            // Day-based interest restarts from the as-at date: the corrected interest is what
            // was owing then (carried), and Run Loans charges days from there.
            if (!$loan->isLockedUp() && $status === 'active') {
                app(LoanInterestService::class)->convert($loan->fresh(), Carbon::parse($plan['as_at'])->addDay());
            }

            $this->audit('loan_correction', "Corrected {$loan->loan_number} as at {$plan['as_at']}: principal "
                . number_format($plan['old']['principal'], 2) . ' → ' . number_format($plan['new']['principal'], 2)
                . ', interest ' . number_format($plan['old']['interest'], 2) . ' → ' . number_format($plan['new']['interest'], 2)
                . ($plan['installment'] !== null ? ', installment ' . number_format($plan['installment'], 2) : '')
                . ". Reason: {$reason}");

            return $correction;
        });
    }

    /** Throws if the correction can no longer be undone safely. */
    public function assertUndoable(LoanCorrection $correction): void
    {
        if ($correction->status !== 'applied') {
            throw ValidationException::withMessages(['correction' => 'This correction has already been undone.']);
        }
        $later = LoanCorrection::where('loan_id', $correction->loan_id)->where('status', 'applied')->where('id', '>', $correction->id)->exists();
        if ($later) {
            throw ValidationException::withMessages(['correction' => 'A later correction exists on this loan. Undo that one first.']);
        }
        // Compare as integers: the production driver returns some ids as strings.
        $then = isset($correction->snapshot['repayment_ids'])
            ? collect($correction->snapshot['repayment_ids'])->map(fn ($id) => (int) $id)->sort()->values()->all() : null;
        $now  = LoanRepayment::where('loan_id', $correction->loan_id)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        if ($then === null ? LoanRepayment::where('loan_id', $correction->loan_id)->where('created_at', '>', $correction->created_at)->exists() : $now !== $then) {
            throw ValidationException::withMessages(['correction' => 'Repayments on this loan have been added or removed since this correction. Undo is only possible while the repayments are exactly as they were — reverse those changes first, or post a new correction instead.']);
        }
    }

    /**
     * Restores the loan and schedule from the snapshot. With $reverseJournal, also posts a
     * reversal of the adjustment journal (skip it when called from a journal reversal/delete).
     */
    public function undo(LoanCorrection $correction, bool $reverseJournal = true, ?string $reversalDate = null): void
    {
        DB::transaction(function () use ($correction, $reverseJournal, $reversalDate) {
            $correction = LoanCorrection::whereKey($correction->id)->lockForUpdate()->firstOrFail();
            $this->assertUndoable($correction);
            $loan = Loan::whereKey($correction->loan_id)->lockForUpdate()->firstOrFail();

            if ($reverseJournal && $correction->transaction_id) {
                $tx = Transaction::with('lines')->find($correction->transaction_id);
                if ($tx && !$tx->reversed_by) {
                    $date = $reversalDate ?: now()->toDateString();
                    if (!\App\Models\FinancialPeriod::isOpen($date)) {
                        throw ValidationException::withMessages(['correction' => 'The financial period for ' . Carbon::parse($date)->format('M Y') . ' is closed. Reopen it or choose another date.']);
                    }
                    $reversal = $this->accounting->post(
                        $date,
                        "REVERSAL of {$tx->reference}: Undo loan balance correction",
                        $tx->lines->map(fn ($l) => [
                            'account_id' => $l->account_id, 'debit' => $l->credit, 'credit' => $l->debit,
                            'description' => 'Reversal: ' . $l->description,
                        ])->all(),
                        'reversal',
                        $tx->id
                    );
                    $reversal->update(['reversal_of' => $tx->id, 'reversal_reason' => 'Undo loan balance correction']);
                    $tx->update(['reversed_by' => $reversal->id]);
                }
            }

            $snap = $correction->snapshot;
            $loan->schedules()->delete();
            foreach ($snap['schedules'] ?? [] as $attrs) {
                LoanSchedule::create(collect($attrs)->except(['created_at', 'updated_at'])->all());
            }
            // Older snapshots predate day-based interest: restore those loans to "not converted".
            $loan->forceFill($snap['loan'] + ['interest_accrued_to' => null, 'interest_carried' => 0, 'installment_amount' => null])->save();

            $correction->update(['status' => 'reversed', 'reversed_at' => now(), 'reversed_by' => auth()->id()]);
            $this->audit('loan_correction_undone', "Undid balance correction #{$correction->id} on {$loan->loan_number}");
        });
    }

    /** The installment the loan's own terms give at disbursement (principal, rate, method, term). */
    public function termsInstallment(Loan $loan): ?float
    {
        $step    = ($loan->repayment_frequency ?? 'monthly') === 'quarterly' ? 3 : 1;
        $periods = intdiv((int) $loan->term_months, $step);
        $amount  = (float) $loan->principal;
        if ($periods < 1 || $amount <= 0) {
            return null;
        }
        $r = (float) $loan->interest_rate / 100 * $step / 12;

        if ($loan->interest_method === 'flat') {
            return round($amount / $periods + $amount * $r, 2);
        }
        return round($r == 0 ? $amount / $periods : $amount * $r / (1 - pow(1 + $r, -$periods)), 2);
    }

    // ── Internals ───────────────────────────────────────────────────────────

    /**
     * Installments after $asAt up to maturity on the loan's own due-day pattern, amortizing $principal.
     * @return array{0: array, 1: float} [rows, natural interest]
     */
    private function amortize(Loan $loan, Carbon $asAt, float $principal): array
    {
        $step     = ($loan->repayment_frequency ?? 'monthly') === 'quarterly' ? 3 : 1;
        $anchor   = $loan->disbursement_date->copy()->startOfDay();
        $maturity = $loan->maturity_date?->copy()->startOfDay()
            ?? $anchor->copy()->addMonthsNoOverflow((int) $loan->term_months);

        $dates = [];
        for ($k = 1; $k <= 600; $k++) {
            $d = $anchor->copy()->addMonthsNoOverflow($k * $step);
            if ($d->gt($maturity)) break;
            if ($d->gt($asAt)) $dates[] = $d;
        }
        if (!$dates) {
            // Already matured (or matures before the next due date): one installment on maturity.
            $dates[] = $maturity->gt($asAt) ? $maturity : $maturity->copy();
        }

        $n          = count($dates);
        $rate       = (float) $loan->interest_rate / 100;
        $periodRate = $rate * $step / 12;
        $rows       = [];

        if ($loan->interest_method === 'flat') {
            $perInterest = (float) $loan->principal * $periodRate;
            $perPrincipal = $principal / $n;
            foreach ($dates as $i => $d) {
                $rows[] = ['principal_due' => $perPrincipal, 'natural_interest' => $perInterest];
            }
        } else {
            $installment = $periodRate == 0 ? $principal / $n
                : $principal * ($periodRate * pow(1 + $periodRate, $n)) / (pow(1 + $periodRate, $n) - 1);
            $balance = $principal;
            foreach ($dates as $d) {
                $int = $balance * $periodRate;
                $rows[] = ['principal_due' => $installment - $int, 'natural_interest' => $int];
                $balance -= $installment - $int;
            }
        }

        // Round principal so it sums exactly to $principal.
        $natural = 0;
        $running = $principal;
        $sumP    = 0;
        foreach ($rows as $i => &$row) {
            $p = $i === $n - 1 ? round($principal - $sumP, 2) : round($row['principal_due'], 2);
            $sumP   += $p;
            $running = round($running - $p, 2);
            $natural += $row['natural_interest'];
            $row = [
                'installment_no'   => $i + 1,
                'due_date'         => $dates[$i]->toDateString(),
                'principal_due'    => $p,
                'natural_interest' => $row['natural_interest'],
                'interest_due'     => 0,
                'total_due'        => 0,
                'balance_after'    => max(0, $running),
                'principal_paid'   => 0,
                'interest_paid'    => 0,
                'status'           => 'pending',
            ];
        }
        unset($row);

        return [$rows, $natural];
    }

    /**
     * Fixed installment, as Run Loans will charge and recover it: interest by days on the
     * reducing balance (after the interest owing at the as-at date) is paid first, the rest of
     * the installment is principal, and the last installment takes everything left.
     */
    private function fixedInstallment(Loan $loan, array &$rows, Carbon $asAt, float $principal, float $interest, float $installment): void
    {
        $rate    = (float) $loan->interest_rate / 100;
        $n       = count($rows);
        $balance = $principal;
        $pool    = $interest;
        $prev    = $asAt->copy();

        foreach ($rows as $i => &$row) {
            $due   = Carbon::parse($row['due_date'])->startOfDay();
            $pool += $balance * $rate * max(0, $prev->diffInDays($due, false)) / 365;
            $prev  = $due;
            if ($i === $n - 1) {
                $iDue = $pool;
                $p    = $balance;
            } else {
                $iDue = min($pool, $installment);
                $p    = min($balance, max(0, $installment - $iDue));
            }
            $iDue    = round($iDue, 2);
            $p       = round($p, 2);
            $pool   -= $iDue;
            $balance = round($balance - $p, 2);

            $row['principal_due'] = $p;
            $row['interest_due']  = $iDue;
            $row['total_due']     = round($p + $iDue, 2);
            $row['balance_after'] = max(0, $balance);
            unset($row['natural_interest']);
        }
        unset($row);
    }

    /**
     * Sets interest_due so the installments sum exactly to $interest: on the natural curve,
     * with any excess (arrears at the as-at date) due on the first installment.
     */
    private function spreadInterest(array &$rows, float $interest, float $natural): void
    {
        $n = count($rows);
        if ($natural <= 0.0001) {
            $weights = array_fill(0, $n, 1 / $n);
            $alloc = array_map(fn ($w) => $interest * $w, $weights);
        } elseif ($interest >= $natural) {
            $alloc = array_column($rows, 'natural_interest');
            $alloc[0] += $interest - $natural;
        } else {
            $alloc = array_map(fn ($r) => $r['natural_interest'] / $natural * $interest, $rows);
        }

        $sum = 0;
        foreach ($rows as $i => &$row) {
            $iDue = $i === $n - 1 ? round($interest - $sum, 2) : round($alloc[$i], 2);
            $sum += $iDue;
            $row['interest_due'] = $iDue;
            $row['total_due']    = round($row['principal_due'] + $iDue, 2);
            unset($row['natural_interest']);
        }
        unset($row);
    }

    private function receivableAccount(Loan $loan): ?Account
    {
        $id = $loan->product?->receivable_account_id;
        return ($id ? Account::find($id) : null) ?? Account::where('account_code', '1101')->first();
    }

    private function audit(string $event, string $description): void
    {
        AuditLog::record($event, $description, 'loans');
    }
}
