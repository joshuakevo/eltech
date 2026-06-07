<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupTransaction;
use App\Models\SavingsAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class GroupService
{
    public function __construct(protected AccountingService $accounting) {}

    // ── Group CRUD ────────────────────────────────────────────────────────

    public function createGroup(array $data): Group
    {
        return DB::transaction(function () use ($data) {
            return Group::create([
                'group_number'          => Group::generateGroupNumber(),
                'name'                  => $data['name'],
                'group_type'            => $data['group_type'] ?? 'savings',
                'registration_date'     => $data['registration_date'],
                'membership_fee'        => $data['membership_fee'] ?? 0,
                'expected_contribution' => $data['expected_contribution'] ?? null,
                'contribution_cycle'    => $data['contribution_cycle'] ?? null,
                'monthly_interest_rate' => $data['monthly_interest_rate'] ?? 0,
                'gl_account_id'         => $data['gl_account_id'] ?? null,
                'status'                => 'active',
                'notes'                 => $data['notes'] ?? null,
                'created_by'            => auth()->id(),
            ]);
        });
    }

    public function updateGroup(Group $group, array $data): Group
    {
        $group->update([
            'name'                  => $data['name'],
            'registration_date'     => $data['registration_date'],
            'membership_fee'        => $data['membership_fee'] ?? $group->membership_fee,
            'expected_contribution' => $data['expected_contribution'] ?? $group->expected_contribution,
            'contribution_cycle'    => $data['contribution_cycle'] ?? $group->contribution_cycle,
            'monthly_interest_rate' => $data['monthly_interest_rate'] ?? $group->monthly_interest_rate,
            'gl_account_id'         => $data['gl_account_id'] ?? $group->gl_account_id,
            'status'                => $data['status'] ?? $group->status,
            'notes'                 => $data['notes'] ?? null,
        ]);

        // Keep the linked client record in sync with the group
        if ($group->client_id && $group->client) {
            $group->client->update([
                'name'   => $data['name'],
                'status' => $data['status'] ?? $group->status,
            ]);
        }

        return $group->fresh();
    }

    // ── Member Management ─────────────────────────────────────────────────

    public function addMember(Group $group, array $data): GroupMember
    {
        return DB::transaction(function () use ($group, $data) {
            $member = GroupMember::create([
                'group_id'    => $group->id,
                'client_id'   => $data['client_id'] ?? null,
                'name'        => $data['name'],
                'phone'       => $data['phone'] ?? null,
                'email'       => $data['email'] ?? null,
                'national_id' => $data['national_id'] ?? null,
                'balance'     => 0,
                'is_leader'   => !empty($data['is_leader']),
                'status'      => 'active',
                'created_by'  => auth()->id(),
            ]);

            if (!empty($data['portal_email'])) {
                $role = !empty($data['is_leader']) ? 'group_leader' : 'group_member';
                $user = User::firstOrCreate(
                    ['email' => $data['portal_email']],
                    [
                        'name'      => $data['name'],
                        'password'  => Hash::make('Welcome@123'),
                        'is_active' => true,
                    ]
                );
                if (!$user->hasRole($role)) {
                    $user->assignRole($role);
                }
                $member->update(['user_id' => $user->id]);
            }

            return $member;
        });
    }

    // ── Deposit ───────────────────────────────────────────────────────────

    public function depositIndividual(Group $group, GroupMember $member, float $amount, string $date, string $notes = 'Group deposit'): GroupTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Deposit amount must be positive.');
        }
        if ((int) $member->group_id !== (int) $group->id) {
            throw new \InvalidArgumentException('Member does not belong to this group.');
        }

        return DB::transaction(function () use ($group, $member, $amount, $date, $notes) {
            $balBefore = (float) $member->balance;
            $balAfter  = round($balBefore + $amount, 2);

            $journal = $this->accounting->post(
                $date,
                "Group deposit — {$group->name} — {$member->name}",
                [
                    ['account_id' => $this->getCashAccountId(),             'debit' => $amount, 'credit' => 0,      'description' => $notes],
                    ['account_id' => $this->getGroupLiabilityAccountId($group), 'debit' => 0,   'credit' => $amount, 'description' => "Group member savings — {$member->name}"],
                ],
                'groups',
                $group->id
            );

            $member->update(['balance' => $balAfter]);

            return GroupTransaction::create([
                'group_id'               => $group->id,
                'member_id'              => $member->id,
                'type'                   => 'deposit',
                'posting_type'           => 'individual',
                'amount'                 => $amount,
                'balance_before'         => $balBefore,
                'balance_after'          => $balAfter,
                'transaction_date'       => $date,
                'notes'                  => $notes,
                'journal_transaction_id' => $journal->id,
                'created_by'             => auth()->id(),
            ]);
        });
    }

    public function depositGroupWideEqual(Group $group, float $totalAmount, string $date, string $notes = 'Group-wide deposit'): array
    {
        if ($totalAmount <= 0) {
            throw new \InvalidArgumentException('Total deposit must be positive.');
        }

        $members = $group->activeMembers()->orderBy('id')->get();
        if ($members->isEmpty()) {
            throw new \InvalidArgumentException('No active members to allocate deposit to.');
        }

        $n         = $members->count();
        $base      = round(floor(($totalAmount / $n) * 100) / 100, 2);
        $allocated = 0.0;
        $amounts   = [];

        foreach ($members as $i => $m) {
            $amounts[$m->id] = ($i === $n - 1) ? round($totalAmount - $allocated, 2) : $base;
            $allocated       += $amounts[$m->id];
        }

        return DB::transaction(function () use ($group, $members, $amounts, $totalAmount, $date, $notes) {
            $journal = $this->accounting->post(
                $date,
                "Group-wide deposit — {$group->name}",
                [
                    ['account_id' => $this->getCashAccountId(),                 'debit' => $totalAmount, 'credit' => 0,           'description' => $notes],
                    ['account_id' => $this->getGroupLiabilityAccountId($group), 'debit' => 0,           'credit' => $totalAmount, 'description' => 'Group member savings — pooled'],
                ],
                'groups',
                $group->id
            );

            $txns = [];
            foreach ($members as $member) {
                $amt       = $amounts[$member->id];
                $balBefore = (float) $member->balance;
                $balAfter  = round($balBefore + $amt, 2);
                $member->update(['balance' => $balAfter]);

                $txns[] = GroupTransaction::create([
                    'group_id'               => $group->id,
                    'member_id'              => $member->id,
                    'type'                   => 'deposit',
                    'posting_type'           => 'equal_split',
                    'amount'                 => $amt,
                    'balance_before'         => $balBefore,
                    'balance_after'          => $balAfter,
                    'transaction_date'       => $date,
                    'notes'                  => $notes,
                    'journal_transaction_id' => $journal->id,
                    'created_by'             => auth()->id(),
                ]);
            }
            return $txns;
        });
    }

    public function depositGroupWideCustom(Group $group, array $amounts, string $date, string $notes = 'Group deposit'): array
    {
        $totalAmount = round(array_sum($amounts), 2);
        if ($totalAmount <= 0) {
            throw new \InvalidArgumentException('Total deposit amount must be positive.');
        }

        return DB::transaction(function () use ($group, $amounts, $totalAmount, $date, $notes) {
            $journal = $this->accounting->post(
                $date,
                "Group deposit (custom) — {$group->name}",
                [
                    ['account_id' => $this->getCashAccountId(),                 'debit' => $totalAmount, 'credit' => 0,           'description' => $notes],
                    ['account_id' => $this->getGroupLiabilityAccountId($group), 'debit' => 0,           'credit' => $totalAmount, 'description' => 'Group member savings'],
                ],
                'groups',
                $group->id
            );

            $txns = [];
            foreach ($amounts as $memberId => $amount) {
                if ($amount <= 0) continue;
                $member    = GroupMember::findOrFail($memberId);
                $balBefore = (float) $member->balance;
                $balAfter  = round($balBefore + $amount, 2);
                $member->update(['balance' => $balAfter]);

                $txns[] = GroupTransaction::create([
                    'group_id'               => $group->id,
                    'member_id'              => $member->id,
                    'type'                   => 'deposit',
                    'posting_type'           => 'custom',
                    'amount'                 => $amount,
                    'balance_before'         => $balBefore,
                    'balance_after'          => $balAfter,
                    'transaction_date'       => $date,
                    'notes'                  => $notes,
                    'journal_transaction_id' => $journal->id,
                    'created_by'             => auth()->id(),
                ]);
            }
            return $txns;
        });
    }

    // ── Withdrawal ────────────────────────────────────────────────────────

    public function withdrawIndividual(Group $group, GroupMember $member, float $amount, string $date, string $notes = 'Group withdrawal'): GroupTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Withdrawal amount must be positive.');
        }
        if ((int) $member->group_id !== (int) $group->id) {
            throw new \InvalidArgumentException('Member does not belong to this group.');
        }
        if ((float) $member->balance < $amount - 0.001) {
            throw new \InvalidArgumentException('Insufficient member balance.');
        }

        return DB::transaction(function () use ($group, $member, $amount, $date, $notes) {
            $balBefore = (float) $member->balance;
            $balAfter  = round($balBefore - $amount, 2);

            $journal = $this->accounting->post(
                $date,
                "Group withdrawal — {$group->name} — {$member->name}",
                [
                    ['account_id' => $this->getGroupLiabilityAccountId($group), 'debit' => $amount, 'credit' => 0,      'description' => "Reduce savings — {$member->name}"],
                    ['account_id' => $this->getCashAccountId(),                 'debit' => 0,       'credit' => $amount, 'description' => $notes],
                ],
                'groups',
                $group->id
            );

            $member->update(['balance' => $balAfter]);

            return GroupTransaction::create([
                'group_id'               => $group->id,
                'member_id'              => $member->id,
                'type'                   => 'withdrawal',
                'posting_type'           => 'individual',
                'amount'                 => $amount,
                'balance_before'         => $balBefore,
                'balance_after'          => $balAfter,
                'transaction_date'       => $date,
                'notes'                  => $notes,
                'journal_transaction_id' => $journal->id,
                'created_by'             => auth()->id(),
            ]);
        });
    }

    public function withdrawGroupWideEqual(Group $group, float $totalAmount, string $date, string $notes = 'Group-wide withdrawal'): array
    {
        if ($totalAmount <= 0) {
            throw new \InvalidArgumentException('Total withdrawal must be positive.');
        }

        $members = $group->activeMembers()->orderBy('id')->get();
        if ($members->isEmpty()) {
            throw new \InvalidArgumentException('No active members in group.');
        }

        $n         = $members->count();
        $base      = round(floor(($totalAmount / $n) * 100) / 100, 2);
        $allocated = 0.0;
        $amounts   = [];

        foreach ($members as $i => $m) {
            $amounts[$m->id] = ($i === $n - 1) ? round($totalAmount - $allocated, 2) : $base;
            $allocated       += $amounts[$m->id];
        }

        foreach ($members as $m) {
            if ((float) $m->balance < $amounts[$m->id] - 0.001) {
                throw new \InvalidArgumentException("Equal split requires each member to have at least their share. Insufficient: {$m->name}");
            }
        }

        return DB::transaction(function () use ($group, $members, $amounts, $totalAmount, $date, $notes) {
            $journal = $this->accounting->post(
                $date,
                "Group-wide withdrawal — {$group->name} (equal)",
                [
                    ['account_id' => $this->getGroupLiabilityAccountId($group), 'debit' => $totalAmount, 'credit' => 0,           'description' => 'Group savings payout'],
                    ['account_id' => $this->getCashAccountId(),                 'debit' => 0,            'credit' => $totalAmount, 'description' => $notes],
                ],
                'groups',
                $group->id
            );

            $txns = [];
            foreach ($members as $member) {
                $amt       = $amounts[$member->id];
                $balBefore = (float) $member->balance;
                $balAfter  = round($balBefore - $amt, 2);
                $member->update(['balance' => $balAfter]);

                $txns[] = GroupTransaction::create([
                    'group_id'               => $group->id,
                    'member_id'              => $member->id,
                    'type'                   => 'withdrawal',
                    'posting_type'           => 'equal_split',
                    'amount'                 => $amt,
                    'balance_before'         => $balBefore,
                    'balance_after'          => $balAfter,
                    'transaction_date'       => $date,
                    'notes'                  => $notes,
                    'journal_transaction_id' => $journal->id,
                    'created_by'             => auth()->id(),
                ]);
            }
            return $txns;
        });
    }

    public function withdrawGroupWideCustom(Group $group, array $memberIdToAmount, string $date, string $notes = 'Group-wide withdrawal'): array
    {
        if (empty($memberIdToAmount)) {
            throw new \InvalidArgumentException('No withdrawal amounts provided.');
        }

        $totalAmount = round(array_sum($memberIdToAmount), 2);
        if ($totalAmount <= 0) {
            throw new \InvalidArgumentException('Total withdrawal must be positive.');
        }

        $members = $group->activeMembers()->whereIn('id', array_keys($memberIdToAmount))->get()->keyBy('id');
        foreach ($memberIdToAmount as $mid => $amt) {
            $member = $members->get($mid);
            if (!$member) {
                throw new \InvalidArgumentException('Invalid member id.');
            }
            if ((float) $member->balance < $amt - 0.001) {
                throw new \InvalidArgumentException("Insufficient balance for {$member->name}.");
            }
        }

        return DB::transaction(function () use ($group, $memberIdToAmount, $members, $totalAmount, $date, $notes) {
            $journal = $this->accounting->post(
                $date,
                "Group-wide withdrawal — {$group->name} (custom)",
                [
                    ['account_id' => $this->getGroupLiabilityAccountId($group), 'debit' => $totalAmount, 'credit' => 0,           'description' => 'Group savings payout'],
                    ['account_id' => $this->getCashAccountId(),                 'debit' => 0,            'credit' => $totalAmount, 'description' => $notes],
                ],
                'groups',
                $group->id
            );

            $txns = [];
            foreach ($memberIdToAmount as $memberId => $amt) {
                if ($amt <= 0) continue;
                $member    = $members->get($memberId);
                $balBefore = (float) $member->balance;
                $balAfter  = round($balBefore - $amt, 2);
                $member->update(['balance' => $balAfter]);

                $txns[] = GroupTransaction::create([
                    'group_id'               => $group->id,
                    'member_id'              => $member->id,
                    'type'                   => 'withdrawal',
                    'posting_type'           => 'custom',
                    'amount'                 => $amt,
                    'balance_before'         => $balBefore,
                    'balance_after'          => $balAfter,
                    'transaction_date'       => $date,
                    'notes'                  => $notes,
                    'journal_transaction_id' => $journal->id,
                    'created_by'             => auth()->id(),
                ]);
            }
            return $txns;
        });
    }

    // ── Monthly Interest ──────────────────────────────────────────────────

    public function postMonthlyInterest(Group $group, string $date, ?string $notes = null): array
    {
        $rate = (float) $group->monthly_interest_rate;
        if ($rate <= 0) {
            throw new \InvalidArgumentException('Group monthly interest rate is not set.');
        }

        $members = $group->activeMembers()->where('balance', '>', 0)->orderBy('id')->get();
        if ($members->isEmpty()) {
            throw new \InvalidArgumentException('No members with positive balances to receive interest.');
        }

        $rows          = [];
        $totalInterest = 0.0;

        foreach ($members as $m) {
            $interest      = round((float) $m->balance * ($rate / 100), 2);
            if ($interest <= 0) continue;
            $rows[]        = ['member' => $m, 'interest' => $interest];
            $totalInterest += $interest;
        }

        if ($totalInterest <= 0) {
            throw new \InvalidArgumentException('No interest to post — check member balances and rate.');
        }

        $desc = $notes ?? "Monthly group interest — {$group->name} @ {$rate}%";

        return DB::transaction(function () use ($group, $rows, $totalInterest, $date, $desc) {
            $journal = $this->accounting->post(
                $date,
                $desc,
                [
                    ['account_id' => $this->getInterestExpenseAccountId(),      'debit' => $totalInterest, 'credit' => 0,             'description' => 'Interest on group member savings'],
                    ['account_id' => $this->getGroupLiabilityAccountId($group), 'debit' => 0,             'credit' => $totalInterest, 'description' => 'Group member interest credit'],
                ],
                'groups',
                $group->id
            );

            $txns = [];
            foreach ($rows as $row) {
                $member    = $row['member'];
                $interest  = $row['interest'];
                $balBefore = (float) $member->balance;
                $balAfter  = round($balBefore + $interest, 2);
                $member->update(['balance' => $balAfter]);

                $txns[] = GroupTransaction::create([
                    'group_id'               => $group->id,
                    'member_id'              => $member->id,
                    'type'                   => 'interest',
                    'posting_type'           => 'individual',
                    'amount'                 => $interest,
                    'balance_before'         => $balBefore,
                    'balance_after'          => $balAfter,
                    'transaction_date'       => $date,
                    'notes'                  => $desc,
                    'journal_transaction_id' => $journal->id,
                    'created_by'             => auth()->id(),
                ]);
            }
            return $txns;
        });
    }

    // ── Pooled Group ──────────────────────────────────────────────────────

    public function contributePooled(Group $group, GroupMember $member, float $amount, string $date, string $reference = '', string $notes = ''): GroupTransaction
    {
        if ($amount <= 0) throw new \InvalidArgumentException('Contribution amount must be positive.');
        if ((int) $member->group_id !== (int) $group->id) throw new \InvalidArgumentException('Member does not belong to this group.');

        return DB::transaction(function () use ($group, $member, $amount, $date, $reference, $notes) {
            $desc    = $notes ?: "Pooled contribution — {$group->name} — {$member->name}";
            $journal = $this->accounting->post(
                $date, $desc,
                [
                    ['account_id' => $this->getCashAccountId(),                 'debit' => $amount, 'credit' => 0,      'description' => $desc],
                    ['account_id' => $this->getGroupLiabilityAccountId($group), 'debit' => 0,       'credit' => $amount, 'description' => "Pool contribution — {$member->name}"],
                ],
                'groups', $group->id
            );

            $memberBalBefore = (float) $member->balance;
            $memberBalAfter  = round($memberBalBefore + $amount, 2);
            $member->update(['balance' => $memberBalAfter]);

            $poolBefore = (float) $group->pool_balance;
            $poolAfter  = round($poolBefore + $amount, 2);
            $group->update(['pool_balance' => $poolAfter]);

            return GroupTransaction::create([
                'group_id'               => $group->id,
                'member_id'              => $member->id,
                'type'                   => 'deposit',
                'posting_type'           => 'individual',
                'amount'                 => $amount,
                'balance_before'         => $poolBefore,
                'balance_after'          => $poolAfter,
                'transaction_date'       => $date,
                'reference'              => $reference ?: null,
                'notes'                  => $notes ?: $desc,
                'journal_transaction_id' => $journal->id,
                'created_by'             => auth()->id(),
            ]);
        });
    }

    public function withdrawPooled(Group $group, float $amount, string $date, string $purpose, string $reference = ''): GroupTransaction
    {
        if ($amount <= 0) throw new \InvalidArgumentException('Withdrawal amount must be positive.');
        if ((float) $group->pool_balance < $amount - 0.001) throw new \InvalidArgumentException('Insufficient group pool balance.');

        return DB::transaction(function () use ($group, $amount, $date, $purpose, $reference) {
            $journal = $this->accounting->post(
                $date, "Pool withdrawal — {$group->name} — {$purpose}",
                [
                    ['account_id' => $this->getGroupLiabilityAccountId($group), 'debit' => $amount, 'credit' => 0,      'description' => "Pool payout — {$purpose}"],
                    ['account_id' => $this->getCashAccountId(),                 'debit' => 0,       'credit' => $amount, 'description' => $purpose],
                ],
                'groups', $group->id
            );

            $poolBefore = (float) $group->pool_balance;
            $poolAfter  = round($poolBefore - $amount, 2);
            $group->update(['pool_balance' => $poolAfter]);

            return GroupTransaction::create([
                'group_id'               => $group->id,
                'member_id'              => null,
                'type'                   => 'withdrawal',
                'posting_type'           => 'individual',
                'amount'                 => $amount,
                'balance_before'         => $poolBefore,
                'balance_after'          => $poolAfter,
                'transaction_date'       => $date,
                'reference'              => $reference ?: null,
                'notes'                  => $purpose,
                'journal_transaction_id' => $journal->id,
                'created_by'             => auth()->id(),
            ]);
        });
    }

    // ── Transfer to Savings Account ───────────────────────────────────────

    public function transferToSavings(Group $group, GroupMember $member, SavingsAccount $savingsAccount, float $amount, string $date, string $notes = ''): array
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Transfer amount must be positive.');
        }
        if ((int) $member->group_id !== (int) $group->id) {
            throw new \InvalidArgumentException('Member does not belong to this group.');
        }
        if ((float) $member->balance < $amount - 0.001) {
            throw new \InvalidArgumentException('Insufficient group member balance.');
        }
        if ($savingsAccount->status !== 'active') {
            throw new \InvalidArgumentException('Target savings account is not active.');
        }
        // Note: transfer is allowed to any active savings account — not restricted to member's own client

        $description = $notes ?: "Transfer from group {$group->name} to savings {$savingsAccount->account_number}";

        return DB::transaction(function () use ($group, $member, $savingsAccount, $amount, $date, $description) {
            $savingsProduct = $savingsAccount->product;

            // Journal: DR Group Liability (reduce group balance) / CR Savings Liability (increase savings balance)
            $journal = $this->accounting->post(
                $date,
                "Group→Savings transfer — {$member->name}",
                [
                    ['account_id' => $this->getGroupLiabilityAccountId($group),         'debit' => $amount, 'credit' => 0,      'description' => "Transfer out — {$member->name}"],
                    ['account_id' => $savingsProduct->savings_liability_account_id,      'debit' => 0,       'credit' => $amount, 'description' => "Transfer in — {$savingsAccount->account_number}"],
                ],
                'groups',
                $group->id
            );

            // Reduce group member balance
            $groupBalBefore = (float) $member->balance;
            $groupBalAfter  = round($groupBalBefore - $amount, 2);
            $member->update(['balance' => $groupBalAfter]);

            // Create group transaction record (withdrawal type)
            $groupTxn = GroupTransaction::create([
                'group_id'               => $group->id,
                'member_id'              => $member->id,
                'type'                   => 'transfer_out',
                'posting_type'           => 'individual',
                'amount'                 => $amount,
                'balance_before'         => $groupBalBefore,
                'balance_after'          => $groupBalAfter,
                'transaction_date'       => $date,
                'notes'                  => $description,
                'journal_transaction_id' => $journal->id,
                'created_by'             => auth()->id(),
            ]);

            // Increase savings account balance
            $savBalBefore = (float) $savingsAccount->balance;
            $savBalAfter  = round($savBalBefore + $amount, 2);
            $savingsAccount->update(['balance' => $savBalAfter]);

            $savingsTxn = \App\Models\SavingsTransaction::create([
                'savings_account_id' => $savingsAccount->id,
                'transaction_type'   => 'deposit',
                'amount'             => $amount,
                'balance_before'     => $savBalBefore,
                'balance_after'      => $savBalAfter,
                'transaction_date'   => $date,
                'reference'          => $journal->reference,
                'description'        => "Transfer from group {$group->name} — {$member->name}",
                'transaction_id'     => $journal->id,
                'created_by'         => auth()->id(),
            ]);

            return [$groupTxn, $savingsTxn];
        });
    }

    // ── GL Helpers ────────────────────────────────────────────────────────

    protected function getCashAccountId(): int
    {
        return Account::where('account_code', '1001')->value('id')
            ?? throw new \RuntimeException('Cash account (1001) not found.');
    }

    protected function getGroupLiabilityAccountId(Group $group): int
    {
        if ($group->gl_account_id) {
            return $group->gl_account_id;
        }
        return Account::where('account_code', '2005')->value('id')
            ?? throw new \RuntimeException('Group Member Savings account (2005) not found. Run migrations.');
    }

    protected function getInterestExpenseAccountId(): int
    {
        return Account::where('account_code', '5010')->value('id')
            ?? throw new \RuntimeException('Interest Expense — Groups (5010) not found. Run migrations.');
    }
}
