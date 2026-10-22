<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Models\Transaction;
use App\Services\SavingsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Savings opening balances brought over at the transfer were imported as a statement row ("Opening balance…") and a
 * separate journal ("Opening balance (31/07/2026 statement) - savings SA-…") that were never linked — so deleting the
 * journal left the row on the statement (statement and books then disagree).
 * This page (super admin): links each unlinked opening row to its journal (same account number + amount, journal not
 * linked to another row), so a later delete/reversal removes the row too; and lists rows whose journal no longer
 * exists, with Remove (deletes the row and rebuilds the running balance).
 */
class OpeningBalanceCheckController extends Controller
{
    public function __construct(protected SavingsService $savings) {}

    /** ['linkable' => [row, journal], 'orphans' => rows] */
    private function analyse(): array
    {
        $rows = SavingsTransaction::with('savingsAccount.client', 'savingsAccount.product')
            ->whereNull('transaction_id')->where('description', 'like', 'Opening balance%')
            ->orderBy('savings_account_id')->get();
        if ($rows->isEmpty()) {
            return ['linkable' => collect(), 'orphans' => collect()];
        }
        $taken = SavingsTransaction::whereNotNull('transaction_id')->pluck('transaction_id')->map(fn ($id) => (int) $id)->flip();
        $journals = Transaction::with('lines')->where('description', 'like', 'Opening balance%savings %')
            ->whereNull('reversed_by')->whereNull('reversal_of')->get()
            ->groupBy(fn ($t) => strtoupper(trim(substr($t->description, strrpos($t->description, 'savings ') + 8))));

        $linkable = collect();
        $orphans = collect();
        $used = [];
        foreach ($rows as $row) {
            $acc = $row->savingsAccount;
            if (!$acc) {
                continue;
            }
            $candidates = ($journals->get(strtoupper($acc->account_number)) ?? collect())
                ->filter(fn ($t) => !isset($taken[(int) $t->id]) && !isset($used[$t->id]));
            $amount = abs((float) $row->amount);
            $match = $candidates->first(function ($t) use ($acc, $amount) {
                $line = $t->lines->firstWhere('account_id', $acc->product->savings_liability_account_id ?? 0);
                return $line && abs(abs((float) $line->debit - (float) $line->credit) - $amount) < 0.01;
            });
            if ($match) {
                $used[$match->id] = true;
                $linkable->push([$row, $match]);
            } elseif ($candidates->isEmpty()) {
                $orphans->push($row);   // no opening journal left for this account — the statement row has nothing behind it
            }
        }
        return compact('linkable', 'orphans');
    }

    public function index()
    {
        ['linkable' => $linkable, 'orphans' => $orphans] = $this->analyse();
        return view('savings.opening-check', compact('linkable', 'orphans'));
    }

    public function link()
    {
        $linkable = $this->analyse()['linkable'];
        DB::transaction(function () use ($linkable) {
            foreach ($linkable as [$row, $journal]) {
                $row->update(['transaction_id' => $journal->id, 'reference' => $row->reference ?: $journal->reference]);
            }
        });
        AuditLog::record('savings_opening_linked', "Linked {$linkable->count()} savings opening balance row(s) to their journals", 'savings');
        return back()->with('success', "Linked {$linkable->count()} opening balance(s) to their journals — deleting or reversing those journals now removes the statement row too.");
    }

    public function remove(SavingsTransaction $row)
    {
        $orphanIds = $this->analyse()['orphans']->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (!in_array((int) $row->id, $orphanIds, true)) {
            return back()->with('error', 'That row still has an opening journal behind it — it is not a leftover.');
        }
        $account = $row->savingsAccount;
        DB::transaction(function () use ($row, $account) {
            $row->delete();
            $this->savings->recalculateLedger($account);
        });
        AuditLog::record('savings_opening_removed', "Removed leftover opening balance row " . number_format((float) $row->amount, 2) . " from {$account->account_number} (its journal had been deleted)", 'savings');
        return back()->with('success', "Removed the leftover opening balance from {$account->account_number}. Balance now " . number_format((float) $account->fresh()->balance, 2) . '.');
    }
}
