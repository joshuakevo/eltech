<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\SavingsAccount;
use App\Services\GroupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use App\Models\User;

class GroupController extends Controller
{
    public function __construct(protected GroupService $groupService) {}

    public function index(Request $request)
    {
        $groups = Group::query()
            ->withCount('activeMembers')
            ->withSum('members', 'balance')
            ->when($request->search, function ($q) use ($request) {
                $s = $request->search;
                $q->where('group_number', 'like', "%{$s}%")
                  ->orWhere('name', 'like', "%{$s}%");
            })
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderBy('name')
            ->paginate(20);

        return view('groups.index', compact('groups'));
    }

    public function create()
    {
        return redirect()->route('clients.create', ['type' => 'group']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                  => 'required|string|max:200',
            'group_type'            => 'required|in:savings,pooled',
            'registration_date'     => 'required|date',
            'membership_fee'        => 'required|numeric|min:0',
            'monthly_interest_rate' => 'required_if:group_type,savings|nullable|numeric|min:0|max:100',
            'expected_contribution' => 'required_if:group_type,pooled|nullable|numeric|min:0',
            'contribution_cycle'    => 'required_if:group_type,pooled|nullable|in:weekly,biweekly,monthly,quarterly',
            'gl_account_id'         => 'nullable|exists:accounts,id',
            'notes'                 => 'nullable|string|max:2000',
        ]);

        $group = $this->groupService->createGroup($data);

        return redirect()->route('groups.show', $group)->with('success', "Group {$group->group_number} created.");
    }

    public function show(Group $group)
    {
        $group->load(['glAccount']);
        $members = $group->members()->with('user')->orderByDesc('is_leader')->orderBy('name')->get();
        $transactions = $group->transactions()
            ->with('member')
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(25);
        $clients = \App\Models\Client::where('status', 'active')->where('client_type', 'individual')->orderBy('name')->get();

        $allSavingsAccounts = SavingsAccount::where('status', 'active')
            ->with(['product', 'client'])
            ->orderBy('account_number')
            ->get();

        // Pooled group: contribution status per member for the current cycle
        $memberContribStatus = collect();
        if ($group->isPooled() && $group->contribution_cycle) {
            [$cycleStart, $cycleEnd] = $this->getCycleDateRange($group->contribution_cycle);
            foreach ($members->where('status', 'active') as $member) {
                $paidThisCycle = \App\Models\GroupTransaction::where('group_id', $group->id)
                    ->where('member_id', $member->id)
                    ->where('type', 'deposit')
                    ->whereBetween('transaction_date', [$cycleStart, $cycleEnd])
                    ->sum('amount');

                $lastPayment = \App\Models\GroupTransaction::where('group_id', $group->id)
                    ->where('member_id', $member->id)
                    ->where('type', 'deposit')
                    ->latest('transaction_date')->latest('id')
                    ->value('transaction_date');

                $expected = (float) $group->expected_contribution;
                $memberContribStatus[$member->id] = [
                    'paid_this_cycle' => (float) $paidThisCycle,
                    'last_payment'    => $lastPayment,
                    'status'          => $paidThisCycle <= 0 ? 'unpaid'
                        : ($paidThisCycle < $expected ? 'partial' : 'paid'),
                ];
            }
        }

        return view('groups.show', compact('group', 'members', 'transactions', 'clients', 'allSavingsAccounts', 'memberContribStatus'));
    }

    private function getCycleDateRange(string $cycle): array
    {
        $now = now();
        return match ($cycle) {
            'weekly'    => [$now->startOfWeek()->toDateString(), $now->endOfWeek()->toDateString()],
            'biweekly'  => $now->day <= 15
                ? [$now->copy()->startOfMonth()->toDateString(), $now->copy()->startOfMonth()->addDays(14)->toDateString()]
                : [$now->copy()->startOfMonth()->addDays(15)->toDateString(), $now->copy()->endOfMonth()->toDateString()],
            'quarterly' => [$now->copy()->firstOfQuarter()->toDateString(), $now->copy()->lastOfQuarter()->toDateString()],
            default     => [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()],
        };
    }

    public function edit(Group $group)
    {
        $glAccounts = Account::where('account_type', 'liability')->where('is_active', true)->orderBy('account_code')->get();
        return view('groups.edit', compact('group', 'glAccounts'));
    }

    public function update(Request $request, Group $group)
    {
        $data = $request->validate([
            'name'                  => 'required|string|max:200',
            'registration_date'     => 'required|date',
            'membership_fee'        => 'required|numeric|min:0',
            'monthly_interest_rate' => 'nullable|numeric|min:0|max:100',
            'expected_contribution' => 'nullable|numeric|min:0',
            'contribution_cycle'    => 'nullable|in:weekly,biweekly,monthly,quarterly',
            'gl_account_id'         => 'nullable|exists:accounts,id',
            'status'                => 'required|in:active,inactive,dissolved',
            'notes'                 => 'nullable|string|max:2000',
        ]);

        $this->groupService->updateGroup($group, $data);

        return redirect()->route('groups.show', $group)->with('success', 'Group updated.');
    }

    // ── Pooled Group Actions ──────────────────────────────────────────────

    public function contributeForm(Group $group)
    {
        abort_unless($group->isPooled(), 404);
        $members = $group->activeMembers()->orderByDesc('is_leader')->orderBy('name')->get();
        return view('groups.contribute', compact('group', 'members'));
    }

    public function contribute(Request $request, Group $group)
    {
        abort_unless($group->isPooled(), 404);

        $data = $request->validate([
            'member_id'        => 'required|exists:group_members,id',
            'amount'           => 'required|numeric|min:0.01',
            'transaction_date' => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
            'reference'        => 'required|string|max:100|unique:transactions,reference',
            'notes'            => 'nullable|string|max:500',
        ]);

        $member = GroupMember::where('group_id', $group->id)->where('id', $data['member_id'])->firstOrFail();

        try {
            $this->groupService->contributePooled(
                $group, $member,
                (float) $data['amount'],
                $data['transaction_date'],
                $data['reference'] ?? '',
                $data['notes'] ?? ''
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }

        return redirect()->route('groups.show', $group)->with('success', "Contribution of " . number_format($data['amount'], 2) . " recorded for {$member->name}.");
    }

    public function poolWithdrawForm(Group $group)
    {
        abort_unless($group->isPooled(), 404);
        return view('groups.pool-withdraw', compact('group'));
    }

    public function poolWithdraw(Request $request, Group $group)
    {
        abort_unless($group->isPooled(), 404);

        $data = $request->validate([
            'amount'           => 'required|numeric|min:0.01',
            'purpose'          => 'required|string|max:255',
            'transaction_date' => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
            'reference'        => 'required|string|max:100|unique:transactions,reference',
        ]);

        try {
            $this->groupService->withdrawPooled(
                $group,
                (float) $data['amount'],
                $data['transaction_date'],
                $data['purpose'],
                $data['reference'] ?? ''
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }

        return redirect()->route('groups.show', $group)->with('success', "Pool withdrawal of " . number_format($data['amount'], 2) . " posted.");
    }

    // ── Members ───────────────────────────────────────────────────────────

    public function storeMember(Request $request, Group $group)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:200',
            'phone'        => 'nullable|string|max:30',
            'email'        => 'nullable|email|max:255',
            'national_id'  => 'nullable|string|max:50',
            'is_leader'    => 'boolean',
            'portal_email' => 'nullable|email|max:255',
        ]);

        try {
            $this->groupService->addMember($group, $data);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['portal_email' => $e->getMessage()])->withInput();
        }

        return redirect()->route('groups.show', $group)->with('success', 'Member added.');
    }

    public function updateMember(Request $request, Group $group, GroupMember $member)
    {
        abort_unless($member->group_id === $group->id, 404);

        $data = $request->validate([
            'name'            => 'required|string|max:200',
            'phone'           => 'nullable|string|max:30',
            'email'           => 'nullable|email|max:255',
            'national_id'     => 'nullable|string|max:50',
            'is_leader'       => 'boolean',
            'portal_email'    => 'nullable|email|max:255',
            'portal_password' => 'nullable|string|min:8|confirmed',
        ]);

        $member->update([
            'name'        => $data['name'],
            'phone'       => $data['phone'] ?? null,
            'email'       => $data['email'] ?? null,
            'national_id' => $data['national_id'] ?? null,
            'is_leader'   => $request->boolean('is_leader'),
        ]);

        // Update or create portal user
        if ($member->user_id) {
            $user = User::find($member->user_id);
            if ($user) {
                $upd = ['name' => $data['name']];
                if (!empty($data['portal_email'])) $upd['email'] = $data['portal_email'];
                if ($request->filled('portal_password')) $upd['password'] = Hash::make($data['portal_password']);
                $user->update($upd);
                $user->syncRoles([$request->boolean('is_leader') ? 'group_leader' : 'group_member']);
            }
        } elseif (!empty($data['portal_email']) && $request->filled('portal_password')) {
            if (User::where('email', $data['portal_email'])->exists()) {
                return back()->withErrors(['portal_email' => 'This email is already registered.'])->withInput();
            }
            $user = User::create([
                'name'      => $data['name'],
                'email'     => $data['portal_email'],
                'password'  => Hash::make($data['portal_password']),
                'is_active' => true,
            ]);
            $user->assignRole($request->boolean('is_leader') ? 'group_leader' : 'group_member');
            $member->update(['user_id' => $user->id]);
        }

        return redirect()->route('groups.show', $group)->with('success', 'Member updated.');
    }

    public function sendMemberReset(Group $group, GroupMember $member)
    {
        abort_unless($member->group_id === $group->id, 404);

        if (!$member->user_id || !$member->user) {
            return back()->with('error', 'This member has no portal account.');
        }

        $status = Password::sendResetLink(['email' => $member->user->email]);

        if ($status === Password::RESET_LINK_SENT) {
            $msg = "Password reset link sent to {$member->user->email}.";
            return request()->wantsJson()
                ? response()->json(['success' => true, 'message' => $msg])
                : back()->with('success', $msg);
        }

        $err = 'Failed to send reset link. Please try again.';
        return request()->wantsJson()
            ? response()->json(['success' => false, 'message' => $err], 422)
            : back()->with('error', $err);
    }

    public function memberStatement(Group $group, GroupMember $member)
    {
        abort_unless($member->group_id === $group->id, 404);

        $transactions = \App\Models\GroupTransaction::where('group_id', $group->id)
            ->where('member_id', $member->id)
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(50);

        $totalDeposited  = \App\Models\GroupTransaction::where('group_id', $group->id)->where('member_id', $member->id)->where('type', 'deposit')->sum('amount');
        $totalWithdrawn  = \App\Models\GroupTransaction::where('group_id', $group->id)->where('member_id', $member->id)->whereIn('type', ['withdrawal', 'transfer_out'])->sum('amount');
        $totalInterest   = \App\Models\GroupTransaction::where('group_id', $group->id)->where('member_id', $member->id)->where('type', 'interest')->sum('amount');

        return view('groups.member-statement', compact('group', 'member', 'transactions', 'totalDeposited', 'totalWithdrawn', 'totalInterest'));
    }

    public function transferToSavings(Request $request, Group $group, GroupMember $member)
    {
        abort_unless($member->group_id === $group->id, 404);

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

        return back()->with('success', "Transferred " . number_format($data['amount'], 2) . " from {$member->name}'s group balance to savings account {$savingsAccount->account_number}.");
    }

    public function destroy(Group $group)
    {
        // Block if any member has a non-zero balance
        $membersWithBalance = $group->activeMembers()->where('balance', '>', 0)->count();
        if ($membersWithBalance) {
            return back()->with('error',
                'Cannot delete group — ' . $membersWithBalance . ' member(s) still have a balance. Clear all balances first.'
            );
        }

        // Block if group has any transactions
        $txnCount = $group->transactions()->count();
        if ($txnCount) {
            return back()->with('error',
                'Cannot delete group — it has ' . $txnCount . ' transaction(s) on record.'
            );
        }

        \DB::transaction(function () use ($group) {
            // Deactivate portal users linked to group members
            $group->members()->each(function ($member) {
                if ($member->user_id) {
                    User::where('id', $member->user_id)->update(['is_active' => false]);
                }
            });

            $group->members()->delete();

            // Delete the linked group-type client
            if ($group->client_id) {
                $client = \App\Models\Client::find($group->client_id);
                if ($client) {
                    $client->delete();
                }
            }

            $group->delete();
        });

        return redirect()->route('groups.index')->with('success', 'Group and associated client deleted.');
    }

    public function destroyMember(Group $group, GroupMember $member)
    {
        abort_unless($member->group_id === $group->id, 404);

        if ((float) $member->balance > 0.001) {
            return back()->with('error', 'Cannot remove a member with a non-zero balance.');
        }

        if ($member->user_id) {
            User::where('id', $member->user_id)->update(['is_active' => false]);
        }

        $member->delete();

        return redirect()->route('groups.show', $group)->with('success', 'Member removed.');
    }

    // ── Transactions ──────────────────────────────────────────────────────

    public function depositForm(Group $group)
    {
        $members = $group->activeMembers()->orderBy('name')->get();
        $paymentSourceAccounts = \App\Models\Account::where('is_payment_source', true)->where('is_active', true)->orderBy('account_code')->get();
        return view('groups.deposit', compact('group', 'members', 'paymentSourceAccounts'));
    }

    public function deposit(Request $request, Group $group)
    {
        $data = $request->validate([
            'mode'                     => 'required|in:individual,equal_split,custom',
            'amount'                   => 'required_unless:mode,custom|nullable|numeric|min:0.01',
            'transaction_date'         => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
            'reference'                => 'required|string|max:100|unique:transactions,reference',
            'payment_source_account_id'=> 'required|exists:accounts,id',
            'notes'                    => 'nullable|string|max:500',
            'member_id'                => 'required_if:mode,individual|nullable|exists:group_members,id',
            'custom_amounts'           => 'required_if:mode,custom|nullable|array',
            'custom_amounts.*'         => 'nullable|numeric|min:0',
        ]);

        try {
            if ($data['mode'] === 'individual') {
                $member = GroupMember::where('group_id', $group->id)->where('id', $data['member_id'])->firstOrFail();
                $this->groupService->depositIndividual($group, $member, (float) $data['amount'], $data['transaction_date'], $data['notes'] ?? 'Group deposit');
            } elseif ($data['mode'] === 'equal_split') {
                $this->groupService->depositGroupWideEqual($group, (float) $data['amount'], $data['transaction_date'], $data['notes'] ?? 'Group-wide deposit');
            } else {
                $amounts = [];
                foreach ($data['custom_amounts'] ?? [] as $mid => $amt) {
                    if ($amt > 0) $amounts[(int) $mid] = round((float) $amt, 2);
                }
                $this->groupService->depositGroupWideCustom($group, $amounts, $data['transaction_date'], $data['notes'] ?? 'Group deposit');
            }
        } catch (\Throwable $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }

        return redirect()->route('groups.show', $group)->with('success', 'Deposit posted.');
    }

    public function withdrawalForm(Group $group)
    {
        $members = $group->activeMembers()->orderBy('name')->get();
        $paymentSourceAccounts = \App\Models\Account::where('is_payment_source', true)->where('is_active', true)->orderBy('account_code')->get();
        return view('groups.withdrawal', compact('group', 'members', 'paymentSourceAccounts'));
    }

    public function withdrawal(Request $request, Group $group)
    {
        $data = $request->validate([
            'mode'                     => 'required|in:individual,equal_split,custom',
            'amount'                   => 'required_unless:mode,custom|nullable|numeric|min:0.01',
            'transaction_date'         => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
            'reference'                => 'required|string|max:100|unique:transactions,reference',
            'payment_source_account_id'=> 'required|exists:accounts,id',
            'notes'                    => 'nullable|string|max:500',
            'member_id'                => 'required_if:mode,individual|nullable|exists:group_members,id',
            'custom_amounts'           => 'required_if:mode,custom|nullable|array',
            'custom_amounts.*'         => 'nullable|numeric|min:0',
        ]);

        try {
            if ($data['mode'] === 'individual') {
                $member = GroupMember::where('group_id', $group->id)->where('id', $data['member_id'])->firstOrFail();
                $this->groupService->withdrawIndividual($group, $member, (float) $data['amount'], $data['transaction_date'], $data['notes'] ?? 'Group withdrawal');
            } elseif ($data['mode'] === 'equal_split') {
                $this->groupService->withdrawGroupWideEqual($group, (float) $data['amount'], $data['transaction_date'], $data['notes'] ?? 'Group-wide withdrawal');
            } else {
                $amounts = [];
                foreach ($data['custom_amounts'] ?? [] as $mid => $amt) {
                    if ($amt > 0) $amounts[(int) $mid] = round((float) $amt, 2);
                }
                $this->groupService->withdrawGroupWideCustom($group, $amounts, $data['transaction_date'], $data['notes'] ?? 'Group-wide withdrawal');
            }
        } catch (\Throwable $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }

        return redirect()->route('groups.show', $group)->with('success', 'Withdrawal posted.');
    }

    public function interestForm(Group $group)
    {
        return view('groups.interest', compact('group'));
    }

    public function interest(Request $request, Group $group)
    {
        $data = $request->validate([
            'transaction_date' => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
            'notes'            => 'nullable|string|max:500',
        ]);

        try {
            $this->groupService->postMonthlyInterest($group, $data['transaction_date'], $data['notes'] ?? null);
        } catch (\Throwable $e) {
            return back()->withErrors(['interest' => $e->getMessage()])->withInput();
        }

        return redirect()->route('groups.show', $group)->with('success', 'Monthly interest posted to all member balances.');
    }
}
