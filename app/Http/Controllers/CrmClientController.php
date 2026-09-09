<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Models\User;
use App\Services\ClientActivityService;
use App\Services\ClientFinancialSummaryService;
use App\Services\ClientHealthService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class CrmClientController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(
        protected ClientFinancialSummaryService $financials,
        protected ClientActivityService $activity,
        protected ClientHealthService $health,
    ) {
    }

    public function index(Request $request)
    {
        $filtered = Client::query()
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('client_number', 'like', "%{$request->search}%")
                ->orWhere('phone', 'like', "%{$request->search}%"))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->client_type, fn ($q) => $q->where('client_type', $request->client_type))
            ->when($request->relationship_manager_id, fn ($q) => $q->where('relationship_manager_id', $request->relationship_manager_id))
            ->when($request->product, fn ($q) => match ($request->product) {
                'savings'       => $q->whereHas('savingsAccounts', fn ($q2) => $q2->whereIn('status', ['active', 'dormant'])),
                'loan'          => $q->whereHas('loans', fn ($q2) => $q2->where('status', 'active')),
                'fixed_deposit' => $q->whereHas('fixedDeposits', fn ($q2) => $q2->where('status', 'active')),
                'shares'        => $q->whereHas('shares', fn ($q2) => $q2->whereIn('status', ['partial', 'paid'])),
                'none'          => $q->whereDoesntHave('savingsAccounts', fn ($q2) => $q2->whereIn('status', ['active', 'dormant']))
                    ->whereDoesntHave('loans', fn ($q2) => $q2->where('status', 'active'))
                    ->whereDoesntHave('fixedDeposits', fn ($q2) => $q2->where('status', 'active'))
                    ->whereDoesntHave('shares', fn ($q2) => $q2->whereIn('status', ['partial', 'paid'])),
                default         => $q,
            })
            ->when($request->joined_from, fn ($q) => $q->whereDate('joining_date', '>=', $request->joined_from))
            ->when($request->joined_to, fn ($q) => $q->whereDate('joining_date', '<=', $request->joined_to));

        // Health is computed (not a DB column), so filtering by it means scoring
        // every client that matches the other filters first, then paginating
        // the filtered ID list in memory. Only paid for when the filter is used.
        if ($request->health) {
            $matchingIds = (clone $filtered)->pluck('id');
            $allScores = $this->health->scoresFor($matchingIds);
            $keptIds = $allScores->filter(fn ($s) => $s['label'] === $request->health)->keys();

            $totalCount = $keptIds->count();
            $page = max(1, (int) $request->input('page', 1));
            $pageIds = $keptIds->slice(($page - 1) * self::PER_PAGE, self::PER_PAGE)->values();

            $pageClients = Client::whereIn('id', $pageIds)
                ->with(['branch', 'segment', 'relationshipManager', 'group'])
                ->withCount([
                    'savingsAccounts as owns_savings_count' => fn ($q) => $q->whereIn('status', ['active', 'dormant']),
                    'loans as owns_loan_count'              => fn ($q) => $q->where('status', 'active'),
                    'fixedDeposits as owns_fd_count'        => fn ($q) => $q->where('status', 'active'),
                    'shares as owns_shares_count'           => fn ($q) => $q->whereIn('status', ['partial', 'paid']),
                ])
                ->orderBy('name')
                ->get();

            $clients = new LengthAwarePaginator($pageClients, $totalCount, self::PER_PAGE, $page, [
                'path' => $request->url(), 'query' => $request->query(),
            ]);
            $healthScores = $allScores;
        } else {
            $totalCount = (clone $filtered)->count();

            $clients = $filtered
                ->with(['branch', 'segment', 'relationshipManager', 'group'])
                ->withCount([
                    'savingsAccounts as owns_savings_count' => fn ($q) => $q->whereIn('status', ['active', 'dormant']),
                    'loans as owns_loan_count'              => fn ($q) => $q->where('status', 'active'),
                    'fixedDeposits as owns_fd_count'        => fn ($q) => $q->where('status', 'active'),
                    'shares as owns_shares_count'           => fn ($q) => $q->whereIn('status', ['partial', 'paid']),
                ])
                ->orderBy('name')
                ->paginate(self::PER_PAGE)
                ->withQueryString();

            $healthScores = $this->health->scoresFor($clients->getCollection()->pluck('id'));
        }

        $ids = $clients->getCollection()->pluck('id');
        $summaries    = $this->financials->summariesFor($ids);
        $lastActivity = $this->activity->lastActivityDatesFor($ids);

        foreach ($clients as $client) {
            $client->financial_summary = $summaries->get($client->id, [
                'total_assets' => 0.0, 'total_liability' => 0.0,
            ]);
            $client->last_activity_at = $lastActivity->get($client->id);
            $client->product_count = ($client->owns_savings_count > 0 ? 1 : 0)
                + ($client->owns_loan_count > 0 ? 1 : 0)
                + ($client->owns_fd_count > 0 ? 1 : 0)
                + ($client->owns_shares_count > 0 ? 1 : 0);
            $client->health = $healthScores->get($client->id);
        }

        $relationshipManagers = User::where('is_relationship_manager', true)->orderBy('name')->get();
        $totalProductTypes = ClientFinancialSummaryService::CORE_PRODUCT_TYPES;

        return view('crm.clients.index', compact('clients', 'totalCount', 'relationshipManagers', 'totalProductTypes'));
    }

    public function show(Client $client)
    {
        $client->load([
            'branch', 'segment', 'relationshipManager', 'createdBy',
            'loans.product', 'savingsAccounts.product', 'fixedDeposits.product', 'shares',
            'group.activeMembers',
        ]);

        $summary = $this->financials->summaryFor($client);
        $lastActivityAt = $this->activity->lastActivityFor($client);
        $health = $this->health->scoreFor($client);

        $ownsSavings = $client->savingsAccounts->whereIn('status', ['active', 'dormant'])->isNotEmpty();
        $ownsLoan    = $client->loans->where('status', 'active')->isNotEmpty();
        $ownsFd      = $client->fixedDeposits->where('status', 'active')->isNotEmpty();
        $ownsShares  = $client->shares->whereIn('status', ['partial', 'paid'])->isNotEmpty();

        $productsOwnedCount = ($ownsSavings ? 1 : 0) + ($ownsLoan ? 1 : 0) + ($ownsFd ? 1 : 0) + ($ownsShares ? 1 : 0);
        $totalProductTypes  = ClientFinancialSummaryService::CORE_PRODUCT_TYPES;

        $productRows = $this->buildProductRows($client);

        return view('crm.clients.show', compact(
            'client', 'summary', 'lastActivityAt', 'health',
            'ownsSavings', 'ownsLoan', 'ownsFd', 'ownsShares',
            'productsOwnedCount', 'totalProductTypes', 'productRows'
        ));
    }

    /**
     * One row per product the client holds, for the Products tab. Only
     * product types that genuinely exist in this system are shown (no
     * placeholder rows for products the system doesn't have, e.g.
     * "Investment"/"Insurance").
     */
    private function buildProductRows(Client $client): array
    {
        $rows = [];

        foreach ($client->savingsAccounts as $account) {
            $rows[] = [
                'type'        => 'Savings',
                'name'        => $account->product->name ?? 'Savings',
                'ref'         => $account->account_number,
                'status'      => $account->status,
                'badge_class' => 'badge-status-' . $account->status,
                'value'       => $account->balance,
                'performance' => $this->savingsPerformance($account),
                'link'        => route('savings.show', $account),
            ];
        }

        foreach ($client->loans as $loan) {
            $repaidPct = $loan->principal > 0
                ? round((($loan->principal - $loan->outstanding_principal) / $loan->principal) * 100)
                : 0;
            $rows[] = [
                'type'        => 'Loan',
                'name'        => $loan->product->name ?? 'Loan',
                'ref'         => $loan->loan_number,
                'status'      => $loan->status,
                'badge_class' => 'badge-status-' . $loan->status,
                'value'       => $loan->outstanding_principal,
                'performance' => "{$repaidPct}% repaid",
                'link'        => route('loans.show', $loan),
            ];
        }

        foreach ($client->fixedDeposits as $fd) {
            $accruedPct = $fd->principal > 0 ? round(($fd->accrued_interest / $fd->principal) * 100, 1) : 0;
            $rows[] = [
                'type'        => 'Fixed Deposit',
                'name'        => $fd->product->name ?? 'Fixed Deposit',
                'ref'         => $fd->deposit_number,
                'status'      => $fd->status,
                'badge_class' => 'badge-status-' . $fd->status,
                'value'       => $fd->principal,
                'performance' => "+{$accruedPct}% accrued",
                'link'        => route('fixed-deposits.show', $fd),
            ];
        }

        foreach ($client->shares as $share) {
            $paidPct = $share->share_value > 0 ? round(($share->amount_paid / $share->share_value) * 100) : 0;
            $rows[] = [
                'type'        => 'Shares',
                'name'        => 'Member Shares',
                'ref'         => $share->share_number,
                'status'      => $share->status,
                'badge_class' => 'badge-membership-' . $share->status,
                'value'       => $share->amount_paid,
                'performance' => "{$paidPct}% paid",
                'link'        => route('clients.shares.statement', $client),
            ];
        }

        if ($client->group) {
            $rows[] = [
                'type'        => 'Group Savings',
                'name'        => $client->group->name,
                'ref'         => $client->group->group_number,
                'status'      => $client->group->status,
                'badge_class' => 'badge-status-' . $client->group->status,
                'value'       => $client->group->activeMembers->sum('balance'),
                'performance' => '—',
                'link'        => null,
            ];
        }

        return $rows;
    }

    /**
     * Compares current balance to the balance ~90 days ago (the last
     * transaction on/before that date) to give a simple, honest trend label.
     * Full multi-month charts are a separate concern (Financial Performance
     * tab); this is a single comparison, not a trend series.
     */
    private function savingsPerformance(SavingsAccount $account): string
    {
        $cutoff = now()->subDays(90)->toDateString();
        $pastBalance = SavingsTransaction::where('savings_account_id', $account->id)
            ->where('transaction_date', '<=', $cutoff)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->value('balance_after');

        if ($pastBalance === null) {
            return 'New';
        }

        $pastBalance = (float) $pastBalance;
        if ($pastBalance == 0.0) {
            return $account->balance > 0 ? 'Growing' : 'Stable';
        }

        $changePct = (($account->balance - $pastBalance) / abs($pastBalance)) * 100;
        if ($changePct > 5)  return 'Growing (+' . round($changePct) . '%)';
        if ($changePct < -5) return 'Declining (' . round($changePct) . '%)';
        return 'Stable';
    }
}
