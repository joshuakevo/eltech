<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\SavingsAccount;
use App\Models\SavingsInterestRun;
use App\Models\SavingsInterestTier;
use App\Models\SavingsProduct;
use App\Models\SavingsTransaction;
use App\Models\SystemSetting;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Running Interest: credits savings interest for one product up to a date.
 *
 * Interest is worked out day by day on each day's closing balance, from the day after the account's last
 * interest date (or its first transaction) to the run date:
 *  - account with a special rate (interest_rate_override): balance × that rate
 *  - product method 'bands':  balance × the rate of the highest band the balance reaches that day
 *                             (Interest Wallet: 10% from 5M, 7% from 1.5M, nothing below)
 *  - product method 'tiers' (Ordinary Savings, the old system's method): month-end balances only, not daily.
 *                             At each month end: savings balance × the rate of the tier it falls in ÷ 12, rounded
 *                             down to a shilling (899,228 → 4% → 2,997). The savings balance holds no interest of
 *                             the year: the interest is accrued to 2006 Savings Interest Payable (tagged to the
 *                             member, like the imported "Save Int") and only on the 31 December run is the
 *                             member's whole 2006 balance moved into savings, where it earns interest from then on.
 *                             Accounts with a special rate keep the daily method.
 * Daily methods use annual rate ÷ 365 per day. One journal per run: DR interest expense / CR the product's savings liability
 * (one line per member, tagged to them); each account gets an "interest" statement row linked to it.
 * Undo deletes the run's journal and statement rows outright (latest run per product only).
 */
class SavingsInterestRunService
{
    public function __construct(protected AccountingService $accounting, protected SavingsService $savings) {}

    /** Bands for a 'bands' product, highest first. */
    public function bands(SavingsProduct $product): array
    {
        return collect($product->interest_bands ?: [])
            ->map(fn ($b) => ['min' => (float) $b['min'], 'rate' => (float) $b['rate']])
            ->sortByDesc('min')->values()->all();
    }

    public function latestRun(SavingsProduct $product): ?SavingsInterestRun
    {
        return SavingsInterestRun::where('savings_product_id', $product->id)->orderByDesc('period_end')->orderByDesc('id')->first();
    }

    /**
     * Preview: one row per account that earns interest up to $periodEnd (nothing is saved).
     * Returns ['rows' => Collection, 'zero' => accounts earning nothing].
     * $from: no account counts from earlier than this date (offered on a product's first run only).
     */
    public function calculate(SavingsProduct $product, string $periodEnd, ?string $from = null): array
    {
        $end      = Carbon::parse($periodEnd)->startOfDay();
        $method   = $product->interest_run_method ?: 'tiers';
        $bands    = $this->bands($product);
        $tiers    = SavingsInterestTier::orderBy('min_balance')->get();
        $tiersDesc = $tiers->sortByDesc(fn ($t) => (float) $t->min_balance)->values();
        $dp       = (int) SystemSetting::get('decimal_places', 2);

        $accounts = SavingsAccount::with('client')->where('product_id', $product->id)->where('status', 'active')->get();
        // Ordinary Savings: the run on 31 December moves each member's accrued interest (2006) into savings
        $yearEnd  = $method === 'tiers' && $end->month === 12 && $end->day === 31;
        $payable  = $yearEnd ? $this->interestPayableByClient($accounts->pluck('client_id')->all(), $end->toDateString()) : [];
        $creditedClients = [];
        $txns     = SavingsTransaction::whereIn('savings_account_id', $accounts->pluck('id'))
            ->where('transaction_date', '<=', $end->toDateString())
            ->orderBy('transaction_date')->orderBy('id')
            ->get(['savings_account_id', 'transaction_date', 'balance_after'])
            ->groupBy('savings_account_id');

        $rows = collect();
        $zero = 0;
        foreach ($accounts as $account) {
            $list = $txns->get($account->id, collect());
            if ($list->isEmpty()) {
                $zero++;
                continue;
            }
            $start = $account->last_interest_date
                ? Carbon::parse($account->last_interest_date)->addDay()
                : Carbon::parse(min($account->opened_date?->toDateString() ?? $list->first()->transaction_date->toDateString(), $list->first()->transaction_date->toDateString()));
            if ($from && $start->lt(Carbon::parse($from))) {
                $start = Carbon::parse($from)->startOfDay();
            }
            if ($start->gt($end)) {
                continue;
            }

            // Closing balance of each day: last transaction on or before that day
            $balance = 0.0;
            $i = 0;
            $n = $list->count();
            while ($i < $n && $list[$i]->transaction_date->lt($start)) {
                $balance = (float) $list[$i]->balance_after;
                $i++;
            }

            $override = $account->interest_rate_override !== null ? (float) $account->interest_rate_override : null;
            $monthEnd = $override === null && $method === 'tiers';
            if ($monthEnd && Carbon::create($start->year, 12, 31)->lt($end)) {
                throw new InvalidArgumentException("Run {$product->name} to 31 Dec {$start->year} first — that run credits the year's interest into the savings balances.");
            }
            $interest = 0.0;
            $sumBal   = 0.0;
            $days     = 0;
            $ratesUsed = [];
            for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
                while ($i < $n && $list[$i]->transaction_date->lte($day)) {
                    $balance = (float) $list[$i]->balance_after;
                    $i++;
                }
                $days++;
                $sumBal += $balance;
                if ($balance <= 0) {
                    continue;
                }
                if ($monthEnd) {
                    // Ordinary Savings (old system): only the balance at each month end counts — the whole
                    // balance × the rate of its tier ÷ 12, rounded down. The balance holds no interest of the year.
                    if (!$day->isLastOfMonth()) {
                        continue;
                    }
                    $tier = $tiersDesc->first(fn ($t) => $balance >= (float) $t->min_balance);
                    $rate = $tier ? (float) $tier->rate : 0.0;
                    if ($rate <= 0) {
                        continue;
                    }
                    $interest += floor($balance * $rate / 100 / 12);
                } elseif ($override !== null) {
                    $rate = $override;
                    $interest += $balance * $rate / 100 / 365;
                } elseif ($method === 'bands') {
                    $rate = 0.0;
                    foreach ($bands as $b) {
                        if ($balance >= $b['min']) {
                            $rate = $b['rate'];
                            break;
                        }
                    }
                    $interest += $balance * $rate / 100 / 365;
                }
                if ($rate !== null && $rate > 0) {
                    $ratesUsed[(string) $rate] = true;
                }
            }

            $interest = round($interest, $dp);
            // 31 December: the member's accrued interest for the year (2006, incl. this run) goes into savings
            $credit = 0.0;
            if ($monthEnd && $yearEnd && !isset($creditedClients[(int) $account->client_id])) {
                $creditedClients[(int) $account->client_id] = true;
                $credit = round(($payable[(int) $account->client_id] ?? 0) + $interest, 2);
            }
            if ($interest <= 0 && $credit <= 0) {
                $zero++;
                continue;
            }

            $label = match (true) {
                $override !== null       => rtrim(rtrim(number_format($override, 2), '0'), '.') . '% (special)',
                $method === 'tiers' && count($ratesUsed) === 1 => array_key_first($ratesUsed) . '% (month end)',
                $method === 'tiers'      => collect(array_keys($ratesUsed))->map(fn ($r) => $r . '%')->implode(' / ') . ' (month end)',
                count($ratesUsed) === 1  => array_key_first($ratesUsed) . '%',
                default                  => collect(array_keys($ratesUsed))->map(fn ($r) => $r . '%')->implode(' / '),
            };

            $rows->push([
                'account'         => $account,
                'period_start'    => $start->toDateString(),
                'period_end'      => $end->toDateString(),
                'days'            => $days,
                'closing_balance' => $balance,
                'average_balance' => round($sumBal / max($days, 1), 2),
                'rate_label'      => $label,
                'interest'        => $interest,
                'accrue'          => $monthEnd,
                'year_end_credit' => $credit,
            ]);
        }

        return ['rows' => $rows->sortBy(fn ($r) => $r['account']->client->name ?? '')->values(), 'zero' => $zero];
    }

    /** Accrued savings interest still owed per member (2006 Savings Interest Payable, lines tagged to the client) as at $date. */
    public function interestPayableByClient(array $clientIds, string $date): array
    {
        $id = $this->payableAccountId(false);
        if (!$id || !$clientIds) {
            return [];
        }
        return \App\Models\TransactionLine::join('transactions', 'transaction_lines.transaction_id', '=', 'transactions.id')
            ->where('transaction_lines.account_id', $id)
            ->whereIn('transaction_lines.client_id', $clientIds)
            ->where('transactions.date', '<=', $date)
            ->groupBy('transaction_lines.client_id')
            ->selectRaw('transaction_lines.client_id as cid, SUM(transaction_lines.credit) - SUM(transaction_lines.debit) as bal')
            ->pluck('bal', 'cid')
            ->mapWithKeys(fn ($bal, $cid) => [(int) $cid => round((float) $bal, 2)])
            ->all();
    }

    public function payableAccountId(bool $required = true): ?int
    {
        $id = Account::where('account_code', '2006')->value('id');
        if (!$id && $required) {
            throw new InvalidArgumentException('Account 2006 Savings Interest Payable is missing.');
        }
        return $id ? (int) $id : null;
    }

    public function expenseAccountId(SavingsProduct $product): int
    {
        $id = $product->interest_expense_account_id ?: Account::where('account_code', '5001')->value('id');
        if (!$id) {
            throw new InvalidArgumentException('Set an interest expense account on the product (or add account 5001).');
        }
        return (int) $id;
    }

    public function run(SavingsProduct $product, string $periodEnd, ?string $from = null): SavingsInterestRun
    {
        $latest = $this->latestRun($product);
        if ($latest && $latest->period_end->toDateString() >= $periodEnd) {
            throw new InvalidArgumentException("Interest for {$product->name} has already been run to {$latest->period_end->format('d M Y')}. Undo that run first, or choose a later date.");
        }
        if (!$product->savings_liability_account_id) {
            throw new InvalidArgumentException("{$product->name} has no savings liability account.");
        }

        $calc = $this->calculate($product, $periodEnd, $from);
        $rows = $calc['rows'];
        if ($rows->isEmpty()) {
            throw new InvalidArgumentException('No account earns interest for this period.');
        }
        $expenseId = $this->expenseAccountId($product);

        return DB::transaction(function () use ($product, $periodEnd, $rows, $expenseId) {
            $total = round($rows->sum('interest'), 2);
            $run = SavingsInterestRun::create([
                'savings_product_id' => $product->id,
                'period_end'         => $periodEnd,
                'total_interest'     => $total,
                'accounts_count'     => $rows->count(),
                'created_by'         => auth()->id(),
            ]);

            $dateLabel = Carbon::parse($periodEnd)->format('d M Y');
            $payableId = $rows->contains(fn ($r) => $r['accrue']) ? $this->payableAccountId() : null;
            $lines = [];
            if ($total > 0) {
                $lines[] = [
                    'account_id'  => $expenseId,
                    'debit'       => $total,
                    'credit'      => 0,
                    'description' => "Interest on {$product->name} to {$dateLabel} ({$rows->count()} accounts)",
                ];
            }
            foreach ($rows as $r) {
                if ($r['interest'] > 0) {
                    $lines[] = [
                        'account_id'  => $r['accrue'] ? $payableId : $product->savings_liability_account_id,
                        'client_id'   => $r['account']->client_id,
                        'debit'       => 0,
                        'credit'      => $r['interest'],
                        'description' => ($r['accrue'] ? 'Interest accrued - ' : 'Interest - ') . $r['account']->account_number,
                    ];
                }
                if ($r['year_end_credit'] > 0) {
                    $lines[] = ['account_id' => $payableId, 'client_id' => $r['account']->client_id, 'debit' => $r['year_end_credit'], 'credit' => 0,
                        'description' => "Year's interest credited to savings - {$r['account']->account_number}"];
                    $lines[] = ['account_id' => $product->savings_liability_account_id, 'client_id' => $r['account']->client_id, 'debit' => 0, 'credit' => $r['year_end_credit'],
                        'description' => "Year's interest credited - {$r['account']->account_number}"];
                }
            }
            $journal = $this->accounting->post($periodEnd, "Savings interest - {$product->name} to {$dateLabel}", $lines, 'savings_interest', $run->id);
            $run->update(['transaction_id' => $journal->id]);

            foreach ($rows as $r) {
                $account = $r['account'];
                $label   = Carbon::parse($r['period_start'])->format('d M Y') . ' – ' . $dateLabel;
                // Ordinary Savings: monthly interest stays in 2006 (no statement row); 31 Dec credits the year's total
                $toSavings = $r['accrue'] ? $r['year_end_credit'] : $r['interest'];
                $st = null;
                if ($toSavings > 0) {
                    $st = SavingsTransaction::create([
                        'savings_account_id' => $account->id,
                        'transaction_type'   => 'interest',
                        'amount'             => $toSavings,
                        'balance_before'     => 0,
                        'balance_after'      => 0,
                        'transaction_date'   => $periodEnd,
                        'reference'          => $journal->reference,
                        'description'        => $r['accrue'] ? 'Interest credited for ' . Carbon::parse($periodEnd)->year : "Interest credit ({$label}) {$r['rate_label']}",
                        'transaction_id'     => $journal->id,
                        'created_by'         => auth()->id(),
                    ]);
                }
                $run->lines()->create([
                    'savings_account_id'          => $account->id,
                    'client_id'                   => $account->client_id,
                    'period_start'                => $r['period_start'],
                    'period_end'                  => $r['period_end'],
                    'days'                        => $r['days'],
                    'closing_balance'             => $r['closing_balance'],
                    'average_balance'             => $r['average_balance'],
                    'rate_label'                  => $r['rate_label'],
                    'interest'                    => $r['interest'],
                    'credited_to_savings'         => $toSavings,
                    'savings_transaction_id'      => $st?->id,
                    'previous_last_interest_date' => $account->last_interest_date?->toDateString(),
                ]);
                $account->update(['last_interest_date' => $periodEnd]);
                if ($st) {
                    $this->savings->recalculateLedger($account);
                }
            }

            AuditLog::record('savings_interest_run', "Ran {$product->name} interest to {$dateLabel}: " . number_format($total, 2) . " on {$rows->count()} accounts (run #{$run->id})", 'savings');

            return $run;
        });
    }

    /** Deletes the run: its statement rows, its journal (and any reversal), and puts back each account's last interest date. */
    public function undo(SavingsInterestRun $run): void
    {
        $latest = $this->latestRun($run->product);
        if ($latest && (int) $latest->id !== (int) $run->id) {
            throw new InvalidArgumentException('Undo the later run first (' . $latest->period_end->format('d M Y') . ').');
        }

        DB::transaction(function () use ($run) {
            $run->load('lines.account');
            foreach ($run->lines as $line) {
                if ($line->savings_transaction_id) {
                    SavingsTransaction::whereKey($line->savings_transaction_id)->delete();
                }
                if ($line->account) {
                    $line->account->update(['last_interest_date' => $line->previous_last_interest_date]);
                    $this->savings->recalculateLedger($line->account);
                }
            }
            if ($run->transaction_id && ($tx = Transaction::find($run->transaction_id))) {
                if ($tx->reversed_by) {
                    Transaction::whereKey($tx->reversed_by)->delete();
                }
                $tx->delete();
            }
            AuditLog::record('savings_interest_undone', "Undid {$run->product->name} interest run to {$run->period_end->format('d M Y')} (" . number_format($run->total_interest, 2) . ", run #{$run->id}) — journal and statement rows deleted", 'savings');
            $run->delete();
        });
    }
}
