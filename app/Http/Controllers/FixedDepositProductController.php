<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\FixedDepositProduct;
use Illuminate\Http\Request;

class FixedDepositProductController extends Controller
{
    public function index()
    {
        $products = FixedDepositProduct::paginate(20);
        return view('fd-products.index', compact('products'));
    }

    public function create()
    {
        $accounts = Account::where('is_active', true)->orderBy('account_code')->get();
        return view('fd-products.create', compact('accounts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                         => 'required|string|max:255',
            'interest_rate'                => 'required|numeric|min:0',
            'term_months'                  => 'required|integer|min:1',
            'min_amount'                   => 'nullable|numeric|min:0',
            'max_amount'                   => 'nullable|numeric|min:0',
            'deposit_liability_account_id' => 'nullable|exists:accounts,id',
            'interest_expense_account_id'  => 'nullable|exists:accounts,id',
            'is_active'                    => 'boolean',
        ]);

        FixedDepositProduct::create($data);

        return redirect()->route('fd-products.index')->with('success', 'Fixed deposit product created.');
    }

    public function show(FixedDepositProduct $fdProduct)
    {
        $stats = [
            'total'       => $fdProduct->fixedDeposits()->count(),
            'active'      => $fdProduct->fixedDeposits()->where('status', 'active')->count(),
            'total_principal' => $fdProduct->fixedDeposits()->where('status', 'active')->sum('principal'),
        ];
        return view('fd-products.show', compact('fdProduct', 'stats'));
    }

    public function edit(FixedDepositProduct $fdProduct)
    {
        $accounts = Account::where('is_active', true)->orderBy('account_code')->get();
        return view('fd-products.edit', compact('fdProduct', 'accounts'));
    }

    public function update(Request $request, FixedDepositProduct $fdProduct)
    {
        $data = $request->validate([
            'name'                         => 'required|string|max:255',
            'interest_rate'                => 'required|numeric|min:0',
            'term_months'                  => 'required|integer|min:1',
            'min_amount'                   => 'nullable|numeric|min:0',
            'max_amount'                   => 'nullable|numeric|min:0',
            'deposit_liability_account_id' => 'nullable|exists:accounts,id',
            'interest_expense_account_id'  => 'nullable|exists:accounts,id',
            'is_active'                    => 'boolean',
        ]);

        $fdProduct->update($data);

        return redirect()->route('fd-products.index')->with('success', 'Product updated.');
    }
}
