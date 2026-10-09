<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\FixedDeposit;
use App\Models\Group;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanRepayment;
use App\Models\MemberShare;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\SavingsTransaction;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /** Opening balances brought over from the previous systems — not real activity. */
    private const OPENING_DESC = 'Opening balance%';

    public function index()
    {
        session(['active_portal' => 'staff']);
        if (auth()->user()->hasRole('group_leader')) {
            return redirect()->route('group-portal.leader');
        }
        if (auth()->user()->hasRole('group_member')) {
            return redirect()->route('group-portal.member');
        }

        // ── Member deposits: each savings product, fixed deposits, group deposits ──
        $palette = ['#2563eb', '#0d9488', '#7c3aed', '#0891b2', '#db2777', '#65a30d', '#ea580c', '#475569'];
        $deposits = collect();
        foreach (SavingsProduct::orderBy('name')->get(['id', 'name']) as $i => $product) {
            $q = SavingsAccount::where('product_id', $product->id)->where('status', 'active');
            $deposits->push([
                'label' => $product->name, 'icon' => 'bi-piggy-bank', 'color' => $palette[$i % count($palette)],
                'amount' => (float) (clone $q)->sum('balance'), 'count' => (clone $q)->count(),
                'overdrawn' => (float) (clone $q)->where('balance', '<', 0)->sum('balance'),
                'url' => route('reports.savings-balances', ['product_id' => $product->id]),
            ]);
        }
        $fd = FixedDeposit::where('status', 'active');
        $deposits->push([
            'label' => 'Fixed Deposits', 'icon' => 'bi-safe', 'color' => '#b45309',
            'amount' => (float) (clone $fd)->sum('principal'), 'count' => (clone $fd)->count(), 'overdrawn' => 0,
            'url' => route('fixed-deposits.index', ['status' => 'active']),
        ]);
        $groups = Group::where('status', 'active')->get();
        $deposits->push([
            'label' => 'Group Deposits', 'icon' => 'bi-people', 'color' => '#be123c',
            'amount' => (float) $groups->sum(fn ($g) => $g->isPooled() ? (float) $g->pool_balance : $g->total_balance),
            'count' => $groups->count(), 'count_label' => 'groups', 'overdrawn' => 0,
            'url' => route('groups.index'),
        ]);
        $deposits = $deposits->filter(fn ($d) => $d['count'] > 0 || $d['amount'] != 0)->values();
        $totalDeposits = $deposits->sum('amount');

        // ── Loan portfolio: standard loans by product, Locked-Up loans separately ──
        $lockedUpId = LoanProduct::where('name', 'Locked-Up Loans')->value('id');
        $running = fn () => Loan::whereIn('status', ['active', 'defaulted']);
        $standard = collect();
        foreach (LoanProduct::when($lockedUpId, fn ($q) => $q->where('id', '!=', $lockedUpId))->orderBy('name')->get(['id', 'name']) as $product) {
            $q = $running()->where('loan_product_id', $product->id);
            $row = ['label' => $product->name, 'count' => (clone $q)->count(),
                'principal' => (float) (clone $q)->sum('outstanding_principal'), 'interest' => (float) (clone $q)->sum('outstanding_interest')];
            if ($row['count']) {
                $standard->push($row);
            }
        }
        $lockedQ = $lockedUpId ? Loan::where('loan_product_id', $lockedUpId)->where('status', '!=', 'closed') : Loan::whereRaw('0 = 1');
        $lockedUp = ['count' => (clone $lockedQ)->count(), 'principal' => (float) (clone $lockedQ)->sum('outstanding_principal'),
            'interest' => (float) (clone $lockedQ)->sum('outstanding_interest')];
        $standardQ = fn () => $running()->when($lockedUpId, fn ($q) => $q->where('loan_product_id', '!=', $lockedUpId));
        $standardPrincipal = (float) $standard->sum('principal');

        // Arrears on standard loans (Locked-Up loans have no schedule)
        $overdueQ = fn (int $days) => $standardQ()->whereHas('schedules', fn ($q) => $q
            ->where('due_date', '<', now()->subDays($days)->toDateString())
            ->whereIn('status', ['pending', 'partial', 'overdue']));
        $overdueCount = $overdueQ(0)->count();
        $par30Amount  = (float) $overdueQ(30)->sum('outstanding_principal');
        $par30        = $standardPrincipal > 0 ? round($par30Amount / $standardPrincipal * 100, 1) : 0;
        $maturedCount = $standardQ()->whereDate('maturity_date', '<', today())->where('outstanding_principal', '>', 0)->count();
        $pendingLoans = Loan::where('status', 'pending')->count();

        // ── Headline ratios / members ──
        $loanToDeposit = $totalDeposits > 0 ? round(($standardPrincipal + $lockedUp['principal']) / $totalDeposits * 100, 1) : 0;
        $activeMembers = Client::where('status', 'active')->count();
        $borrowers     = $running()->distinct('client_id')->count('client_id');
        $savers        = SavingsAccount::where('status', 'active')->where('balance', '>', 0)->distinct('client_id')->count('client_id');

        // ── This month ──
        $monthStart = now()->startOfMonth()->toDateString();
        $realTx = fn ($type) => SavingsTransaction::where('transaction_type', $type)
            ->where(fn ($q) => $q->whereNull('description')->orWhere('description', 'not like', self::OPENING_DESC));
        $month = [
            'deposits'    => (float) $realTx('deposit')->where('transaction_date', '>=', $monthStart)->sum('amount'),
            'withdrawals' => (float) $realTx('withdrawal')->where('transaction_date', '>=', $monthStart)->sum('amount'),
            'disbursed'   => (float) Loan::whereIn('status', ['active', 'defaulted', 'closed'])
                ->when($lockedUpId, fn ($q) => $q->where('loan_product_id', '!=', $lockedUpId))
                ->where('disbursement_date', '>=', $monthStart)->sum('principal'),
            'recovered'   => (float) LoanRepayment::where('payment_date', '>=', $monthStart)->sum('amount'),
            'new_accounts' => SavingsAccount::where('opened_date', '>=', $monthStart)
                ->whereDoesntHave('transactions', fn ($q) => $q->where('description', 'like', self::OPENING_DESC))->count(),
            'new_members' => Client::where('created_at', '>=', $monthStart)->count(),
        ];

        // ── 6-month activity trend (opening balances excluded) ──
        $months = collect(range(5, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
        $trend = [
            'labels'      => $months->map(fn ($m) => $m->format('M Y')),
            'deposits'    => $months->map(fn ($m) => (float) $realTx('deposit')->whereBetween('transaction_date', [$m->toDateString(), $m->copy()->endOfMonth()->toDateString()])->sum('amount')),
            'withdrawals' => $months->map(fn ($m) => (float) $realTx('withdrawal')->whereBetween('transaction_date', [$m->toDateString(), $m->copy()->endOfMonth()->toDateString()])->sum('amount')),
            'disbursed'   => $months->map(fn ($m) => (float) Loan::whereIn('status', ['active', 'defaulted', 'closed'])
                ->when($lockedUpId, fn ($q) => $q->where('loan_product_id', '!=', $lockedUpId))
                ->where('disbursement_date', '>', Carbon::parse('2026-07-31'))
                ->whereBetween('disbursement_date', [$m->toDateString(), $m->copy()->endOfMonth()->toDateString()])->sum('principal')),
            'recovered'   => $months->map(fn ($m) => (float) LoanRepayment::whereBetween('payment_date', [$m->toDateString(), $m->copy()->endOfMonth()->toDateString()])->sum('amount')),
        ];

        // ── Other ──
        $shareCapital = (float) MemberShare::where('status', '!=', 'liquidated')->sum('amount_paid');
        $shareholders = MemberShare::where('status', '!=', 'liquidated')->distinct('client_id')->count('client_id');
        $dormant = SavingsAccount::where('status', 'active')
            ->whereDoesntHave('transactions', fn ($q) => $q->where('transaction_date', '>=', now()->subMonths(6)->toDateString()))->count();
        $overdrawn = SavingsAccount::where('status', 'active')->where('balance', '<', 0);
        $other = [
            'share_capital' => $shareCapital, 'shareholders' => $shareholders,
            'groups' => $groups->count(), 'group_members' => \App\Models\GroupMember::where('status', 'active')->count(),
            'dormant' => $dormant, 'overdrawn_count' => (clone $overdrawn)->count(), 'overdrawn_amount' => (float) (clone $overdrawn)->sum('balance'),
            'fees_unpaid' => Client::where('status', 'active')->where('membership_fee_status', '!=', 'paid')->count(),
        ];

        $upcomingMaturities = FixedDeposit::with('client')->where('status', 'active')
            ->where('maturity_date', '<=', now()->addDays(30)->toDateString())
            ->orderBy('maturity_date')->take(6)->get();

        return view('dashboard', compact(
            'deposits', 'totalDeposits', 'standard', 'lockedUp', 'standardPrincipal',
            'overdueCount', 'par30', 'par30Amount', 'maturedCount', 'pendingLoans',
            'loanToDeposit', 'activeMembers', 'borrowers', 'savers',
            'month', 'trend', 'other', 'upcomingMaturities'
        ));
    }
}
