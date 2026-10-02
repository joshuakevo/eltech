<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanProvision;
use App\Models\LoanProvisionLine;
use App\Models\SystemSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Loan loss provisioning.
 *
 * General provision = rate (default 1%) × outstanding principal as at a date,
 * across every disbursed loan EXCEPT Locked-Up Loans. Each run posts only the
 * movement needed to bring the GL provision account to the required level:
 *   increase  → DR 5120 General Loan Provision Expense / CR 1110 Loan Provisions — General
 *   decrease  → DR 1110 / CR 5120 (write-back)
 */
class LoanProvisionService
{
    public const GENERAL_PROVISION_ACCOUNT = '1110';
    public const GENERAL_EXPENSE_ACCOUNT   = '5120';
    public const DEFAULT_GENERAL_RATE      = 1.0;

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
            ->select('loan_id', DB::raw('SUM(principal_paid) as principal_paid'));

        return Loan::query()
            ->with('client', 'product')
            ->leftJoinSub($repaidAfter, 'ra', 'ra.loan_id', '=', 'loans.id')
            ->whereIn('loans.status', ['active', 'defaulted', 'closed'])
            ->whereNotNull('loans.disbursement_date')
            ->whereDate('loans.disbursement_date', '<=', $asAt)
            ->when($lockedUpProductId, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('loans.loan_product_id', '!=', $lockedUpProductId)
                ->orWhereNull('loans.loan_product_id')))
            ->select('loans.*', DB::raw('loans.outstanding_principal + COALESCE(ra.principal_paid, 0) as outstanding_as_at'))
            ->orderBy('loans.loan_number')
            ->get()
            ->filter(fn ($loan) => (float) $loan->outstanding_as_at > 0.004)
            ->values();
    }

    /**
     * Build a general provision preview (nothing is saved).
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
            ];
        });

        // Required is rate × total (rounded to the system's decimal places) so the GL
        // posting doesn't pick up fractional residue from per-loan rounding.
        $dp       = (int) SystemSetting::get('decimal_places', 2);
        $total    = round($lines->sum('outstanding'), 2);
        $required = round($total * $rate / 100, $dp);
        $existing = $this->provisionBalance(self::GENERAL_PROVISION_ACCOUNT, $asAt);

        return [
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
     * Post a general provision run and its GL adjustment.
     */
    public function runGeneral(string $asAt, float $rate, ?string $notes = null): LoanProvision
    {
        $this->assertCanRun('general', $asAt);

        $provisionAccount = $this->account(self::GENERAL_PROVISION_ACCOUNT);
        $expenseAccount   = $this->account(self::GENERAL_EXPENSE_ACCOUNT);

        return DB::transaction(function () use ($asAt, $rate, $notes, $provisionAccount, $expenseAccount) {
            $preview = $this->previewGeneral($asAt, $rate);

            $run = LoanProvision::create([
                'provision_type'     => 'general',
                'as_at_date'         => $asAt,
                'rate'               => $rate,
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
                    'outstanding_principal' => $line['outstanding'],
                    'rate'                  => $line['rate'],
                    'provision_amount'      => $line['provision_amount'],
                ]);
            }

            $adjustment = $preview['adjustment'];
            if (abs($adjustment) >= 0.005) {
                $amount = abs($adjustment);
                $label  = 'General loan provision @ ' . rtrim(rtrim(number_format($rate, 4), '0'), '.') . '% as at ' . $asAt;
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
     * before a later one would shift the GL balance that the later run was sized against.
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
