<?php

namespace App\Http\Controllers;

use App\Models\SavingsAccount;
use Illuminate\Http\Request;

class CloseAccountsController extends Controller
{
    public function index(Request $request)
    {
        $accounts = SavingsAccount::query()
            ->whereIn('status', ['active', 'dormant'])
            ->when($request->search, fn($q) => $q->where('account_number', 'like', "%{$request->search}%")
                ->orWhereHas('client', fn($q2) => $q2->where('name', 'like', "%{$request->search}%")
                    ->orWhere('client_number', 'like', "%{$request->search}%")))
            ->when($request->zero_balance_only, fn($q) => $q->where('balance', 0))
            ->with('client', 'product')
            ->orderBy('balance')
            ->paginate(30)
            ->withQueryString();

        return view('close-accounts.index', compact('accounts'));
    }

    public function close(Request $request)
    {
        $data = $request->validate([
            'account_ids'   => 'required|array|min:1',
            'account_ids.*' => 'exists:savings_accounts,id',
        ]);

        $closed  = 0;
        $skipped = [];

        foreach (SavingsAccount::whereIn('id', $data['account_ids'])->get() as $account) {
            if ($account->status === 'closed') {
                continue;
            }
            if (round($account->balance, 2) != 0) {
                $skipped[] = $account->account_number;
                continue;
            }
            $account->update(['status' => 'closed']);
            $closed++;
        }

        $message = "{$closed} account(s) closed.";
        if ($skipped) {
            $message .= ' Skipped (non-zero balance): ' . implode(', ', $skipped) . '.';
        }

        return back()->with($skipped && !$closed ? 'error' : 'success', $message);
    }
}
