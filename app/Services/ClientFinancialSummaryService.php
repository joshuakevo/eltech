<?php

namespace App\Services;

use App\Models\Client;
use App\Models\SavingsAccount;
use Illuminate\Support\Collection;

/**
 * Per-client cross-product financial summary (savings, loans, fixed deposits,
 * shares, group balances) as of a given date.
 *
 * Extracted from ReportController::memberSummary() so the Member Summary
 * report and the CRM Client 360 page compute identical figures from a single
 * source of truth -- every formula here is copied verbatim from that method,
 * not re-derived.
 */
class ClientFinancialSummaryService
{
    public function __construct(protected SavingsService $savingsService)
    {
    }

    public const SHARE_VALUE = 100000;

    /**
     * The core product types every individual client can hold, used as the
     * denominator for "product penetration" everywhere it's shown (CRM
     * Clients list, Client 360, CRM Dashboard, health score). Group savings
     * is a different relational shape (a client is a *member* of a group
     * owned by another client record) and is excluded from this count.
     */
    public const CORE_PRODUCT_TYPES = 4;

    /**
     * Bulk-compute summaries for a specific set of client IDs as of $asOf
     * (default today). Returns a Collection keyed by client_id, each value
     * an array: savings_balance, savings_interest, loan_principal,
     * loan_interest, fd_amount, group_balance, share_units, share_total,
     * total_assets, total_liability.
     */
    public function summariesFor(Collection $clientIds, ?string $asOf = null): Collection
    {
        $asOf = $asOf ?? now()->toDateString();
        $clientIds = $clientIds->filter()->unique()->values();

        if ($clientIds->isEmpty()) {
            return collect();
        }

        // Savings: balance_after of the last transaction on or before $asOf, per client
        $savingsBalances = \DB::table('savings_accounts as sa')
            ->leftJoinSub(
                \DB::table('savings_transactions')
                    ->where('transaction_date', '<=', $asOf)
                    ->select('savings_account_id', \DB::raw('MAX(id) as last_id'))
                    ->groupBy('savings_account_id'),
                'latest',
                'latest.savings_account_id', '=', 'sa.id'
            )
            ->leftJoin('savings_transactions as st', 'st.id', '=', 'latest.last_id')
            ->whereIn('sa.client_id', $clientIds)
            ->groupBy('sa.client_id')
            ->select('sa.client_id', \DB::raw('COALESCE(SUM(st.balance_after), 0) as balance'))
            ->pluck('balance', 'client_id');

        // Loans: outstanding principal = principal − repayments up to $asOf
        $loanPrincipals = \DB::table('loans as l')
            ->leftJoinSub(
                \DB::table('loan_repayments')
                    ->where('payment_date', '<=', $asOf)
                    ->groupBy('loan_id')
                    ->select('loan_id', \DB::raw('SUM(principal_paid) as paid')),
                'rp', 'rp.loan_id', '=', 'l.id'
            )
            ->where('l.disbursement_date', '<=', $asOf)
            ->whereNull('l.deleted_at')
            ->whereIn('l.client_id', $clientIds)
            ->groupBy('l.client_id')
            ->select('l.client_id', \DB::raw('SUM(GREATEST(0, l.principal - COALESCE(rp.paid, 0))) as outstanding'))
            ->pluck('outstanding', 'client_id');

        // Loans: outstanding interest = scheduled interest due on or before $asOf − interest paid up to $asOf
        $loanInterests = \DB::table('loans as l')
            ->leftJoinSub(
                \DB::table('loan_schedules')
                    ->where('due_date', '<=', $asOf)
                    ->groupBy('loan_id')
                    ->select('loan_id', \DB::raw('SUM(GREATEST(0, interest_due - interest_paid)) as interest_os')),
                'sched', 'sched.loan_id', '=', 'l.id'
            )
            ->where('l.disbursement_date', '<=', $asOf)
            ->whereNull('l.deleted_at')
            ->whereIn('l.client_id', $clientIds)
            ->groupBy('l.client_id')
            ->select('l.client_id', \DB::raw('COALESCE(SUM(sched.interest_os), 0) as interest'))
            ->pluck('interest', 'client_id');

        // Fixed Deposits: principal of FDs that started on or before $asOf and mature after $asOf
        $fdAmounts = \DB::table('fixed_deposits')
            ->where('start_date', '<=', $asOf)
            ->where('maturity_date', '>=', $asOf)
            ->whereNull('deleted_at')
            ->whereIn('client_id', $clientIds)
            ->groupBy('client_id')
            ->select('client_id', \DB::raw('SUM(principal) as amount'))
            ->pluck('amount', 'client_id');

        // Shares: amount paid on shares created on or before $asOf
        $shareAmounts = \DB::table('member_shares')
            ->whereDate('created_at', '<=', $asOf)
            ->whereIn('client_id', $clientIds)
            ->groupBy('client_id')
            ->select('client_id', \DB::raw('SUM(amount_paid) as paid'))
            ->pluck('paid', 'client_id');

        // Savings interest has two sources, both meaning "owed but not yet credited":
        //
        // 1. Tiered products: live day-by-day projection via a per-account loop (not
        //    a bulk SQL aggregate, since the graduated-tier calculation mirrors
        //    SavingsService::postInterest()). Flat products are excluded from this
        //    part -- their interest is already folded into savings_balance since
        //    it's credited automatically every month.
        // 2. Account 2006 "Savings Interest Payable" -- accrued interest inherited
        //    from opening-balance migrations, booked as a client-tagged liability
        //    rather than a live accrual since there's no balance history to project
        //    it from.
        $savingsInterests = [];
        $tieredAccounts = SavingsAccount::with('product')
            ->where('status', 'active')
            ->whereIn('client_id', $clientIds)
            ->whereHas('product', fn($q) => $q->where('interest_method', 'tiered'))
            ->get();
        foreach ($tieredAccounts as $account) {
            $projected = $this->savingsService->previewAccruedInterest($account, $asOf);
            if ($projected > 0) {
                $savingsInterests[$account->client_id] = ($savingsInterests[$account->client_id] ?? 0) + $projected;
            }
        }

        $accruedInterestPayable = \DB::table('transaction_lines as tl')
            ->join('transactions as t', 't.id', '=', 'tl.transaction_id')
            ->join('accounts as a', 'a.id', '=', 'tl.account_id')
            ->where('a.account_code', '2006')
            ->where('t.date', '<=', $asOf)
            ->whereNotNull('tl.client_id')
            ->whereIn('tl.client_id', $clientIds)
            ->groupBy('tl.client_id')
            ->select('tl.client_id', \DB::raw('SUM(tl.credit - tl.debit) as amount'))
            ->pluck('amount', 'client_id');
        foreach ($accruedInterestPayable as $clientId => $amount) {
            if ((float) $amount != 0) {
                $savingsInterests[$clientId] = ($savingsInterests[$clientId] ?? 0) + (float) $amount;
            }
        }

        // Group balance: for group-type clients, sum of their group members' balances
        $groupBalances = \DB::table('groups as g')
            ->join('group_members as gm', 'gm.group_id', '=', 'g.id')
            ->whereNotNull('g.client_id')
            ->whereIn('g.client_id', $clientIds)
            ->where('gm.status', 'active')
            ->groupBy('g.client_id')
            ->select('g.client_id', \DB::raw('SUM(gm.balance) as balance'))
            ->pluck('balance', 'client_id');

        return $clientIds->mapWithKeys(function ($clientId) use (
            $savingsBalances, $savingsInterests, $loanPrincipals, $loanInterests, $fdAmounts, $shareAmounts, $groupBalances
        ) {
            $savingsBalance  = (float) ($savingsBalances[$clientId] ?? 0);
            $savingsInterest = (float) ($savingsInterests[$clientId] ?? 0);
            $loanPrincipal   = (float) ($loanPrincipals[$clientId]  ?? 0);
            $loanInterest    = (float) ($loanInterests[$clientId]   ?? 0);
            $fdAmount        = (float) ($fdAmounts[$clientId]        ?? 0);
            $sharePaid       = (float) ($shareAmounts[$clientId]     ?? 0);
            $groupBalance    = (float) ($groupBalances[$clientId]    ?? 0);
            $shareUnits      = self::SHARE_VALUE > 0 ? floor($sharePaid / self::SHARE_VALUE) : 0;

            return [$clientId => [
                'savings_balance'  => $savingsBalance,
                'savings_interest' => $savingsInterest,
                'loan_principal'   => $loanPrincipal,
                'loan_interest'    => $loanInterest,
                'fd_amount'        => $fdAmount,
                'group_balance'    => $groupBalance,
                'share_units'      => $shareUnits,
                'share_total'      => $sharePaid,
                'total_assets'     => $savingsBalance + $savingsInterest + $fdAmount + $sharePaid + $groupBalance,
                'total_liability'  => $loanPrincipal + $loanInterest,
            ]];
        });
    }

    /**
     * Convenience wrapper: summary for a single client.
     */
    public function summaryFor(Client $client, ?string $asOf = null): array
    {
        $default = [
            'savings_balance' => 0.0, 'savings_interest' => 0.0, 'loan_principal' => 0.0,
            'loan_interest' => 0.0, 'fd_amount' => 0.0, 'group_balance' => 0.0,
            'share_units' => 0, 'share_total' => 0.0, 'total_assets' => 0.0, 'total_liability' => 0.0,
        ];

        return $this->summariesFor(collect([$client->id]), $asOf)->get($client->id, $default);
    }

    /**
     * Client IDs with any dated financial activity on or before $asOf --
     * a savings transaction, a loan disbursed, a fixed deposit opened, a
     * client-tagged GL posting, or a member share recorded. Mirrors the
     * inclusion rule ReportController::memberSummary() has always used: a
     * client is "financially active as of $asOf" if they have a real dated
     * transaction, not merely a created_at/joining_date on the client record.
     */
    public function activeClientIdsAsOf(?string $asOf = null): Collection
    {
        $asOf = $asOf ?? now()->toDateString();

        $loanClientIds = \DB::table('loans')
            ->where('disbursement_date', '<=', $asOf)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('client_id');

        $savingsClientIds = \DB::table('savings_transactions')
            ->join('savings_accounts', 'savings_accounts.id', '=', 'savings_transactions.savings_account_id')
            ->where('savings_transactions.transaction_date', '<=', $asOf)
            ->distinct()
            ->pluck('savings_accounts.client_id');

        $fdClientIds = \DB::table('fixed_deposits')
            ->where('start_date', '<=', $asOf)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('client_id');

        $glClientIds = \DB::table('transaction_lines')
            ->join('transactions', 'transactions.id', '=', 'transaction_lines.transaction_id')
            ->whereNotNull('transaction_lines.client_id')
            ->where('transactions.date', '<=', $asOf)
            ->distinct()
            ->pluck('transaction_lines.client_id');

        $shareClientIds = \DB::table('member_shares')
            ->whereDate('created_at', '<=', $asOf)
            ->distinct()
            ->pluck('client_id');

        return $loanClientIds
            ->merge($savingsClientIds)
            ->merge($fdClientIds)
            ->merge($glClientIds)
            ->merge($shareClientIds)
            ->unique()
            ->values();
    }
}
