<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\FixedDeposit;
use App\Models\Loan;
use App\Models\LoanSchedule;
use App\Models\MemberShare;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Models\LoanRepayment;
use App\Services\ClientActivityService;
use App\Services\ClientFinancialSummaryService;
use Illuminate\Support\Facades\DB;

class CrmDashboardController extends Controller
{
    private const CORE_PRODUCT_TYPES = 4; // Savings, Loan, Fixed Deposit, Shares
    private const DORMANT_DAYS = 180;      // no activity in 6 months, matches the operational dashboard's dormancy heuristic

    public function __construct(
        protected ClientFinancialSummaryService $financials,
        protected ClientActivityService $activity,
    ) {
    }

    public function index()
    {
        $totalClients  = Client::count();
        $activeClients = Client::where('status', 'active')->get(['id', 'joining_date', 'created_at']);
        $activeCount   = $activeClients->count();
        $activeIds     = $activeClients->pluck('id');

        // --- New clients / growth -------------------------------------------------
        $joinExpr = DB::raw('COALESCE(joining_date, created_at)');
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

        // --- Dormant: active, joined more than 180 days ago, no activity in the last 180 days ---
        $lastActivity = $this->activity->lastActivityDatesFor($activeIds);
        $cutoff = now()->subDays(self::DORMANT_DAYS);
        $dormantCount = 0;
        foreach ($activeClients as $client) {
            $joinedAt = $client->joining_date ?? $client->created_at;
            if ($joinedAt && $joinedAt->gt($cutoff)) {
                continue; // too new to call dormant
            }
            $last = $lastActivity->get($client->id);
            if (!$last || \Illuminate\Support\Carbon::parse($last)->lt($cutoff)) {
                $dormantCount++;
            }
        }

        // --- At risk: active client with an active loan carrying an overdue installment ---
        $overdueLoanIds = LoanSchedule::where('due_date', '<', now()->toDateString())
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->distinct()
            ->pluck('loan_id');
        $atRiskCount = Loan::where('status', 'active')
            ->whereIn('id', $overdueLoanIds)
            ->distinct()
            ->count('client_id');

        // --- Total customer value (assets) -- same "financially active" universe as Member Summary ---
        $financialClientIds = $this->financials->activeClientIdsAsOf();
        $summaries = $this->financials->summariesFor($financialClientIds);
        $totalCustomerValue = $summaries->sum('total_assets');
        $totalOutstanding   = $summaries->sum('total_liability');

        // --- Top 10 customers by total value ---
        $topClientIds = $summaries->sortByDesc('total_assets')->take(10)->keys();
        $topClients = Client::whereIn('id', $topClientIds)->get()->keyBy('id');
        $topCustomers = $topClientIds->map(fn ($id) => (object) [
            'client' => $topClients->get($id),
            'value'  => $summaries->get($id)['total_assets'],
        ])->filter(fn ($row) => $row->client !== null)->values();

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

        return view('crm.dashboard', compact(
            'totalClients', 'activeCount', 'newThisMonth', 'growthPct',
            'dormantCount', 'atRiskCount', 'avgProducts',
            'totalCustomerValue', 'totalOutstanding',
            'distribution', 'topCustomers',
            'monthLabels', 'growthSeries',
            'activityLabels', 'savingsTxnCounts', 'repaymentCounts',
            'savingsSet', 'loanSet', 'fdSet', 'shareSet'
        ));
    }
}
