<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanProvision;
use App\Models\LoanProvisionLine;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Loan loss provisioning. Both types exclude Locked-Up Loans.
 *
 * General  = rate (default 1%) × outstanding principal as at a date, all loans.
 *            GL: DR 5120 / CR 1110 (write-back reverses). Sized against the 1110 GL balance.
 * Specific = loans in arrears as at a date:
 *              91–180 days → 25%, 181–365 days → 50% of outstanding principal
 *              over 365 days → 100% of outstanding principal (old system's bands);
 *              arrears brought forward from the old system (loans.arrears_bf_*) count from their old start
 *            GL: DR 5119 / CR 1109 (write-back reverses). Sized against the balance built
 *            up by previous specific runs only — the old-system opening balance in 1109
 *            (which covers the Locked-Up book) is left untouched.
 *
 * Each run posts only the movement needed to reach the required level.
 */
class LoanProvisionService
{
    public const GENERAL_PROVISION_ACCOUNT  = '1110';
    public const GENERAL_EXPENSE_ACCOUNT    = '5120';
    public const SPECIFIC_PROVISION_ACCOUNT = '1109';
    public const SPECIFIC_EXPENSE_ACCOUNT   = '5119';
    public const DEFAULT_GENERAL_RATE       = 1.0;

    /** Specific provision bands: [min days in arrears (inclusive), rate %, label]. Highest first. */
    public const SPECIFIC_BANDS = [
        [366, 100.0, 'Over 365 days'],
        [181, 50.0,  '181 – 365 days'],
        [91,  25.0,  '91 – 180 days'],
    ];   // as the old system's provision report (user decision 19/11/2026)

    public function __construct(protected AccountingService $accountingService)
    {
    }

    /**
     * Outstanding principal per loan as at $asAt (end of day), excluding Locked-Up Loans.
     *
     * Rolled back from today's balance: current outstanding_principal plus principal
     * repaid after $asAt. This keeps imported loans (whose history pre-dates the system
     * and has no repayment rows) correct, since their stored balance is authoritative.
     */
    public function outstandingAsAt(string $asAt): Collection
    {
        $lockedUpProductId = LoanProduct::where('name', 'Locked-Up Loans')->value('id');

        // Locked-Up is judged as at the date (user decision 19/11/2026): a loan locked up AFTER the date was a normal
        // loan then (included, with its schedule from the lock-up snapshot); a loan moved from Locked-Up to active
        // AFTER the date was Locked-Up then (excluded).
        $lockedLater = \App\Models\LoanLockup::where('status', 'executed')->whereDate('lockup_date', '>', $asAt)
            ->orderBy('lockup_date')->get()->keyBy(fn ($l) => (int) $l->loan_id);
        $activatedLater = \App\Models\LoanActivation::where('status', 'applied')->whereDate('start_date', '>', $asAt)
            ->pluck('loan_id')->map(fn ($id) => (int) $id)->all();

        $repaidAfter = DB::table('loan_repayments')
            ->where('payment_date', '>', $asAt)
            ->groupBy('loan_id')
            ->select('loan_id', DB::raw('SUM(principal_paid) as principal_paid'), DB::raw('SUM(principal_paid + interest_paid) as schedule_paid'));

        return Loan::query()
            ->with('client', 'product')
            ->leftJoinSub($repaidAfter, 'ra', 'ra.loan_id', '=', 'loans.id')
            ->whereIn('loans.status', ['active', 'defaulted', 'closed'])
            ->whereNotNull('loans.disbursement_date')
            ->whereDate('loans.disbursement_date', '<=', $asAt)
            ->when($lockedUpProductId, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('loans.loan_product_id', '!=', $lockedUpProductId)
                ->orWhereNull('loans.loan_product_id')
                ->orWhereIn('loans.id', $lockedLater->keys()->all())))
            ->when($activatedLater, fn ($q) => $q->whereNotIn('loans.id', $activatedLater))
            ->select(
                'loans.*',
                DB::raw('loans.outstanding_principal + COALESCE(ra.principal_paid, 0) as outstanding_as_at'),
                DB::raw('COALESCE(ra.schedule_paid, 0) as schedule_paid_after')
            )
            ->orderBy('loans.loan_number')
            ->get()
            ->filter(fn ($loan) => (float) $loan->outstanding_as_at > 0.004)
            ->values()
            ->each(function ($loan) use ($lockedLater, $asAt) {
                $lockup = $lockedLater[(int) $loan->id] ?? null;
                if ($lockup) {
                    // schedule as it was before the lock-up; only payments up to the lock-up are on it
                    $loan->setRelation('schedules', collect($lockup->snapshot['schedules'] ?? [])
                        ->map(fn ($a) => (new \App\Models\LoanSchedule())->forceFill($a))->sortBy('installment_no')->values());
                    $loan->schedule_paid_after = (float) DB::table('loan_repayments')->where('loan_id', $loan->id)
                        ->where('payment_date', '>', $asAt)->where('payment_date', '<=', $lockup->lockup_date->toDateString())
                        ->sum(DB::raw('principal_paid + interest_paid'));
                } else {
                    $loan->load('schedules');
                }
            });
    }

    /**
     * Oldest installment due on/before $asAt that was not fully paid as at $asAt, or null.
     *
     * Schedule paid amounts reflect today, so principal+interest repaid after $asAt is
     * peeled back off the most recently paid installments (allocation runs oldest-first,
     * so later payments sit on the latest installments).
     */
    public function oldestArrearsDate(Loan $loan, string $asAt): ?Carbon
    {
        $schedules = $loan->schedules->values();
        $paid      = $schedules->map(fn ($s) => (float) $s->principal_paid + (float) $s->interest_paid)->all();

        $unwind = (float) $loan->schedule_paid_after;
        for ($i = count($paid) - 1; $i >= 0 && $unwind > 0.004; $i--) {
            $take      = min($paid[$i], $unwind);
            $paid[$i] -= $take;
            $unwind   -= $take;
        }

        $fromSchedule = null;
        foreach ($schedules as $i => $s) {
            if (Carbon::parse($s->due_date)->toDateString() > $asAt) {
                break;
            }
            $due = (float) $s->principal_due + (float) $s->interest_due;
            if ($due - $paid[$i] > 1) {   // tolerate rounding residue
                $fromSchedule = Carbon::parse($s->due_date)->startOfDay();
                break;
            }
        }

        $fromOld = $this->arrearsBroughtForward($loan, $asAt);

        return $fromOld && (!$fromSchedule || $fromOld->lt($fromSchedule)) ? $fromOld : $fromSchedule;
    }

    /**
     * Arrears start date from the old system (imported from its provision report), while those arrears are not cleared:
     * principal + interest repaid after the report date up to $asAt is less than the arrears on the report.
     */
    public function arrearsBroughtForward(Loan $loan, string $asAt): ?Carbon
    {
        if (!$loan->arrears_bf_since || $loan->arrears_bf_since->toDateString() > $asAt || (float) $loan->arrears_bf_amount <= 0) {
            return null;
        }
        $paid = (float) DB::table('loan_repayments')->where('loan_id', $loan->id)
            ->where('payment_date', '>', $loan->arrears_bf_as_at->toDateString())->where('payment_date', '<=', $asAt)
            ->sum(DB::raw('principal_paid + interest_paid'));

        return $paid + 1 < (float) $loan->arrears_bf_amount ? $loan->arrears_bf_since->copy()->startOfDay() : null;
    }

    public function preview(string $type, string $asAt, ?float $rate = null): array
    {
        return $type === 'specific' ? $this->previewSpecific($asAt) : $this->previewGeneral($asAt, (float) $rate);
    }

    /**
     * General provision preview (nothing is saved).
     */
    public function previewGeneral(string $asAt, float $rate): array
    {
        $lines = $this->outstandingAsAt($asAt)->map(function ($loan) use ($rate) {
            $outstanding = round((float) $loan->outstanding_as_at, 2);
            return [
                'loan'             => $loan,
                'outstanding'      => $outstanding,
                'rate'             => $rate,
                'provision_amount' => round($outstanding * $rate / 100, 2),
                'days_in_arrears'  => null,
                'arrears_date'     => null,
                'band'             => null,
            ];
        });

        // Required is rate × total (rounded to the system's decimal places) so the GL
        // posting doesn't pick up fractional residue from per-loan rounding.
        $dp       = $this->decimals();
        $total    = round($lines->sum('outstanding'), 2);
        $required = round($total * $rate / 100, $dp);
        $existing = $this->provisionBalance(self::GENERAL_PROVISION_ACCOUNT, $asAt);

        return [
            'type'              => 'general',
            'as_at'             => $asAt,
            'rate'              => $rate,
            'lines'             => $lines,
            'total_outstanding' => $total,
            'required'          => $required,
            'existing'          => $existing,
            'adjustment'        => round($required - $existing, $dp),
        ];
    }

    /**
     * Specific provision preview (nothing is saved): loans in arrears beyond 90 days.
     */
    public function previewSpecific(string $asAt): array
    {
        $loans = $this->outstandingAsAt($asAt);   // schedules loaded (lock-up snapshot for loans locked up later)

        $lines = $loans->map(function ($loan) use ($asAt) {
            $arrearsDate = $this->oldestArrearsDate($loan, $asAt);
            if (!$arrearsDate) {
                return null;
            }
            $days = $arrearsDate->diffInDays(Carbon::parse($asAt)->startOfDay());
            $band = collect(self::SPECIFIC_BANDS)->first(fn ($b) => $days >= $b[0]);
            if (!$band) {
                return null;
            }
            $outstanding = round((float) $loan->outstanding_as_at, 2);
            return [
                'loan'             => $loan,
                'outstanding'      => $outstanding,
                'rate'             => $band[1],
                'provision_amount' => round($outstanding * $band[1] / 100, 2),
                'days_in_arrears'  => $days,
                'arrears_date'     => $arrearsDate->toDateString(),
                'band'             => $band[2],
            ];
        })->filter()->sortByDesc('days_in_arrears')->values();

        $dp       = $this->decimals();
        $required = round($lines->sum(fn ($l) => $l['outstanding'] * $l['rate'] / 100), $dp);
        $existing = $this->specificRunBalance($asAt);

        $bands = collect(self::SPECIFIC_BANDS)->map(function ($b) use ($lines) {
            $inBand = $lines->where('band', $b[2]);
            return [
                'label'       => $b[2],
                'rate'        => $b[1],
                'count'       => $inBand->count(),
                'outstanding' => round($inBand->sum('outstanding'), 2),
                'provision'   => round($inBand->sum('provision_amount'), 2),
            ];
        })->reverse()->values();

        return [
            'type'              => 'specific',
            'as_at'             => $asAt,
            'rate'              => null,
            'lines'             => $lines,
            'bands'             => $bands,
            'total_outstanding' => round($lines->sum('outstanding'), 2),
            'required'          => $required,
            'existing'          => $existing,
            'gl_balance'        => $this->provisionBalance(self::SPECIFIC_PROVISION_ACCOUNT, $asAt),
            'adjustment'        => round($required - $existing, $dp),
        ];
    }

    public function runGeneral(string $asAt, float $rate, ?string $notes = null): LoanProvision
    {
        return $this->run('general', $asAt, $rate, $notes);
    }

    public function runSpecific(string $asAt, ?string $notes = null): LoanProvision
    {
        return $this->run('specific', $asAt, null, $notes);
    }

    /**
     * Save a provision run with its per-loan breakdown and post the GL adjustment.
     */
    protected function run(string $type, string $asAt, ?float $rate, ?string $notes): LoanProvision
    {
        $this->assertCanRun($type, $asAt);

        $provisionAccount = $this->account($type === 'specific' ? self::SPECIFIC_PROVISION_ACCOUNT : self::GENERAL_PROVISION_ACCOUNT);
        $expenseAccount   = $this->account($type === 'specific' ? self::SPECIFIC_EXPENSE_ACCOUNT : self::GENERAL_EXPENSE_ACCOUNT);

        return DB::transaction(function () use ($type, $asAt, $rate, $notes, $provisionAccount, $expenseAccount) {
            $preview = $this->preview($type, $asAt, $rate);

            $run = LoanProvision::create([
                'provision_type'     => $type,
                'as_at_date'         => $asAt,
                'rate'               => $rate ?? 0,   // specific uses per-band rates on each line
                'total_outstanding'  => $preview['total_outstanding'],
                'required_provision' => $preview['required'],
                'previous_provision' => $preview['existing'],
                'adjustment'         => $preview['adjustment'],
                'loan_count'         => $preview['lines']->count(),
                'status'             => 'posted',
                'notes'              => $notes,
                'created_by'         => auth()->id(),
            ]);

            foreach ($preview['lines'] as $line) {
                LoanProvisionLine::create([
                    'loan_provision_id'     => $run->id,
                    'loan_id'               => $line['loan']->id,
                    'client_id'             => $line['loan']->client_id,
                    'days_in_arrears'       => $line['days_in_arrears'],
                    'oldest_arrears_date'   => $line['arrears_date'],
                    'outstanding_principal' => $line['outstanding'],
                    'rate'                  => $line['rate'],
                    'provision_amount'      => $line['provision_amount'],
                ]);
            }

            $adjustment = $preview['adjustment'];
            if (abs($adjustment) >= 0.005) {
                $amount = abs($adjustment);
                $label  = $type === 'specific'
                    ? 'Specific loan provision (arrears over 90 days) as at ' . $asAt
                    : 'General loan provision @ ' . rtrim(rtrim(number_format($rate, 4), '0'), '.') . '% as at ' . $asAt;
                $lines  = $adjustment > 0
                    ? [
                        ['account_id' => $expenseAccount->id,   'debit' => $amount, 'credit' => 0, 'description' => $label . ' — charge'],
                        ['account_id' => $provisionAccount->id, 'debit' => 0, 'credit' => $amount, 'description' => $label . ' — charge'],
                    ]
                    : [
                        ['account_id' => $provisionAccount->id, 'debit' => $amount, 'credit' => 0, 'description' => $label . ' — write-back'],
                        ['account_id' => $expenseAccount->id,   'debit' => 0, 'credit' => $amount, 'description' => $label . ' — write-back'],
                    ];

                $transaction = $this->accountingService->post($asAt, $label, $lines, 'loan_provision', $run->id);
                $run->update(['transaction_id' => $transaction->id]);
            }

            return $run;
        });
    }

    /**
     * Record the old system's specific provision at $asAt as the starting point (user decision 19/11/2026: July as the
     * old system reported it — 113,431,843 at 31/07/2026 — and the new system continues from there). The old system
     * already posted it (its trial balance, brought in as the 1109 opening balance, includes it), so NO journal is posted:
     * the run only records the figure (per loan, as reported) so later runs post the movement from it.
     * $lines: [loan_id, loan_bal, days, old_specific] per report row. Blocked while a specific run on/after $asAt is posted.
     */
    public function setSpecificFromReport(string $asAt, array $lines): LoanProvision
    {
        $clash = LoanProvision::where('provision_type', 'specific')->where('status', 'posted')->whereDate('as_at_date', '>=', $asAt)->orderBy('as_at_date')->first();
        if ($clash) {
            throw ValidationException::withMessages(['as_at_date' => 'A specific provision is already posted as at ' . $clash->as_at_date->format('d/m/Y')
                . '. Reverse it first: the old system figure for ' . Carbon::parse($asAt)->format('d/m/Y') . ' is already in the 1109 opening balance.']);
        }

        return DB::transaction(function () use ($asAt, $lines) {
            $dp       = $this->decimals();
            $provided = collect($lines)->filter(fn ($l) => $l['old_specific'] > 0);
            $required = round($provided->sum('old_specific'), $dp);
            $existing = $this->specificRunBalance($asAt);

            $run = LoanProvision::create([
                'provision_type' => 'specific', 'as_at_date' => $asAt, 'rate' => 0,
                'total_outstanding' => round($provided->sum('loan_bal'), 2), 'required_provision' => $required,
                'previous_provision' => $existing, 'adjustment' => round($required - $existing, $dp), 'loan_count' => $provided->count(),
                'status' => 'posted', 'created_by' => auth()->id(),
                'notes' => 'Old system provision report as at ' . $asAt . ' — starting figure, no journal (already in the 1109 opening balance)',
            ]);
            foreach ($provided as $l) {
                $loan = $l['loan_id'] ? Loan::find($l['loan_id']) : null;
                if (!$loan) continue;
                $band = collect(self::SPECIFIC_BANDS)->first(fn ($b) => $l['days'] >= $b[0]);
                LoanProvisionLine::create([
                    'loan_provision_id' => $run->id, 'loan_id' => $loan->id, 'client_id' => $loan->client_id,
                    'days_in_arrears' => $l['days'], 'oldest_arrears_date' => Carbon::parse($asAt)->subDays($l['days'])->toDateString(),
                    'outstanding_principal' => $l['loan_bal'], 'rate' => $band[1] ?? 0, 'provision_amount' => $l['old_specific'],
                ]);
            }

            \App\Models\AuditLog::record('loan_provision_from_report', "Specific provision starting figure as at {$asAt} from the old system report: "
                . number_format($required, 2) . ' (no journal — already in the 1109 opening balance)', 'loans');

            return $run;
        });
    }

    /** Remove a starting figure recorded from the old report (no journal) — only while no later specific run is posted. */
    public function removeReportBaseline(LoanProvision $run): void
    {
        if ($run->provision_type !== 'specific' || $run->transaction_id || $run->status !== 'posted' || !str_starts_with((string) $run->notes, 'Old system provision report')) {
            throw ValidationException::withMessages(['run' => 'Only an old-report starting figure can be removed this way.']);
        }
        if (LoanProvision::where('provision_type', 'specific')->where('status', 'posted')->where('id', '!=', $run->id)->whereDate('as_at_date', '>=', $run->as_at_date)->exists()) {
            throw ValidationException::withMessages(['run' => 'A later specific provision is posted. Reverse it first.']);
        }
        $run->update(['status' => 'reversed']);
        \App\Models\AuditLog::record('loan_provision_from_report_removed', 'Removed the old-report starting figure as at ' . $run->as_at_date->toDateString(), 'loans');
    }

    /**
     * Credit balance of a provision (contra-asset) account up to and including $asAt.
     */
    public function provisionBalance(string $accountCode, string $asAt): float
    {
        $account = Account::where('account_code', $accountCode)->first();
        if (!$account) {
            return 0.0;
        }
        $bal = $this->accountingService->getAccountBalance($account->id, null, $asAt);
        return round((float) $bal['credit'] - (float) $bal['debit'], 2);
    }

    /**
     * Specific provision held by this system's runs (excludes the old-system opening balance).
     */
    public function specificRunBalance(string $asAt): float
    {
        return round((float) LoanProvision::where('provision_type', 'specific')
            ->where('status', 'posted')
            ->whereDate('as_at_date', '<=', $asAt)
            ->sum('adjustment'), 2);
    }

    public function latestPosted(string $type): ?LoanProvision
    {
        return LoanProvision::where('provision_type', $type)
            ->where('status', 'posted')
            ->orderByDesc('as_at_date')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * One posted run per date, and runs must move forward in time — back-dating a run
     * before a later one would shift the balance that the later run was sized against.
     */
    public function assertCanRun(string $type, string $asAt): void
    {
        $latest = $this->latestPosted($type);
        if (!$latest) {
            return;
        }
        if ($latest->as_at_date->toDateString() === $asAt) {
            throw ValidationException::withMessages([
                'as_at_date' => "A {$type} provision has already been posted as at {$asAt}. Reverse its journal entry first to re-run it.",
            ]);
        }
        if ($latest->as_at_date->toDateString() > $asAt) {
            throw ValidationException::withMessages([
                'as_at_date' => "A later {$type} provision exists (as at {$latest->as_at_date->toDateString()}). Reverse it first to post an earlier date.",
            ]);
        }
    }

    protected function decimals(): int
    {
        return (int) SystemSetting::get('decimal_places', 2);
    }

    protected function account(string $code): Account
    {
        $account = Account::where('account_code', $code)->first();
        if (!$account) {
            throw ValidationException::withMessages([
                'as_at_date' => "GL account {$code} is missing from the Chart of Accounts.",
            ]);
        }
        return $account;
    }
}
