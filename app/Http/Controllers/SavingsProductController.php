<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\SavingsProduct;
use Illuminate\Http\Request;

class SavingsProductController extends Controller
{
    public function index()
    {
        $products = SavingsProduct::paginate(20);
        return view('savings-products.index', compact('products'));
    }

    public function create()
    {
        $accounts = Account::where('is_active', true)->orderBy('account_code')->get();
        return view('savings-products.create', compact('accounts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                         => 'required|string|max:255',
            'minimum_balance'              => 'required|numeric|min:0',
            'withdrawal_fee'               => 'nullable|numeric|min:0',
            'interest_rate'                => 'nullable|numeric|min:0',
            'interest_method'              => 'required|in:flat,tiered',
            'interest_frequency'           => 'required|in:daily,monthly,quarterly,annually',
            'savings_liability_account_id' => 'nullable|exists:accounts,id',
            'interest_expense_account_id'  => 'nullable|exists:accounts,id',
            'is_active'                    => 'boolean',
        ]);

        SavingsProduct::create($data);

        return redirect()->route('savings-products.index')->with('success', 'Savings product created.');
    }

    public function show(SavingsProduct $savingsProduct)
    {
        $stats = [
            'total_accounts'  => $savingsProduct->savingsAccounts()->count(),
            'active_accounts' => $savingsProduct->savingsAccounts()->where('status', 'active')->count(),
            'total_balance'   => $savingsProduct->savingsAccounts()->sum('balance'),
        ];
        return view('savings-products.show', compact('savingsProduct', 'stats'));
    }

    public function edit(SavingsProduct $savingsProduct)
    {
        $accounts = Account::where('is_active', true)->orderBy('account_code')->get();
        return view('savings-products.edit', compact('savingsProduct', 'accounts'));
    }

    public function update(Request $request, SavingsProduct $savingsProduct)
    {
        $data = $request->validate([
            'name'                         => 'required|string|max:255',
            'minimum_balance'              => 'required|numeric|min:0',
            'withdrawal_fee'               => 'nullable|numeric|min:0',
            'interest_rate'                => 'nullable|numeric|min:0',
            'interest_method'              => 'required|in:flat,tiered',
            'interest_frequency'           => 'required|in:daily,monthly,quarterly,annually',
            'savings_liability_account_id' => 'nullable|exists:accounts,id',
            'interest_expense_account_id'  => 'nullable|exists:accounts,id',
            'is_active'                    => 'boolean',
        ]);

        $savingsProduct->update($data);

        return redirect()->route('savings-products.index')->with('success', 'Product updated.');
    }
}
