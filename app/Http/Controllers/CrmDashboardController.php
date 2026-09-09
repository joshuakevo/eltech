<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientSegment;
use App\Models\FixedDeposit;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\MemberShare;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Services\ClientActivityService;
use App\Services\ClientFinancialSummaryService;
use App\Services\ClientHealthService;
use Illuminate\Support\Facades\DB;

class CrmDashboardController extends Controller
{
    public function __construct(
        protected ClientFinancialSummaryService $financials,
        protected ClientActivityService $activity,
        protected ClientHealthService $health,
    ) {
    }

    public function index()
    {
        $totalClients  = Client::count();
        $activeClients = Client::where('status', 'active')->get(['id', 'joining_date', 'created_at']);
        $activeCount   = $activeClients->count();
        $activeIds     = $activeClients->pluck('id');

        // --- New clients / growth -------------------------------------------------
        $newThisMonth = Client::whereRaw('COALESCE(joining_date, created_at) >= ?', [now()->startOfMonth()->toDateString()])->count();
        $newLastMonth = Client::whereRaw('COALESCE(joining_date, created_at) >= ? AND COALESCE(joining_date, created_at) < ?', [
            now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
            now()->startOfMonth()->toDateString(),
        ])->count();
        $growthPct = $newLastMonth > 0
            ? round((($newThisMonth - $newLastMonth) / $newLastMonth) * 100)
            : ($newThisMonth > 0 ? 100 : 0);

        // --- Product ownership sets (reused for adoption chart, distribution, average) ---
        $savingsSet = SavingsAccount::whereIn('status', ['active', 'dormant'])->distinct()->pluck('client_id')->flip();
        $loanSet    = Loan::where('status', 'active')->distinct()->pluck('client_id')->flip();
        $fdSet      = FixedDeposit::where('status', 'active')->distinct()->pluck('client_id')->flip();
        $shareSet   = MemberShare::whereIn('status', ['partial', 'paid'])->distinct()->pluck('client_id')->flip();

        $distribution = [0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0];
        $totalProductInstances = 0;
        foreach ($activeIds as $id) {
            $count = (isset($savingsSet[$id]) ? 1 : 0)
                + (isset($loanSet[$id]) ? 1 : 0)
                + (isset($fdSet[$id]) ? 1 : 0)
                + (isset($shareSet[$id]) ? 1 : 0);
            $distribution[$count]++;
            $totalProductInstances += $count;
        }
        $avgProducts = $activeCount > 0 ? round($totalProductInstances / $activeCount, 2) : 0;

        // --- Customer health -- single source of truth shared with the CRM Clients
        // list and Client 360 (ClientHealthService), so "at risk" here means the
        // same thing everywhere in the CRM, not a second, different definition. ---
        $healthScores = $this->health->scoresFor($activeIds);
        $healthyCount   = $healthScores->where('label', 'Healthy')->count();
        $attentionCount = $healthScores->where('label', 'Needs Attention')->count();
        $atRiskCount    = $healthScores->where('label', 'At Risk')->count();
        $atRiskClientIds = $healthScores->where('label', 'At Risk')->keys();

        // --- Total customer value (assets) -- same "financially active" universe as Member Summary ---
        $financialClientIds = $this->financials->activeClientIdsAsOf();
        $summaries = $this->financials->summariesFor($financialClientIds);
        $totalCustomerValue = $summaries->sum('total_assets');
        $totalOutstanding   = $summaries->sum('total_liability');

        // --- Portfolio at risk: how much of total outstanding sits with At-Risk clients ---
        $atRiskOutstanding = $summaries->only($atRiskClientIds->all())->sum('total_liability');
        $portfolioAtRiskPct = $totalOutstanding > 0 ? round(($atRiskOutstanding / $totalOutstanding) * 100, 1) : 0;

        // --- Top 10 customers by total value, and how concentrated value is among them ---
        $topClientIds = $summaries->sortByDesc('total_assets')->take(10)->keys();
        $topClients = Client::whereIn('id', $topClientIds)->get()->keyBy('id');
        $topCustomers = $topClientIds->map(fn ($id) => (object) [
            'client' => $topClients->get($id),
            'value'  => $summaries->get($id)['total_assets'],
        ])->filter(fn ($row) => $row->client !== null)->values();
        $top10Value = $topCustomers->sum('value');
        $concentrationPct = $totalCustomerValue > 0 ? round(($top10Value / $totalCustomerValue) * 100, 1) : 0;

        // --- Customer growth over time (last 12 months, by joining_date/created_at) ---
        $months = collect();
        $monthLabels = collect();
        for ($i = 11; $i >= 0; $i--) {
            $m = now()->startOfMonth()->subMonths($i);
            $months->push($m);
            $monthLabels->push($m->format('M Y'));
        }
        $growthSeries = $months->map(fn ($m) => Client::whereRaw(
            'COALESCE(joining_date, created_at) >= ? AND COALESCE(joining_date, created_at) < ?',
            [$m->toDateString(), $m->copy()->addMonthNoOverflow()->toDateString()]
        )->count());

        // --- Transaction activity trend (last 6 months): savings transactions + loan repayments ---
        $activityMonths = collect();
        $activityLabels = collect();
        for ($i = 5; $i >= 0; $i--) {
            $m = now()->startOfMonth()->subMonths($i);
            $activityMonths->push($m);
            $activityLabels->push($m->format('M Y'));
        }
        $savingsTxnCounts = $activityMonths->map(fn ($m) => SavingsTransaction::whereYear('transaction_date', $m->year)
            ->whereMonth('transaction_date', $m->month)->count());
        $repaymentCounts = $activityMonths->map(fn ($m) => LoanRepayment::whereYear('payment_date', $m->year)
            ->whereMonth('payment_date', $m->month)->count());

        // --- Clients by segment (only segments actually in use) ---
        $segmentCounts = Client::whereNotNull('segment_id')
            ->selectRaw('segment_id, count(*) as cnt')
            ->groupBy('segment_id')
            ->pluck('cnt', 'segment_id');
        $segments = ClientSegment::whereIn('id', $segmentCounts->keys())->pluck('name', 'id');
        $segmentLabels = $segments->values();
        $segmentData   = $segments->keys()->map(fn ($id) => $segmentCounts->get($id, 0));
        $unsegmentedCount = Client::whereNull('segment_id')->count();
        if ($unsegmentedCount > 0) {
            $segmentLabels->push('Unassigned');
            $segmentData->push($unsegmentedCount);
        }

        // --- Clients by branch (only shown when the org actually has more than one) ---
        $branchCounts = Client::whereNotNull('branch_id')
            ->selectRaw('branch_id, count(*) as cnt')
            ->groupBy('branch_id')
            ->pluck('cnt', 'branch_id');
        $showBranchChart = $branchCounts->count() > 1;
        $branches = $showBranchChart ? Branch::whereIn('id', $branchCounts->keys())->pluck('name', 'id') : collect();
        $branchLabels = $branches->values();
        $branchData   = $branches->keys()->map(fn ($id) => $branchCounts->get($id, 0));

        $segmentPerformance = $this->segmentPerformance();

        return view('crm.dashboard', compact(
            'totalClients', 'activeCount', 'newThisMonth', 'growthPct',
            'healthyCount', 'attentionCount', 'atRiskCount', 'avgProducts',
            'totalCustomerValue', 'totalOutstanding', 'portfolioAtRiskPct', 'concentrationPct',
            'distribution', 'topCustomers',
            'monthLabels', 'growthSeries',
            'activityLabels', 'savingsTxnCounts', 'repaymentCounts',
            'savingsSet', 'loanSet', 'fdSet', 'shareSet',
            'segmentLabels', 'segmentData',
            'showBranchChart', 'branchLabels', 'branchData',
            'segmentPerformance'
        ));
    }

    /**
     * Portfolio, income, and expense per client segment. Portfolio figures
     * reuse ClientFinancialSummaryService (same numbers as everywhere else
     * in the CRM). Income/expense are this-calendar-month, computed from
     * sub-ledger rows joined through client -> segment -- NOT from
     * transaction_lines.segment_id, which is only populated for manually
     * tagged journal entries and would silently miss most automated
     * interest/fee postings.
     *
     * "Income" = loan interest collected + one-off loan fees on loans
     * disbursed this month. "Expense" = savings interest credited to
     * members this month (interest paid out is a cost to the institution).
     * Fixed deposit interest expense isn't included -- there's no discrete
     * per-posting FD ledger to attribute to a date/segment, only running
     * totals on the FD record itself.
     */
    private function segmentPerformance()
    {
        $segments = ClientSegment::orderBy('name')->get();
        if ($segments->isEmpty()) {
            return collect();
        }

        $clientSegments = Client::whereNotNull('segment_id')->pluck('segment_id', 'id');
        $clientIds = $clientSegments->keys();
        if ($clientIds->isEmpty()) {
            return collect();
        }

        $summaries = $this->financials->summariesFor($clientIds);

        $portfolio = [];
        foreach ($clientIds as $clientId) {
            $segmentId = $clientSegments->get($clientId);
            $s = $summaries->get($clientId);
            if (!$s) {
                continue;
            }
            $portfolio[$segmentId] ??= ['clients' => 0, 'savings' => 0.0, 'loans' => 0.0, 'fd' => 0.0, 'shares' => 0.0, 'total_value' => 0.0];
            $portfolio[$segmentId]['clients']++;
            $portfolio[$segmentId]['savings']     += $s['savings_balance'] + $s['savings_interest'];
            $portfolio[$segmentId]['loans']       += $s['loan_principal'] + $s['loan_interest'];
            $portfolio[$segmentId]['fd']          += $s['fd_amount'];
            $portfolio[$segmentId]['shares']      += $s['share_total'];
            $portfolio[$segmentId]['total_value'] += $s['total_assets'];
        }

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd   = now()->endOfMonth()->toDateString();

        $interestIncome = DB::table('loan_repayments')
            ->join('loans', 'loans.id', '=', 'loan_repayments.loan_id')
            ->join('clients', 'clients.id', '=', 'loans.client_id')
            ->whereNotNull('clients.segment_id')
            ->whereBetween('loan_repayments.payment_date', [$monthStart, $monthEnd])
            ->groupBy('clients.segment_id')
            ->select('clients.segment_id', DB::raw('SUM(loan_repayments.interest_paid) as amt'))
            ->pluck('amt', 'segment_id');

        $feeIncome = DB::table('loans')
            ->join('clients', 'clients.id', '=', 'loans.client_id')
            ->whereNotNull('clients.segment_id')
            ->whereBetween('loans.disbursement_date', [$monthStart, $monthEnd])
            ->groupBy('clients.segment_id')
            ->select('clients.segment_id', DB::raw('SUM(COALESCE(loans.application_fee,0) + COALESCE(loans.management_fee,0) + COALESCE(loans.insurance_fee,0)) as amt'))
            ->pluck('amt', 'segment_id');

        $savingsInterestExpense = DB::table('savings_transactions')
            ->join('savings_accounts', 'savings_accounts.id', '=', 'savings_transactions.savings_account_id')
            ->join('clients', 'clients.id', '=', 'savings_accounts.client_id')
            ->whereNotNull('clients.segment_id')
            ->where('savings_transactions.transaction_type', 'interest')
            ->whereBetween('savings_transactions.transaction_date', [$monthStart, $monthEnd])
            ->groupBy('clients.segment_id')
            ->select('clients.segment_id', DB::raw('SUM(savings_transactions.amount) as amt'))
            ->pluck('amt', 'segment_id');

        return $segments->map(function ($segment) use ($portfolio, $interestIncome, $feeIncome, $savingsInterestExpense) {
            $p = $portfolio[$segment->id] ?? ['clients' => 0, 'savings' => 0.0, 'loans' => 0.0, 'fd' => 0.0, 'shares' => 0.0, 'total_value' => 0.0];
            $income  = (float) ($interestIncome[$segment->id] ?? 0) + (float) ($feeIncome[$segment->id] ?? 0);
            $expense = (float) ($savingsInterestExpense[$segment->id] ?? 0);

            return (object) array_merge($p, [
                'segment' => $segment,
                'income'  => $income,
                'expense' => $expense,
                'net'     => $income - $expense,
            ]);
        })
        ->filter(fn ($row) => $row->clients > 0)
        ->sortByDesc('total_value')
        ->values();
    }
}
