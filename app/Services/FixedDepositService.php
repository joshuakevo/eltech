<?php

namespace App\Services;

use App\Models\Account;

use App\Models\FixedDeposit;
use App\Models\FixedDepositProduct;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FixedDepositService
{
    public function __construct(
        protected AccountingService $accounting,
        protected \App\Services\SavingsService $savingsService,
    ) {}

    /**
     * Create a new fixed deposit.
     */
    public function createDeposit(array $data): FixedDeposit
    {
        $reference = $data['reference'] ?? null;
        return DB::transaction(function () use ($data, $reference) {
            $product    = FixedDepositProduct::findOrFail($data['product_id']);
            $principal  = $data['principal'];
            $rate       = $data['interest_rate'] ?? $product->interest_rate;
            $months     = $data['term_months'] ?? $product->term_months;
            $startDate  = Carbon::parse($data['start_date']);
            $maturity   = $startDate->copy()->addMonths($months);

            // Simple interest P × R × T (T in years), or compounded monthly when the placement says so
            $compounding     = ($data['compounding'] ?? 'simple') === 'monthly' ? 'monthly' : 'simple';
            $interestAmount  = FixedDeposit::interestFor((float) $principal, (float) $rate, (int) $months, $compounding);
            $maturityAmount  = $principal + $interestAmount;

            $savingsAccount = isset($data['savings_account_id']) && $data['savings_account_id']
                ? SavingsAccount::findOrFail($data['savings_account_id'])
                : null;

            $allowOverdraft = !empty($data['allow_overdraft']);
            if ($savingsAccount && !$allowOverdraft && !$savingsAccount->canWithdraw($principal)) {
                throw new \InvalidArgumentException(
                    'Insufficient savings balance to fund this fixed deposit.'
                );
            }
            // Funded from another member's account (e.g. a pool): don't link it — maturity pays the investor's own savings
            $linkSavings = $savingsAccount && (int) $savingsAccount->client_id === (int) $data['client_id'];
            $fundDate = !empty($data['funding_date']) ? Carbon::parse($data['funding_date'])->toDateString() : $startDate->toDateString();

            $deposit = FixedDeposit::create([
                'client_id'          => $data['client_id'],
                'savings_account_id' => $linkSavings ? $savingsAccount->id : null,
                'product_id'         => $data['product_id'],
                'deposit_number'     => $this->generateDepositNumber(),
                'principal'          => $principal,
                'interest_rate'      => $rate,
                'compounding'        => $compounding,
                'asset_class'        => ($data['asset_class'] ?? null) ?: null,
                'certificate_reference' => ($data['certificate_reference'] ?? null) ?: null,
                'term_months'        => $months,
                'start_date'         => $startDate->toDateString(),
                'maturity_date'      => $maturity->toDateString(),
                'interest_amount'    => round($interestAmount, 2),
                'maturity_amount'    => round($maturityAmount, 2),
                'accrued_interest'   => 0,
                'status'             => 'active',
                'payment_source_account_id' => $savingsAccount ? null : ($data['payment_source_account_id'] ?? null),
                'created_by'         => auth()->id(),
            ]);

            if ($savingsAccount) {
                // Deduct from savings: DR Savings Liability, CR FD Liability (no cash)
                $savingsProduct = $savingsAccount->product;
                $balBefore      = $savingsAccount->balance;

                $placementJournal = $this->accounting->post(
                    $fundDate,
                    "Fixed deposit placement from savings - {$deposit->deposit_number}",
                    [
                        [
                            'account_id'  => $savingsProduct->savings_liability_account_id,
                            'debit'       => $principal,
                            'credit'      => 0,
                            'description' => "FD placement - {$savingsAccount->account_number}",
                            'client_id'   => $savingsAccount->client_id,
                        ],
                        [
                            'account_id'  => $product->deposit_liability_account_id,
                            'debit'       => 0,
                            'credit'      => $principal,
                            'description' => "FD liability - {$deposit->deposit_number}",
                        ],
                    ],
                    'fixed_deposit',
                    $deposit->id,
                    $reference
                );

                SavingsTransaction::create([
                    'savings_account_id' => $savingsAccount->id,
                    'transaction_type'   => 'withdrawal',
                    'amount'             => $principal,
                    'balance_before'     => $balBefore,
                    'balance_after'      => $balBefore - $principal,
                    'transaction_date'   => $fundDate,
                    'reference'          => $placementJournal->reference,
                    'description'        => "Fixed deposit placement - {$deposit->deposit_number}" . ($linkSavings ? '' : ' (' . ($deposit->client->name ?? 'investor') . ')'),
                    'transaction_id'     => $placementJournal->id,
                    'created_by'         => auth()->id(),
                ]);
                // Running balances in date order (handles a back-dated funding date)
                app(\App\Services\SavingsService::class)->recalculateLedger($savingsAccount->fresh());
            } else {
                // Cash-funded: DR Cash/Bank, CR FD Liability
                $this->accounting->post(
                    $startDate->toDateString(),
                    "Fixed deposit creation - {$deposit->deposit_number}",
                    [
                        [
                            'account_id'  => $data['payment_source_account_id'] ?? $this->getCashAccount(),
                            'debit'       => $principal,
                            'credit'      => 0,
                            'description' => "FD receipt - {$deposit->deposit_number}",
                        ],
                        [
                            'account_id'  => $product->deposit_liability_account_id,
                            'debit'       => 0,
                            'credit'      => $principal,
                            'description' => "FD liability - {$deposit->deposit_number}",
                        ],
                    ],
                    'fixed_deposit',
                    $deposit->id,
                    $reference
                );
            }

            return $deposit;
        });
    }

    /**
     * Accrue interest for a fixed deposit (periodic).
     */
    public function accrueInterest(FixedDeposit $deposit, string $date): void
    {
        if ($deposit->status !== 'active') return;

        $product       = $deposit->product;
        if ($product && $product->isAnniversary()) {
            $this->accrueAnniversaries($deposit, $date);
            return;
        }
        $daysElapsed   = Carbon::parse($deposit->start_date)->diffInDays(Carbon::parse($date));
        $totalDays     = Carbon::parse($deposit->start_date)->diffInDays($deposit->maturity_date);
        $totalInterest = $deposit->interest_amount;

        $accruedToDate = $totalDays > 0
            ? round(($daysElapsed / $totalDays) * $totalInterest, 2)
            : 0;

        $newAccrual = max(0, $accruedToDate - $deposit->accrued_interest);
        if ($newAccrual <= 0) return;

        DB::transaction(function () use ($deposit, $product, $newAccrual, $date) {
            // DR Interest Expense, CR Interest Payable
            $this->accounting->post(
                $date,
                "Interest accrual - {$deposit->deposit_number}",
                [
                    [
                        'account_id'  => $product->interest_expense_account_id,
                        'debit'       => $newAccrual,
                        'credit'      => 0,
                        'description' => "Interest expense - {$deposit->deposit_number}",
                    ],
                    [
                        'account_id'  => $this->getInterestPayableAccount(),
                        'debit'       => 0,
                        'credit'      => $newAccrual,
                        'description' => "Interest payable - {$deposit->deposit_number}",
                    ],
                ],
                'fixed_deposit',
                $deposit->id
            );

            $deposit->increment('accrued_interest', $newAccrual);

            if (Carbon::parse($date)->gte($deposit->maturity_date)) {
                $deposit->update(['status' => 'matured']);
            }
        });
    }

    /**
     * Anniversary products: book one month's interest on each monthly anniversary of the value date
     * reached by $date (dated the anniversary), DR interest expense / CR FD interest payable (2003).
     */
    protected function accrueAnniversaries(FixedDeposit $deposit, string $date): void
    {
        $product = $deposit->product;
        DB::transaction(function () use ($deposit, $product, $date) {
            $deposit = FixedDeposit::whereKey($deposit->id)->lockForUpdate()->first();
            $booked = round((float) $deposit->accrued_interest, 2);
            $n = max(1, (int) $deposit->term_months);
            foreach ($deposit->monthlySchedule(Carbon::parse($date)) as $row) {
                if (!$row['earned'] || $row['cumulative'] <= $booked + 0.01) {
                    continue;
                }
                $amount = round($row['cumulative'] - $booked, 2);
                $this->accounting->post(
                    $row['date']->toDateString(),
                    "Interest accrual month {$row['month']} of {$n} - {$deposit->deposit_number}",
                    [
                        ['account_id' => $product->interest_expense_account_id ?: \App\Models\Account::where('account_code', '5002')->value('id'), 'debit' => $amount, 'credit' => 0, 'client_id' => $deposit->client_id, 'description' => "Interest expense - {$deposit->deposit_number}"],
                        ['account_id' => $this->getInterestPayableAccount(), 'debit' => 0, 'credit' => $amount, 'client_id' => $deposit->client_id, 'description' => "Interest payable - {$deposit->deposit_number}"],
                    ],
                    'fixed_deposit',
                    $deposit->id
                );
                $booked = round($booked + $amount, 2);
            }
            if ($booked > (float) $deposit->accrued_interest) {
                $deposit->update(['accrued_interest' => $booked]);
            }
            if (Carbon::parse($date)->gte($deposit->maturity_date)) {
                $deposit->update(['status' => 'matured']);
            }
        });
    }

    /**
     * Corrects a deposit's interest rate (entered wrongly). Interest and maturity amount are recalculated with the
     * deposit's own term and compounding. Running/matured deposits: figures only. Already paid out to savings
     * (status closed, "Fixed deposit maturity - …" row on the member's statement): the difference is paid into
     * (or taken back from) that savings account — DR interest expense (product's, fallback 5002) / CR savings
     * liability, or the reverse — dated the payout date when that period is open, else today; with its own
     * statement row linked to the journal (module savings, so reversing the journal removes the row).
     * Returns ['old_rate', 'new_rate', 'old_interest', 'new_interest', 'difference', 'paid_to' (account|null)].
     */
    public function correctRate(FixedDeposit $deposit, float $annualRate, string $reason): array
    {
        if ($annualRate <= 0 || $annualRate > 200) {
            throw new \InvalidArgumentException('Enter a valid interest rate.');
        }
        $old = ['rate' => (float) $deposit->interest_rate, 'interest' => (float) $deposit->interest_amount, 'maturity' => (float) $deposit->maturity_amount];
        $newInterest = FixedDeposit::interestFor((float) $deposit->principal, $annualRate, (int) $deposit->term_months, $deposit->compounding ?? 'simple');
        $diff = round($newInterest - $old['interest'], 2);
        if (abs($annualRate - $old['rate']) < 0.00001) {
            throw new \InvalidArgumentException('That is already the deposit\'s rate.');
        }

        $payout = null;
        if ($deposit->status === 'closed') {
            $payout = SavingsTransaction::with('savingsAccount.product')->where('description', "Fixed deposit maturity - {$deposit->deposit_number}")
                ->where('transaction_type', 'deposit')->latest('id')->first();
            if (!$payout || !$payout->savingsAccount) {
                throw new \InvalidArgumentException('This deposit was closed without a maturity payout to savings (e.g. broken early or paid out another way) — correct it with a journal entry instead.');
            }
        }

        return DB::transaction(function () use ($deposit, $annualRate, $newInterest, $diff, $old, $payout, $reason) {
            $deposit->update([
                'interest_rate'   => $annualRate,
                'interest_amount' => $newInterest,
                'maturity_amount' => round((float) $deposit->principal + $newInterest, 2),
            ]);
            $label = $deposit->product ? $deposit->product->rateLabel($old['rate']) . ' → ' . $deposit->product->rateLabel($annualRate) : "{$old['rate']}% → {$annualRate}%";

            $paidTo = null;
            if ($payout && abs($diff) >= 0.01) {
                $account = $payout->savingsAccount;
                $date = $deposit->closed_date && \App\Models\FinancialPeriod::isOpen($deposit->closed_date) ? $deposit->closed_date->toDateString() : today()->toDateString();
                $expenseId = $deposit->product->interest_expense_account_id ?: Account::where('account_code', '5002')->value('id');
                $liabilityId = $account->product->savings_liability_account_id;
                $amount = abs($diff);
                $desc = "Fixed deposit interest correction - {$deposit->deposit_number} ({$label})";
                $journal = $this->accounting->post($date, $desc, $diff > 0
                    ? [
                        ['account_id' => $expenseId, 'debit' => $amount, 'credit' => 0, 'description' => $desc],
                        ['account_id' => $liabilityId, 'debit' => 0, 'credit' => $amount, 'client_id' => $deposit->client_id, 'description' => "Interest correction credited to {$account->account_number}"],
                    ]
                    : [
                        ['account_id' => $liabilityId, 'debit' => $amount, 'credit' => 0, 'client_id' => $deposit->client_id, 'description' => "Interest correction taken from {$account->account_number}"],
                        ['account_id' => $expenseId, 'debit' => 0, 'credit' => $amount, 'description' => $desc],
                    ], 'savings', $account->id);
                SavingsTransaction::create([
                    'savings_account_id' => $account->id,
                    'transaction_type'   => $diff > 0 ? 'deposit' : 'withdrawal',
                    'amount'             => $amount,
                    'balance_before'     => 0,
                    'balance_after'      => 0,
                    'transaction_date'   => $date,
                    'reference'          => $journal->reference,
                    'description'        => $desc,
                    'transaction_id'     => $journal->id,
                    'created_by'         => auth()->id(),
                ]);
                app(SavingsService::class)->recalculateLedger($account);
                $paidTo = $account->account_number;
            }

            \App\Models\AuditLog::record('fd_rate_corrected', "{$deposit->deposit_number}: rate {$label}, interest " . number_format($old['interest'], 2) . ' → ' . number_format($newInterest, 2)
                . ($paidTo ? ', difference ' . number_format($diff, 2) . " to {$paidTo}" : '') . " — {$reason}", 'fixed_deposits');

            return ['old_rate' => $old['rate'], 'new_rate' => $annualRate, 'old_interest' => $old['interest'], 'new_interest' => $newInterest, 'difference' => $diff, 'paid_to' => $paidTo];
        });
    }

    /**
     * Mature and pay out a fixed deposit (only on/after maturity date).
     */
    public function matureDeposit(FixedDeposit $deposit, string $date, ?string $reference = null): FixedDeposit
    {
        if (!in_array($deposit->status, ['active', 'matured'])) {
            throw new \InvalidArgumentException('Deposit cannot be matured in its current state.');
        }

        if (Carbon::parse($date)->lt($deposit->maturity_date)) {
            throw new \InvalidArgumentException(
                'Maturity payout can only be processed on or after the maturity date (' .
                $deposit->maturity_date->format('d M Y') . '). Use "Break FD" for early termination.'
            );
        }

        return DB::transaction(function () use ($deposit, $date, $reference) {
            $product        = $deposit->product;
            $principal      = $deposit->principal;
            $interest       = $deposit->interest_amount;
            $total          = $deposit->maturity_amount;
            $savingsAccount = $this->requirePayoutSavingsAccount($deposit);

            // Credit maturity amount to the client's savings: DR FD Liability + Interest Payable, CR Savings Liability
            $savingsProduct = $savingsAccount->product;
            $balBefore      = $savingsAccount->balance;

            $maturityJournal = $this->accounting->post(
                $date,
                "Fixed deposit maturity to savings - {$deposit->deposit_number}",
                [
                    [
                        'account_id'  => $product->deposit_liability_account_id,
                        'debit'       => $principal,
                        'credit'      => 0,
                        'description' => "FD principal returned - {$deposit->deposit_number}",
                    ],
                    [
                        'account_id'  => $this->getInterestPayableAccount(),
                        'debit'       => $interest,
                        'credit'      => 0,
                        'description' => "FD interest payout - {$deposit->deposit_number}",
                    ],
                    [
                        'account_id'  => $savingsProduct->savings_liability_account_id,
                        'debit'       => 0,
                        'credit'      => $total,
                        'description' => "FD maturity credited to {$savingsAccount->account_number}",
                    ],
                ],
                'fixed_deposit',
                $deposit->id,
                $reference
            );

            $savingsAccount->update(['balance' => $balBefore + $total]);

            SavingsTransaction::create([
                'savings_account_id' => $savingsAccount->id,
                'transaction_type'   => 'deposit',
                'amount'             => $total,
                'balance_before'     => $balBefore,
                'balance_after'      => $balBefore + $total,
                'transaction_date'   => $date,
                'reference'          => $maturityJournal->reference,
                'description'        => "Fixed deposit maturity - {$deposit->deposit_number}",
                'transaction_id'     => $maturityJournal->id,
                'created_by'         => auth()->id(),
            ]);

            $deposit->update([
                'status'      => 'closed',
                'closed_date' => $date,
            ]);

            return $deposit->fresh();
        });
    }

    /**
     * Break a fixed deposit early — reverses accrued interest, returns principal only.
     */
    public function breakDeposit(FixedDeposit $deposit, string $breakDate, ?string $reference = null): FixedDeposit
    {
        if ($deposit->status !== 'active') {
            throw new \InvalidArgumentException('Only active deposits can be broken early.');
        }

        if (Carbon::parse($breakDate)->gte($deposit->maturity_date)) {
            throw new \InvalidArgumentException(
                'Deposit has already reached maturity. Use "Process Maturity Payout" instead.'
            );
        }

        return DB::transaction(function () use ($deposit, $breakDate, $reference) {
            $product        = $deposit->product;
            $principal      = $deposit->principal;
            $accrued        = $deposit->accrued_interest;
            $savingsAccount = $this->requirePayoutSavingsAccount($deposit);

            // Reverse any accrued interest that was posted to GL
            if ($accrued > 0) {
                $this->accounting->post(
                    $breakDate,
                    "FD early break - interest reversal - {$deposit->deposit_number}",
                    [
                        [
                            'account_id'  => $this->getInterestPayableAccount(),
                            'debit'       => $accrued,
                            'credit'      => 0,
                            'description' => "Interest payable reversal - {$deposit->deposit_number}",
                        ],
                        [
                            'account_id'  => $product->interest_expense_account_id,
                            'debit'       => 0,
                            'credit'      => $accrued,
                            'description' => "Interest expense reversal - {$deposit->deposit_number}",
                        ],
                    ],
                    'fixed_deposit',
                    $deposit->id
                );
            }

            // Return principal only (no interest) to the client's savings
            $savingsProduct = $savingsAccount->product;
            $balBefore      = $savingsAccount->balance;

            $breakJournal = $this->accounting->post(
                $breakDate,
                "FD early break - principal return to savings - {$deposit->deposit_number}",
                [
                    [
                        'account_id'  => $product->deposit_liability_account_id,
                        'debit'       => $principal,
                        'credit'      => 0,
                        'description' => "FD principal return - {$deposit->deposit_number}",
                    ],
                    [
                        'account_id'  => $savingsProduct->savings_liability_account_id,
                        'debit'       => 0,
                        'credit'      => $principal,
                        'description' => "FD break - principal to {$savingsAccount->account_number}",
                    ],
                ],
                'fixed_deposit',
                $deposit->id,
                $reference
            );

            $savingsAccount->update(['balance' => $balBefore + $principal]);

            SavingsTransaction::create([
                'savings_account_id' => $savingsAccount->id,
                'transaction_type'   => 'deposit',
                'amount'             => $principal,
                'balance_before'     => $balBefore,
                'balance_after'      => $balBefore + $principal,
                'transaction_date'   => $breakDate,
                'reference'          => $breakJournal->reference,
                'description'        => "Fixed deposit early break - principal returned - {$deposit->deposit_number}",
                'transaction_id'     => $breakJournal->id,
                'created_by'         => auth()->id(),
            ]);

            $deposit->update([
                'status'           => 'broken',
                'closed_date'      => $breakDate,
                'accrued_interest' => 0,
            ]);

            return $deposit->fresh();
        });
    }

    /**
     * Savings account that receives a maturity / break payout: the FD's linked
     * account if still active, otherwise the client's oldest active savings account.
     */
    public function payoutSavingsAccount(FixedDeposit $deposit): ?SavingsAccount
    {
        $linked = $deposit->savingsAccount;
        if ($linked && $linked->status === 'active') {
            return $linked;
        }

        return SavingsAccount::where('client_id', $deposit->client_id)
            ->where('status', 'active')
            ->orderBy('id')
            ->first();
    }

    protected function requirePayoutSavingsAccount(FixedDeposit $deposit): SavingsAccount
    {
        $account = $this->payoutSavingsAccount($deposit);
        if (!$account) {
            throw new \InvalidArgumentException(
                'The client has no active savings account to receive the payout. Open a savings account for the client first.'
            );
        }

        return $account;
    }

    protected function getCashAccount(): int
    {
        $account = \App\Models\Account::where('account_code', '1001')->first();
        return $account?->id ?? 1;
    }

    protected function getInterestPayableAccount(): int
    {
        $account = \App\Models\Account::where('account_code', '2003')->first();
        return $account?->id ?? 1;
    }

    protected function generateDepositNumber(): string
    {
        $prefix = 'FD';
        $year   = now()->format('Y');

        // withTrashed(): FixedDeposit is soft-deleted (e.g. when a creation entry is
        // reversed), but the row -- and its unique deposit_number -- still physically
        // exists, so it must still count toward "already taken".
        $lastNumber = FixedDeposit::withTrashed()
            ->where('deposit_number', 'like', "{$prefix}-{$year}-%")
            ->orderByDesc('deposit_number')
            ->value('deposit_number');
        $next = $lastNumber ? ((int) substr($lastNumber, -5)) + 1 : 1;

        do {
            $candidate = "{$prefix}-{$year}-" . str_pad($next, 5, '0', STR_PAD_LEFT);
            $taken     = FixedDeposit::withTrashed()->where('deposit_number', $candidate)->exists();
            $next++;
        } while ($taken);

        return $candidate;
    }
}
