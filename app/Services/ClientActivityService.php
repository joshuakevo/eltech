<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Last-activity lookups across a client's products, built from existing
 * transaction tables (no new "activity" table). Bulk methods take a set of
 * client IDs to avoid N+1 queries on list pages.
 */
class ClientActivityService
{
    /**
     * Most recent dated activity per client, across savings transactions,
     * loan repayments, share transactions, and group transactions. Returns a
     * Collection keyed by client_id => date string (or null if no activity).
     */
    public function lastActivityDatesFor(Collection $clientIds): Collection
    {
        $clientIds = $clientIds->filter()->unique()->values();
        if ($clientIds->isEmpty()) {
            return collect();
        }

        $savingsDates = DB::table('savings_transactions')
            ->join('savings_accounts', 'savings_accounts.id', '=', 'savings_transactions.savings_account_id')
            ->whereIn('savings_accounts.client_id', $clientIds)
            ->groupBy('savings_accounts.client_id')
            ->select('savings_accounts.client_id', DB::raw('MAX(transaction_date) as last_date'))
            ->pluck('last_date', 'client_id');

        $loanDates = DB::table('loan_repayments')
            ->join('loans', 'loans.id', '=', 'loan_repayments.loan_id')
            ->whereIn('loans.client_id', $clientIds)
            ->groupBy('loans.client_id')
            ->select('loans.client_id', DB::raw('MAX(payment_date) as last_date'))
            ->pluck('last_date', 'client_id');

        $shareDates = DB::table('share_transactions')
            ->whereIn('client_id', $clientIds)
            ->groupBy('client_id')
            ->select('client_id', DB::raw('MAX(transaction_date) as last_date'))
            ->pluck('last_date', 'client_id');

        $groupDates = DB::table('group_transactions')
            ->join('group_members', 'group_members.id', '=', 'group_transactions.member_id')
            ->whereIn('group_members.client_id', $clientIds)
            ->groupBy('group_members.client_id')
            ->select('group_members.client_id', DB::raw('MAX(transaction_date) as last_date'))
            ->pluck('last_date', 'client_id');

        return $clientIds->mapWithKeys(function ($id) use ($savingsDates, $loanDates, $shareDates, $groupDates) {
            $dates = array_filter([
                $savingsDates[$id] ?? null,
                $loanDates[$id] ?? null,
                $shareDates[$id] ?? null,
                $groupDates[$id] ?? null,
            ]);
            return [$id => $dates ? max($dates) : null];
        });
    }

    public function lastActivityFor(Client $client): ?string
    {
        return $this->lastActivityDatesFor(collect([$client->id]))->get($client->id);
    }
}
