<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\GroupMember;
use App\Models\GroupTransaction;
use App\Models\SavingsAccount;
use App\Services\GroupService;
use Carbon\Carbon;

class GroupPortalController extends Controller
{
    public function __construct(protected GroupService $groupService) {}
    /** Resolve the authenticated leader + group. */
    private function resolveLeader(): array
    {
        $leader = GroupMember::where('user_id', auth()->id())
            ->where('is_leader', true)
            ->with('group')
            ->first();

        if (!$leader) {
            abort(403, 'No group leader profile linked to this account.');
        }

        return [$leader, $leader->group];
    }

    private function leaderAnalytics($group, $members): array
    {
        // Last deposit date
        $lastDeposit = GroupTransaction::where('group_id', $group->id)
            ->where('type', 'deposit')
            ->latest('transaction_date')
            ->value('transaction_date');

        // Deposits this month
        $monthlyDeposits = GroupTransaction::where('group_id', $group->id)
            ->where('type', 'deposit')
            ->whereMonth('transaction_date', now()->month)
            ->whereYear('transaction_date', now()->year)
            ->sum('amount');

        // Per-member last deposit date
        $lastDepositByMember = GroupTransaction::where('group_id', $group->id)
            ->where('type', 'deposit')
            ->selectRaw('member_id, MAX(transaction_date) as last_date')
            ->groupBy('member_id')
            ->pluck('last_date', 'member_id');

        // Top 3 / bottom 3 (all members including leader)
        $topMembers    = $members->sortByDesc('balance')->take(3);
        $bottomMembers = $members->sortBy('balance')->take(3);

        // Watch list (all members)
        $zeroBalance = $members->filter(fn($m) => (float) $m->balance <= 0);
        $recentMemberIds = GroupTransaction::where('group_id', $group->id)
            ->where('type', 'deposit')
            ->where('transaction_date', '>=', now()->subDays(60))
            ->pluck('member_id')->unique();
        $inactiveDepositors = $members->whereNotIn('id', $recentMemberIds->toArray());

        // Monthly deposit trend – last 6 months
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->startOfMonth()->subMonths($i);
            $trend[] = [
                'label'  => $month->format('M Y'),
                'amount' => (float) GroupTransaction::where('group_id', $group->id)
                    ->where('type', 'deposit')
                    ->whereMonth('transaction_date', $month->month)
                    ->whereYear('transaction_date', $month->year)
                    ->sum('amount'),
            ];
        }

        return compact(
            'lastDeposit', 'monthlyDeposits',
            'topMembers', 'bottomMembers',
            'zeroBalance', 'inactiveDepositors', 'lastDepositByMember',
            'trend'
        );
    }

    public function leaderDashboard()
    {
        session(['active_portal' => 'leader']);
        [$leader, $group] = $this->resolveLeader();
        $members = $group->activeMembers()->orderByDesc('balance')->get();
        $analytics = $this->leaderAnalytics($group, $members);

        return view('group-portal.leader', array_merge(
            compact('group', 'leader', 'members'),
            $analytics
        ));
    }

    public function leaderMembers()
    {
        [$leader, $group] = $this->resolveLeader();
        $members = $group->members()->orderByDesc('is_leader')->orderByDesc('balance')->get();

        $lastDepositByMember = GroupTransaction::where('group_id', $group->id)
            ->where('type', 'deposit')
            ->selectRaw('member_id, MAX(transaction_date) as last_date')
            ->groupBy('member_id')
            ->pluck('last_date', 'member_id');

        $totalDeposited = GroupTransaction::where('group_id', $group->id)
            ->where('type', 'deposit')
            ->selectRaw('member_id, SUM(amount) as total')
            ->groupBy('member_id')
            ->pluck('total', 'member_id');

        return view('group-portal.leader-members', compact('group', 'leader', 'members', 'lastDepositByMember', 'totalDeposited'));
    }

    public function leaderActivity()
    {
        [$leader, $group] = $this->resolveLeader();

        $transactions = GroupTransaction::where('group_id', $group->id)
            ->with('member')
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(30);

        return view('group-portal.leader-activity', compact('group', 'leader', 'transactions'));
    }

    public function memberStatement(GroupMember $member)
    {
        [$leader, $group] = $this->resolveLeader();
        abort_if($member->group_id !== $group->id, 403);

        $transactions = GroupTransaction::where('group_id', $group->id)
            ->where('member_id', $member->id)
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(30);

        return view('group-portal.leader-member-statement', compact('group', 'leader', 'member', 'transactions'));
    }

    public function memberStatementPdf(GroupMember $member)
    {
        [$leader, $group] = $this->resolveLeader();
        abort_if((int) $member->group_id !== (int) $group->id, 403);

        $transactions = GroupTransaction::where('group_id', $group->id)
            ->where('member_id', $member->id)
            ->latest('transaction_date')
            ->latest('id')
            ->get();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.group-member-statement', compact('group', 'member', 'transactions'))
            ->setPaper('a4', 'portrait');

        return $pdf->download("group-statement-{$group->group_number}-{$member->name}.pdf");
    }

    private function resolveMember(): array
    {
        $member = GroupMember::where('user_id', auth()->id())
            ->with('group')
            ->first();

        if (!$member) {
            abort(403, 'No group member profile linked to this account.');
        }

        return [$member, $member->group];
    }

    public function memberOverview()
    {
        [$member, $group] = $this->resolveMember();

        $totalDeposited = GroupTransaction::where('group_id', $group->id)
            ->where('member_id', $member->id)
            ->where('type', 'deposit')
            ->sum('amount');

        $totalWithdrawn = GroupTransaction::where('group_id', $group->id)
            ->where('member_id', $member->id)
            ->where('type', 'withdrawal')
            ->sum('amount');

        $lastDeposit = GroupTransaction::where('group_id', $group->id)
            ->where('member_id', $member->id)
            ->where('type', 'deposit')
            ->latest('transaction_date')
            ->value('transaction_date');

        // Monthly trend — last 6 months
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->startOfMonth()->subMonths($i);
            $trend[] = [
                'label'  => $month->format('M Y'),
                'amount' => (float) GroupTransaction::where('group_id', $group->id)
                    ->where('member_id', $member->id)
                    ->where('type', 'deposit')
                    ->whereMonth('transaction_date', $month->month)
                    ->whereYear('transaction_date', $month->year)
                    ->sum('amount'),
            ];
        }

        $recentTx = GroupTransaction::where('group_id', $group->id)
            ->where('member_id', $member->id)
            ->latest('transaction_date')->latest('id')
            ->limit(6)->get();

        // All active savings accounts available as transfer targets
        $savingsAccounts = SavingsAccount::where('status', 'active')
            ->with(['product', 'client'])
            ->orderBy('account_number')
            ->get();

        return view('group-portal.member-overview', compact(
            'group', 'member', 'totalDeposited', 'totalWithdrawn', 'lastDeposit', 'trend', 'recentTx', 'savingsAccounts'
        ));
    }

    public function transferToSavings(\Illuminate\Http\Request $request)
    {
        [$member, $group] = $this->resolveMember();

        $data = $request->validate([
            'savings_account_id' => 'required|exists:savings_accounts,id',
            'amount'             => 'required|numeric|min:0.01',
            'transfer_date'      => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
            'notes'              => 'nullable|string|max:255',
        ]);

        $savingsAccount = SavingsAccount::findOrFail($data['savings_account_id']);

        try {
            $this->groupService->transferToSavings(
                $group, $member, $savingsAccount,
                (float) $data['amount'],
                $data['transfer_date'],
                $data['notes'] ?? ''
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Transfer of ' . number_format($data['amount'], 2) . ' completed successfully.');
    }

    public function memberDashboard()
    {
        session(['active_portal' => 'member']);
        [$member, $group] = $this->resolveMember();

        $transactions = GroupTransaction::where('group_id', $group->id)
            ->where('member_id', $member->id)
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(30);

        return view('group-portal.member', compact('group', 'member', 'transactions'));
    }
}
