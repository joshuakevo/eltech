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

        return view('crm.dashboard', compact(
            'totalClients', 'activeCount', 'newThisMonth', 'growthPct',
            'healthyCount', 'attentionCount', 'atRiskCount', 'avgProducts',
            'totalCustomerValue', 'totalOutstanding', 'portfolioAtRiskPct', 'concentrationPct',
            'distribution', 'topCustomers',
            'monthLabels', 'growthSeries',
            'activityLabels', 'savingsTxnCounts', 'repaymentCounts',
            'savingsSet', 'loanSet', 'fdSet', 'shareSet',
            'segmentLabels', 'segmentData',
            'showBranchChart', 'branchLabels', 'branchData'
        ));
    }
}
