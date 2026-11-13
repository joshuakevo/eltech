<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\FinancialPeriod;
use App\Models\FixedDeposit;
use App\Models\Loan;
use App\Models\MemberClosure;
use App\Models\OverdraftCharge;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Models\SystemSetting;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Closing a member's account ("Close Account" on the client page). One journal (module member_closure):
 *
 *  Accounts in credit — their balances pay the account closure fee (setting account_closure_fee, default
 *  30,000, includes the withdrawal fee → account_closure_fee_account, 4104) and the rest is paid out:
 *      DR savings liability / CR closure fee income, CR cash / bank / mobile money
 *  If they are less than the fee, everything goes to the closure fee (nothing paid out). Fee from the largest
 *  balance first.
 *
 *  Overdrawn accounts (e.g. −800) — written off: DR Overdrawn Savings Write-Off (setting overdrawn_writeoff_account,
 *  5122, expense) / CR savings liability. Any unpaid overdraft charge in that balance was never earned, so it is
 *  cleared against the write-off: DR Accrued Overdraft Fees / CR write-off (the charges are marked written off).
 *
 *  Every account ends at 0 and is closed; the member becomes inactive with closed_at. Blocked while a loan or
 *  fixed deposit is running. Reopen (or reversing the journal) puts everything back.
 */
class MemberClosureService
{
    public const WRITE_OFF_TEXT = 'Overdrawn balance written off';

    public function __construct(protected AccountingService $accounting, protected SavingsService $savings) {}

    public function defaultClosureFee(): float
    {
        return (float) SystemSetting::get('account_closure_fee', 30000);
    }

    public function closureFeeAccountId(): ?int
    {
        return Account::where('account_code', SystemSetting::get('account_closure_fee_account', '4104'))->value('id');
    }

    public function writeOffAccountId(): ?int
    {
        $code = SystemSetting::get('overdrawn_writeoff_account');

        return $code ? Account::where('account_code', $code)->value('id') : Account::where('account_name', 'like', 'Overdrawn Savings Write%')->value('id');
    }

    /** Things that stop the closure (running loans / deposits, later transactions). */
    public function blockers(Client $client, ?string $date = null): array
    {
        $date ??= today()->toDateString();
        $out = [];
        foreach (Loan::where('client_id', $client->id)->whereIn('status', ['pending', 'approved', 'active', 'disbursed', 'overdue', 'defaulted'])->get() as $loan) {
            $out[] = "Loan {$loan->loan_number}" . ($loan->status === 'pending' ? ' is a pending application — cancel it first.' : ' is still running (owes ' . number_format((float) $loan->outstanding_principal + (float) $loan->outstanding_interest) . ') — clear it first.');
        }
        foreach (FixedDeposit::where('client_id', $client->id)->whereIn('status', ['active', 'matured'])->get() as $fd) {
            $out[] = "Fixed deposit {$fd->deposit_number} is still open — pay it out first.";
        }
        foreach ($this->accounts($client) as $a) {
            if (SavingsTransaction::where('savings_account_id', $a->id)->where('transaction_date', '>', $date)->exists()) {
                $out[] = "Savings {$a->account_number} has transactions after " . Carbon::parse($date)->format('d M Y') . ' — choose a later closure date.';
            }
        }

        return $out;
    }

    /** The member's savings accounts that are not closed yet. */
    public function accounts(Client $client)
    {
        return SavingsAccount::with('product')->where('client_id', $client->id)->where('status', '!=', 'closed')->get();
    }

    /**
     * The split per account: closure fee and payout from accounts in credit (largest first), write-off for
     * overdrawn ones (with the unpaid overdraft charges inside it).
     */
    public function plan(Client $client, string $date, float $closureFee): array
    {
        $all = $this->accounts($client)->map(fn ($a) => ['account' => $a, 'balance' => round($this->savings->balanceAsOf($a, $date), 2)])
            ->sortByDesc('balance')->values();
        $total = round($all->where('balance', '>', 0)->sum('balance'), 2);
        $fee = round(min($closureFee, $total), 2);   // not enough: everything to the closure fee
        $left = $fee;
        $lines = $all->map(function ($r) use (&$left) {
            $b = $r['balance'];
            if ($b < 0) {
                $unpaidCharges = round(min(abs($b), (float) OverdraftCharge::where('savings_account_id', $r['account']->id)
                    ->sum(DB::raw('amount - recognised_amount'))), 2);

                return $r + ['closure_fee' => 0.0, 'payout' => 0.0, 'write_off' => round(abs($b), 2), 'unpaid_charges' => max(0, $unpaidCharges)];
            }
            $c = round(min($b, $left), 2);
            $left = round($left - $c, 2);

            return $r + ['closure_fee' => $c, 'payout' => round($b - $c, 2), 'write_off' => 0.0, 'unpaid_charges' => 0.0];
        })->all();
        $writeOff = round(collect($lines)->sum('write_off'), 2);

        return ['accounts' => $lines, 'total' => $total, 'closure_fee' => $fee, 'payout' => round($total - $fee, 2),
            'write_off' => $writeOff, 'unpaid_charges' => round(collect($lines)->sum('unpaid_charges'), 2), 'short' => $total + 0.005 < $closureFee];
    }

    public function close(Client $client, array $in): MemberClosure
    {
        $date = Carbon::parse($in['closure_date'] ?? today())->startOfDay();
        $closureFee = round(max(0, (float) ($in['closure_fee'] ?? $this->defaultClosureFee())));
        $fail = fn ($m) => throw ValidationException::withMessages(['closure' => $m]);

        return DB::transaction(function () use ($client, $in, $date, $closureFee, $fail) {
            $client = Client::whereKey($client->id)->lockForUpdate()->firstOrFail();
            if ($client->closed_at) {
                $fail('This member\'s account is already closed.');
            }
            if ($date->gt(today())) {
                $fail('The closure date cannot be in the future.');
            }
            if (!FinancialPeriod::isOpen($date->toDateString())) {
                $fail('The financial period for ' . $date->format('M Y') . ' is closed.');
            }
            if ($blockers = $this->blockers($client, $date->toDateString())) {
                $fail(implode(' ', $blockers));
            }
            $plan = $this->plan($client, $date->toDateString(), $closureFee);
            $feeAccount = $this->closureFeeAccountId();
            $writeOffAccount = $this->writeOffAccountId();
            $accruedAccount = app(OverdraftService::class)->accruedAccountId();
            if ($plan['closure_fee'] > 0 && !$feeAccount) {
                $fail('The account closure fee account was not found. Check Settings → Account Closure.');
            }
            if ($plan['write_off'] > 0 && !$writeOffAccount) {
                $fail('The Overdrawn Savings Write-Off account was not found. Check Settings → Account Closure.');
            }
            $payoutAccount = null;
            if ($plan['payout'] > 0) {
                $payoutAccount = Account::where('is_payment_source', true)->where('is_active', true)->find($in['payout_account_id'] ?? null);
                if (!$payoutAccount) {
                    $fail('Choose the cash, bank or mobile-money account the balance is paid out from.');
                }
            }

            $closure = MemberClosure::create([
                'client_id' => $client->id, 'closure_date' => $date->toDateString(), 'total_balance' => $plan['total'],
                'closure_fee' => $plan['closure_fee'], 'payout' => $plan['payout'], 'written_off' => $plan['write_off'],
                'payout_account_id' => $payoutAccount?->id, 'reason' => $in['reason'] ?? null, 'created_by' => auth()->id(),
                'snapshot' => [
                    'client' => ['status' => $client->getRawOriginal('status')],
                    'accounts' => collect($plan['accounts'])->mapWithKeys(fn ($r) => [$r['account']->id => $r['account']->getRawOriginal('status')])->all(),
                ],
            ]);

            $rowIds = [];
            $charges = [];   // overdraft charges written off: [id => recognised_amount before]
            $who = "{$client->name} ({$client->client_number})";
            $lines = [];
            foreach ($plan['accounts'] as $r) {
                $liability = $r['account']->product->savings_liability_account_id;
                if ($r['balance'] > 0) {
                    $lines[] = ['account_id' => $liability, 'debit' => $r['balance'], 'credit' => 0, 'client_id' => $client->id, 'description' => "Account closure - {$r['account']->account_number}"];
                } elseif ($r['write_off'] > 0) {
                    $lines[] = ['account_id' => $writeOffAccount, 'debit' => $r['write_off'], 'credit' => 0, 'client_id' => $client->id, 'description' => self::WRITE_OFF_TEXT . " - {$r['account']->account_number}"];
                    $lines[] = ['account_id' => $liability, 'debit' => 0, 'credit' => $r['write_off'], 'client_id' => $client->id, 'description' => self::WRITE_OFF_TEXT . " - {$r['account']->account_number}"];
                    if ($r['unpaid_charges'] > 0 && $accruedAccount) {
                        // Unpaid overdraft charges inside the written-off balance were never earned: clear them against the write-off
                        $lines[] = ['account_id' => $accruedAccount, 'debit' => $r['unpaid_charges'], 'credit' => 0, 'client_id' => $client->id, 'description' => "Unpaid overdraft charges written off - {$r['account']->account_number}"];
                        $lines[] = ['account_id' => $writeOffAccount, 'debit' => 0, 'credit' => $r['unpaid_charges'], 'client_id' => $client->id, 'description' => "Unpaid overdraft charges written off - {$r['account']->account_number}"];
                        foreach (OverdraftCharge::where('savings_account_id', $r['account']->id)->whereColumn('recognised_amount', '<', 'amount')->get() as $ch) {
                            $charges[$ch->id] = $ch->recognised_amount;
                            $ch->update(['recognised_amount' => $ch->amount]);
                        }
                    }
                }
            }
            if ($plan['closure_fee'] > 0) {
                $lines[] = ['account_id' => $feeAccount, 'debit' => 0, 'credit' => $plan['closure_fee'], 'client_id' => $client->id, 'description' => "Account closure fee - {$who}"];
            }
            if ($plan['payout'] > 0) {
                $lines[] = ['account_id' => $payoutAccount->id, 'debit' => 0, 'credit' => $plan['payout'], 'client_id' => $client->id, 'description' => "Account closure - balance paid out to {$who}"];
            }

            if ($lines) {
                $tx = $this->accounting->post($date->toDateString(), "Account closure: {$who}", $lines, 'member_closure', $closure->id);
                $closure->transaction_id = $tx->id;
                foreach ($plan['accounts'] as $r) {
                    foreach ([['closure_fee', 'withdrawal', 'Account closure fee'], ['payout', 'withdrawal', 'Account closure - balance paid out'], ['write_off', 'deposit', self::WRITE_OFF_TEXT]] as [$k, $type, $desc]) {
                        if ($r[$k] > 0) {
                            $rowIds[] = SavingsTransaction::create([
                                'savings_account_id' => $r['account']->id, 'transaction_type' => $type, 'amount' => $r[$k],
                                'balance_before' => 0, 'balance_after' => 0, 'transaction_date' => $date->toDateString(),
                                'reference' => $tx->reference, 'description' => $desc, 'transaction_id' => $tx->id, 'created_by' => auth()->id(),
                            ])->id;
                        }
                    }
                }
            }
            foreach ($plan['accounts'] as $r) {
                $this->savings->recalculateLedger($r['account']->fresh());
                $r['account']->forceFill(['status' => 'closed'])->save();
            }
            $client->forceFill(['status' => 'inactive', 'closed_at' => $date->toDateString()])->save();
            $closure->snapshot = $closure->snapshot + ['row_ids' => $rowIds, 'overdraft_charges' => $charges];
            $closure->save();

            AuditLog::record('member_closed', "Closed {$client->name} ({$client->client_number}) on {$date->toDateString()}: balances " . number_format($plan['total'])
                . ', closure fee ' . number_format($plan['closure_fee']) . ', paid out ' . number_format($plan['payout']) . ($payoutAccount ? " from {$payoutAccount->account_code}" : '')
                . ($plan['write_off'] > 0 ? ', overdrawn ' . number_format($plan['write_off']) . ' written off' : '') . ', ' . count($plan['accounts']) . ' savings account(s) closed'
                . (!empty($in['reason']) ? ". Reason: {$in['reason']}" : ''), 'clients');

            return $closure;
        });
    }

    /** Reopen: remove the closure lines, reopen the accounts and the member; reverse the journal unless the caller is. */
    public function reopen(MemberClosure $closure, bool $reverseJournal = true): void
    {
        DB::transaction(function () use ($closure, $reverseJournal) {
            $closure = MemberClosure::whereKey($closure->id)->lockForUpdate()->firstOrFail();
            if ($closure->status !== 'applied') {
                throw ValidationException::withMessages(['closure' => 'This closure has already been undone.']);
            }
            $ids = array_map('intval', array_keys($closure->snapshot['accounts'] ?? []));
            $ours = array_map('intval', $closure->snapshot['row_ids'] ?? []);
            if (SavingsTransaction::whereIn('savings_account_id', $ids)->whereNotIn('id', $ours)->where('created_at', '>', $closure->created_at)->exists()) {
                throw ValidationException::withMessages(['closure' => 'There are newer transactions on the closed accounts. Reverse those first.']);
            }

            SavingsTransaction::whereIn('id', $ours)->get()->each->delete();
            foreach ($closure->snapshot['overdraft_charges'] ?? [] as $id => $recognised) {
                OverdraftCharge::whereKey($id)->update(['recognised_amount' => $recognised]);
            }
            foreach ($closure->snapshot['accounts'] ?? [] as $id => $status) {
                if ($account = SavingsAccount::find($id)) {
                    $account->forceFill(['status' => $status ?: 'active'])->save();
                    $this->savings->recalculateLedger($account->fresh());
                }
            }
            if ($client = Client::find($closure->client_id)) {
                $client->forceFill(['status' => $closure->snapshot['client']['status'] ?? 'active', 'closed_at' => null])->save();
            }

            if ($reverseJournal && $closure->transaction_id && ($tx = Transaction::with('lines')->find($closure->transaction_id)) && !$tx->reversed_by) {
                $reversal = $this->accounting->post(now()->toDateString(), "REVERSAL of {$tx->reference}: Reopen member account",
                    $tx->lines->map(fn ($l) => ['account_id' => $l->account_id, 'debit' => $l->credit, 'credit' => $l->debit, 'client_id' => $l->client_id, 'description' => 'Reversal: ' . $l->description])->all(),
                    'reversal', $tx->id);
                $reversal->update(['reversal_of' => $tx->id, 'reversal_reason' => 'Reopen member account']);
                $tx->update(['reversed_by' => $reversal->id]);
            }
            $closure->update(['status' => 'reversed', 'reversed_at' => now(), 'reversed_by' => auth()->id()]);
            AuditLog::record('member_reopened', "Reopened {$client?->name} ({$client?->client_number}) — closure of " . $closure->closure_date->toDateString() . ' undone', 'clients');
        });
    }
}
