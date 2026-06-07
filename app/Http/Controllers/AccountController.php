<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\TransactionLine;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $accounts = Account::with('parent')
            ->when($request->type, fn($q) => $q->where('account_type', $request->type))
            ->when($request->search, fn($q) => $q->where('account_name', 'like', "%{$request->search}%")
                ->orWhere('account_code', 'like', "%{$request->search}%"))
            ->orderBy('account_code')
            ->paginate(30);

        return view('accounts.index', compact('accounts'));
    }

    public function create()
    {
        $parents = Account::where('is_active', true)->orderBy('account_code')->get();
        return view('accounts.create', compact('parents'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'account_code'      => 'required|string|max:20|unique:accounts',
            'account_name'      => 'required|string|max:255',
            'account_type'      => 'required|in:asset,liability,equity,revenue,expense',
            'parent_id'         => 'nullable|exists:accounts,id',
            'description'       => 'nullable|string',
            'is_active'         => 'boolean',
            'is_payment_source' => 'boolean',
        ]);
        $data['is_active']         = $request->boolean('is_active');
        $data['is_payment_source'] = $request->boolean('is_payment_source');

        Account::create($data);

        return redirect()->route('accounts.index')->with('success', 'Account created successfully.');
    }

    public function show(Account $account, Request $request)
    {
        $lines = TransactionLine::with('transaction')
            ->where('account_id', $account->id)
            ->join('transactions', 'transaction_lines.transaction_id', '=', 'transactions.id')
            ->when($request->from_date, fn($q) => $q->where('transactions.date', '>=', $request->from_date))
            ->when($request->to_date, fn($q) => $q->where('transactions.date', '<=', $request->to_date))
            ->orderBy('transactions.date')
            ->orderBy('transactions.id')
            ->select('transaction_lines.*')
            ->paginate(30);

        $totalDebit  = $lines->sum('debit');
        $totalCredit = $lines->sum('credit');

        return view('accounts.show', compact('account', 'lines', 'totalDebit', 'totalCredit'));
    }

    public function edit(Account $account)
    {
        $parents = Account::where('is_active', true)->where('id', '!=', $account->id)->orderBy('account_code')->get();
        return view('accounts.edit', compact('account', 'parents'));
    }

    public function update(Request $request, Account $account)
    {
        $data = $request->validate([
            'account_code'      => 'required|string|max:20|unique:accounts,account_code,' . $account->id,
            'account_name'      => 'required|string|max:255',
            'account_type'      => 'required|in:asset,liability,equity,revenue,expense',
            'parent_id'         => 'nullable|exists:accounts,id',
            'description'       => 'nullable|string',
            'is_active'         => 'boolean',
            'is_payment_source' => 'boolean',
        ]);
        $data['is_active']         = $request->boolean('is_active');
        $data['is_payment_source'] = $request->boolean('is_payment_source');

        $account->update($data);

        return redirect()->route('accounts.index')->with('success', 'Account updated.');
    }
}
