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
 *              more than 90 days  → 50% of outstanding principal
 *              365 days and above → 100% of outstanding principal
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
        [365, 100.0, '365+ days'],
        [91,  50.0,  '91 – 364 days'],
    ];

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
                ->orWhereNull('loans.loan_product_id')))
            ->select(
                'loans.*',
                DB::raw('loans.outstanding_principal + COALESCE(ra.principal_paid, 0) as outstanding_as_at'),
                DB::raw('COALESCE(ra.schedule_paid, 0) as schedule_paid_after')
            )
            ->orderBy('loans.loan_number')
            ->get()
            ->filter(fn ($loan) => (float) $loan->outstanding_as_at > 0.004)
            ->values();
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

        foreach ($schedules as $i => $s) {
            if (Carbon::parse($s->due_date)->toDateString() > $asAt) {
                break;
            }
            $due = (float) $s->principal_due + (float) $s->interest_due;
            if ($due - $paid[$i] > 1) {   // tolerate rounding residue
                return Carbon::parse($s->due_date)->startOfDay();
            }
        }

        return null;
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
        $loans = $this->outstandingAsAt($asAt);
        $loans->load('schedules');

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
                    ? 'Specific loan provision (arrears > 90 days) as at ' . $asAt
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
