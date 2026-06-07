<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\SavingsAccount;
use App\Services\SavingsService;
use Illuminate\Http\Request;

class TellerController extends Controller
{
    public function __construct(private SavingsService $savings) {}

    public function index()
    {
        $paymentSourceAccounts = \App\Models\Account::where('is_payment_source', true)->where('is_active', true)->orderBy('account_code')->get();
        return view('teller.index', compact('paymentSourceAccounts'));
    }

    /**
     * AJAX: search clients by name/phone/account number
     */
    public function searchClient(Request $request)
    {
        $q = $request->get('q', '');

        $accounts = SavingsAccount::with('client', 'product')
            ->whereHas('client', function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('phone', 'like', "%{$q}%")
                      ->orWhere('client_number', 'like', "%{$q}%");
            })
            ->orWhere('account_number', 'like', "%{$q}%")
            ->where('status', 'active')
            ->limit(10)
            ->get()
            ->map(fn($a) => [
                'id'             => $a->id,
                'account_number' => $a->account_number,
                'client_name'    => $a->client->name,
                'product_name'   => $a->product->name,
                'balance'        => $a->balance,
                'balance_fmt'    => number_format($a->balance, 2),
                'withdrawal_fee' => $a->product->withdrawal_fee ?? 0,
            ]);

        return response()->json($accounts);
    }

    public function deposit(Request $request)
    {
        $data = $request->validate([
            'savings_account_id'        => 'required|exists:savings_accounts,id',
            'amount'                    => 'required|numeric|min:0.01',
            'date'                      => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
            'reference'                 => 'required|string|max:100|unique:transactions,reference',
            'narration'                 => 'nullable|string|max:200',
            'payment_source_account_id' => 'required|exists:accounts,id',
        ]);

        $account = SavingsAccount::findOrFail($data['savings_account_id']);

        try {
            $this->savings->deposit(
                $account,
                (float) $data['amount'],
                $data['date'],
                $data['narration'] ?? 'Teller deposit',
                $data['reference'] ?? null,
                isset($data['payment_source_account_id']) ? (int) $data['payment_source_account_id'] : null
            );
            return back()->with('success', "Deposit of " . number_format($data['amount'], 2) . " posted successfully.");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function withdraw(Request $request)
    {
        $data = $request->validate([
            'savings_account_id'        => 'required|exists:savings_accounts,id',
            'amount'                    => 'required|numeric|min:0.01',
            'date'                      => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
            'reference'                 => 'required|string|max:100|unique:transactions,reference',
            'narration'                 => 'nullable|string|max:200',
            'withdrawal_fee'            => 'nullable|numeric|min:0',
            'payment_source_account_id' => 'required|exists:accounts,id',
        ]);

        $account = SavingsAccount::findOrFail($data['savings_account_id']);
        $fee     = isset($data['withdrawal_fee']) ? (float) $data['withdrawal_fee'] : null;

        try {
            $this->savings->withdraw(
                $account,
                (float) $data['amount'],
                $data['date'],
                $data['narration'] ?? 'Teller withdrawal',
                $data['reference'] ?? null,
                $fee,
                isset($data['payment_source_account_id']) ? (int) $data['payment_source_account_id'] : null
            );
            return back()->with('success', "Withdrawal of " . number_format($data['amount'], 2) . " posted successfully.");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }
}
