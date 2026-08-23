<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Client;
use App\Models\ClientSegment;
use App\Models\FixedDeposit;
use App\Models\Loan;
use App\Models\MemberShare;
use App\Models\SavingsAccount;
use App\Models\Transaction;
use App\Models\TransactionLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingService
{
    /**
     * Post a double-entry transaction.
     *
     * @param  string  $date       e.g. '2024-01-15'
     * @param  string  $description
     * @param  array   $lines      [['account_id'=>X,'debit'=>Y,'credit'=>Z,'description'=>'...'], ...]
     * @param  string|null  $module
     * @param  int|null  $moduleId
     * @return Transaction
     */
    public function post(string $date, string $description, array $lines, ?string $module = null, ?int $moduleId = null, ?string $reference = null): Transaction
    {
        $this->validateLines($lines);

        return DB::transaction(function () use ($date, $description, $lines, $module, $moduleId, $reference) {
            $transaction = Transaction::create([
                'date'        => $date,
                'reference'   => $reference ?: $this->generateReference(),
                'description' => $description,
                'module'      => $module,
                'module_id'   => $moduleId,
                'created_by'  => auth()->id(),
            ]);

            foreach ($lines as $line) {
                TransactionLine::create([
                    'transaction_id' => $transaction->id,
                    'account_id'     => $line['account_id'],
                    'client_id'      => $line['client_id'] ?? null,
                    'debit'          => $line['debit'] ?? 0,
                    'credit'         => $line['credit'] ?? 0,
                    'description'    => $line['description'] ?? null,
                ]);
            }

            return $transaction;
        });
    }

    /**
     * Validate that lines are balanced and have at least 2 entries.
     */
    protected function validateLines(array $lines): void
    {
        if (count($lines) < 2) {
            throw new \InvalidArgumentException('A transaction must have at least 2 lines.');
        }

        $totalDebit  = array_sum(array_column($lines, 'debit'));
        $totalCredit = array_sum(array_column($lines, 'credit'));

        if (abs($totalDebit - $totalCredit) > 0.001) {
            throw ValidationException::withMessages([
                'lines' => "Transaction is not balanced. Debits: {$totalDebit}, Credits: {$totalCredit}",
            ]);
        }
    }

    protected function generateReference(): string
    {
        $prefix = 'TXN';
        $date   = now()->format('Ymd');

        $lastRef = Transaction::where('reference', 'like', "{$prefix}-{$date}-%")
            ->orderByDesc('reference')
            ->value('reference');
        $next = $lastRef ? ((int) substr($lastRef, -4)) + 1 : 1;

        do {
            $candidate = "{$prefix}-{$date}-" . str_pad($next, 4, '0', STR_PAD_LEFT);
            $taken     = Transaction::where('reference', $candidate)->exists();
            $next++;
        } while ($taken);

        return $candidate;
    }

    /**
     * Get account balance between dates.
     */
    public function getAccountBalance(int $accountId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = TransactionLine::where('account_id', $accountId)
            ->join('transactions', 'transaction_lines.transaction_id', '=', 'transactions.id');

        if ($fromDate) {
            $query->where('transactions.date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('transactions.date', '<=', $toDate);
        }

        $debit  = $query->sum('transaction_lines.debit');
        $credit = $query->sum('transaction_lines.credit');

        return [
            'debit'   => $debit,
            'credit'  => $credit,
            'balance' => $debit - $credit,
        ];
    }

    /**
     * Generate trial balance.
     */
    public function getTrialBalance(?string $fromDate = null, ?string $toDate = null): array
    {
        $accounts = Account::where('is_active', true)->get();
        $rows = [];
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($accounts as $account) {
            $bal = $this->getAccountBalance($account->id, $fromDate, $toDate);
            $net = $bal['debit'] - $bal['credit'];
            if (abs($net) < 0.001) continue;

            $rows[] = [
                'account_code' => $account->account_code,
                'account_name' => $account->account_name,
                'account_type' => $account->account_type,
                'debit'        => $net > 0 ? $net : 0,
                'credit'       => $net < 0 ? -$net : 0,
            ];

            $totalDebit  += $net > 0 ? $net : 0;
            $totalCredit += $net < 0 ? -$net : 0;
        }

        return [
            'rows'         => $rows,
            'total_debit'  => $totalDebit,
            'total_credit' => $totalCredit,
            'balanced'     => abs($totalDebit - $totalCredit) < 0.001,
        ];
    }

    /**
     * Generate income statement (Revenue - Expense).
     *
     * When $segmentId is given, only counts transaction lines whose transaction is
     * attached to a client in that segment (see resolveClientIdsForTransactions()).
     */
    public function getIncomeStatement(?string $fromDate = null, ?string $toDate = null, ?int $segmentId = null): array
    {
        $revenues = Account::where('account_type', 'revenue')->where('is_active', true)->get();
        $expenses = Account::where('account_type', 'expense')->where('is_active', true)->get();

        $sumsByAccount = $segmentId
            ? $this->getSegmentAccountSums($revenues->pluck('id')->merge($expenses->pluck('id')), $fromDate, $toDate, $segmentId)
            : null;

        $balanceFor = function (Account $acc) use ($sumsByAccount, $fromDate, $toDate) {
            if ($sumsByAccount !== null) {
                return $sumsByAccount[$acc->id] ?? ['debit' => 0, 'credit' => 0];
            }
            return $this->getAccountBalance($acc->id, $fromDate, $toDate);
        };

        $revenueRows = [];
        $totalRevenue = 0;
        foreach ($revenues as $acc) {
            $bal = $balanceFor($acc);
            $balance = $bal['credit'] - $bal['debit'];
            if ($balance == 0) continue;
            $revenueRows[] = ['account' => $acc, 'balance' => $balance];
            $totalRevenue += $balance;
        }

        $expenseRows = [];
        $totalExpense = 0;
        foreach ($expenses as $acc) {
            $bal = $balanceFor($acc);
            $balance = $bal['debit'] - $bal['credit'];
            if ($balance == 0) continue;
            $expenseRows[] = ['account' => $acc, 'balance' => $balance];
            $totalExpense += $balance;
        }

        return [
            'revenue_rows'  => $revenueRows,
            'expense_rows'  => $expenseRows,
            'total_revenue' => $totalRevenue,
            'total_expense' => $totalExpense,
            'net_income'    => $totalRevenue - $totalExpense,
        ];
    }

    /**
     * Sum debit/credit per account, keeping only transaction lines whose transaction
     * is attached to a client in $segmentId. Most module-generated postings (savings,
     * loans, fixed deposits, membership fees, shares) never tag transaction_lines.client_id
     * directly -- the client is only resolvable via transactions.module/module_id -- so this
     * mirrors the same resolution TransactionController uses when editing those entries.
     *
     * @return array<int, array{debit: float, credit: float}> account_id => sums
     */
    protected function getSegmentAccountSums($accountIds, ?string $fromDate, ?string $toDate, int $segmentId): array
    {
        $clientIds = Client::where('segment_id', $segmentId)->pluck('id')->all();
        if (empty($clientIds)) {
            return [];
        }

        $query = TransactionLine::whereIn('transaction_lines.account_id', $accountIds)
            ->join('transactions', 'transaction_lines.transaction_id', '=', 'transactions.id')
            ->select(
                'transaction_lines.account_id',
                'transaction_lines.debit',
                'transaction_lines.credit',
                'transaction_lines.client_id',
                'transactions.id as transaction_id',
                'transactions.module',
                'transactions.module_id'
            );
        if ($fromDate) $query->where('transactions.date', '>=', $fromDate);
        if ($toDate)   $query->where('transactions.date', '<=', $toDate);

        $lines = $query->get();
        if ($lines->isEmpty()) {
            return [];
        }

        $clientByTransaction = $this->resolveClientIdsForTransactions($lines);

        $sums = [];
        foreach ($lines as $line) {
            $clientId = $line->client_id ?: ($clientByTransaction[$line->transaction_id] ?? null);
            if (!$clientId || !in_array($clientId, $clientIds, true)) {
                continue;
            }

            $sums[$line->account_id] ??= ['debit' => 0, 'credit' => 0];
            $sums[$line->account_id]['debit']  += (float) $line->debit;
            $sums[$line->account_id]['credit'] += (float) $line->credit;
        }

        return $sums;
    }

    /**
     * Read-only diagnostic for "the Income Statement segment filter always
     * returns zero" reports. Runs the exact same client-resolution logic as
     * getSegmentAccountSums()/resolveClientIdsForTransactions() above, but
     * instead of filtering to one segment, buckets every revenue/expense
     * transaction line by why it would or wouldn't show under a segment
     * filter: no resolvable client at all, a resolved client with no
     * segment assigned, or a resolved client with a segment (broken down by
     * segment). Never writes anything.
     */
    public function diagnoseSegmentCoverage(?string $fromDate, ?string $toDate): array
    {
        $revenues   = Account::where('account_type', 'revenue')->where('is_active', true)->get();
        $expenses   = Account::where('account_type', 'expense')->where('is_active', true)->get();
        $accountIds = $revenues->pluck('id')->merge($expenses->pluck('id'));

        $accountNames = $revenues->concat($expenses)->pluck('account_name', 'id');

        $query = TransactionLine::whereIn('transaction_lines.account_id', $accountIds)
            ->join('transactions', 'transaction_lines.transaction_id', '=', 'transactions.id')
            ->select(
                'transaction_lines.account_id',
                'transaction_lines.debit',
                'transaction_lines.credit',
                'transaction_lines.client_id',
                'transactions.id as transaction_id',
                'transactions.module',
                'transactions.module_id',
                'transactions.description'
            );
        if ($fromDate) $query->where('transactions.date', '>=', $fromDate);
        if ($toDate)   $query->where('transactions.date', '<=', $toDate);

        $lines = $query->get();

        $clientByTransaction = $this->resolveClientIdsForTransactions($lines);

        $resolvedClientIds = collect();
        foreach ($lines as $line) {
            $cid = $line->client_id ?: ($clientByTransaction[$line->transaction_id] ?? null);
            if ($cid) $resolvedClientIds->push($cid);
        }
        $clientSegments = Client::whereIn('id', $resolvedClientIds->unique())->pluck('segment_id', 'id');
        $segmentNames   = ClientSegment::pluck('name', 'id');

        $totalAmount       = 0;
        $unresolved        = 0;
        $unresolvedAmount  = 0;
        $unresolvedByAccount = [];
        $noSegment         = 0;
        $noSegmentAmount   = 0;
        $bySegment         = [];

        foreach ($lines as $line) {
            $amount = (float) $line->debit + (float) $line->credit;
            $totalAmount += $amount;

            $cid = $line->client_id ?: ($clientByTransaction[$line->transaction_id] ?? null);

            if (!$cid) {
                $unresolved++;
                $unresolvedAmount += $amount;
                $key = $accountNames[$line->account_id] ?? "Account #{$line->account_id}";
                $unresolvedByAccount[$key] ??= ['lines' => 0, 'amount' => 0, 'modules' => [], 'sample_description' => $line->description];
                $unresolvedByAccount[$key]['lines']++;
                $unresolvedByAccount[$key]['amount'] += $amount;
                $mod = $line->module ?: 'null';
                $unresolvedByAccount[$key]['modules'][$mod] = ($unresolvedByAccount[$key]['modules'][$mod] ?? 0) + 1;
                continue;
            }

            $segId = $clientSegments[$cid] ?? null;
            if (!$segId) {
                $noSegment++;
                $noSegmentAmount += $amount;
                continue;
            }

            $bySegment[$segId] ??= ['name' => $segmentNames[$segId] ?? "Segment #{$segId}", 'lines' => 0, 'amount' => 0];
            $bySegment[$segId]['lines']++;
            $bySegment[$segId]['amount'] += $amount;
        }

        return [
            'total_lines'                => $lines->count(),
            'total_amount'               => $totalAmount,
            'unresolved_client_lines'    => $unresolved,
            'unresolved_client_amount'   => $unresolvedAmount,
            'unresolved_by_account'      => $unresolvedByAccount,
            'resolved_no_segment_lines'  => $noSegment,
            'resolved_no_segment_amount' => $noSegmentAmount,
            'by_segment'                 => $bySegment,
        ];
    }

    /**
     * Batch-resolve the client attached to each transaction referenced by $lines
     * (each line carries transaction_id/module/module_id), grouping by module to
     * avoid an N+1 query per line. Modules with more than one client per
     * transaction (payroll, groups) are intentionally left unresolved (null).
     *
     * @return array<int, int|null> transaction_id => client_id
     */
    protected function resolveClientIdsForTransactions($lines): array
    {
        $byModule = $lines->unique('transaction_id')->groupBy('module');
        $result = [];

        foreach ($byModule as $module => $rows) {
            $moduleIds = $rows->pluck('module_id')->filter()->unique()->values();

            $map = match ($module) {
                'savings'       => SavingsAccount::whereIn('id', $moduleIds)->pluck('client_id', 'id'),
                'loan'          => Loan::whereIn('id', $moduleIds)->pluck('client_id', 'id'),
                'fixed_deposit' => FixedDeposit::withTrashed()->whereIn('id', $moduleIds)->pluck('client_id', 'id'),
                'member_share'  => MemberShare::whereIn('id', $moduleIds)->pluck('client_id', 'id'),
                default         => collect(),
            };

            foreach ($rows as $row) {
                $result[$row->transaction_id] = match (true) {
                    $module === 'client' => $row->module_id,
                    $map->has($row->module_id) => $map->get($row->module_id),
                    default => null,
                };
            }
        }

        return $result;
    }

    /**
     * Generate balance sheet (Assets, Liabilities, Equity).
     */
    public function getBalanceSheet(?string $asOf = null): array
    {
        $asOf = $asOf ?? now()->toDateString();

        $result = [];
        foreach (['asset', 'liability', 'equity'] as $type) {
            $accounts = Account::where('account_type', $type)->where('is_active', true)->get();
            $rows = [];
            $total = 0;
            foreach ($accounts as $acc) {
                $bal = $this->getAccountBalance($acc->id, null, $asOf);
                $balance = ($type === 'asset') ? $bal['debit'] - $bal['credit'] : $bal['credit'] - $bal['debit'];
                if ($balance == 0) continue;
                $rows[] = ['account' => $acc, 'balance' => $balance];
                $total += $balance;
            }
            $result[$type] = ['rows' => $rows, 'total' => $total];
        }

        // Net income (Revenue - Expenses) up to $asOf is part of equity on the balance sheet.
        // Revenue accounts are credit-normal; expense accounts are debit-normal.
        $revenues = Account::where('account_type', 'revenue')->where('is_active', true)->get();
        $expenses = Account::where('account_type', 'expense')->where('is_active', true)->get();

        $totalRevenue = 0;
        foreach ($revenues as $acc) {
            $bal = $this->getAccountBalance($acc->id, null, $asOf);
            $totalRevenue += $bal['credit'] - $bal['debit'];
        }

        $totalExpense = 0;
        foreach ($expenses as $acc) {
            $bal = $this->getAccountBalance($acc->id, null, $asOf);
            $totalExpense += $bal['debit'] - $bal['credit'];
        }

        $netIncome = $totalRevenue - $totalExpense;

        // Append net income as a pseudo-row under equity
        if (abs($netIncome) >= 0.01) {
            $result['equity']['rows'][] = [
                'account' => (object)['account_code' => '—', 'account_name' => 'Net Income (Current Period)'],
                'balance' => $netIncome,
            ];
            $result['equity']['total'] += $netIncome;
        }

        $result['net_income'] = $netIncome;

        return $result;
    }
}
