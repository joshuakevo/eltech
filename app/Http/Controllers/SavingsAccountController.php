<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\SavingsTransaction;
use App\Models\Transaction;
use App\Services\SavingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class SavingsAccountController extends Controller
{
    public function __construct(protected SavingsService $savingsService) {}

    public function index(Request $request)
    {
        $filtered = SavingsAccount::query()
            ->when($request->search, fn($q) => $q->where('account_number', 'like', "%{$request->search}%")
                ->orWhereHas('client', fn($q2) => $q2->where('name', 'like', "%{$request->search}%")))
            ->when($request->status, fn($q) => $q->where('status', $request->status));

        $totalBalance = (clone $filtered)->sum('balance');
        $totalSavers  = (clone $filtered)->count();

        if ($request->format === 'pdf') {
            $all = (clone $filtered)->with('client', 'product')->orderByDesc('balance')->get();
            $pdf = Pdf::loadView('pdf.savings-accounts', [
                'accounts'     => $all,
                'totalBalance' => $totalBalance,
                'totalSavers'  => $totalSavers,
            ])->setPaper('a4', 'portrait');
            return $pdf->download('savings-accounts-' . now()->format('Y-m-d') . '.pdf');
        }

        if ($request->format === 'excel') {
            $all = (clone $filtered)->with('client', 'product')->orderByDesc('balance')->get();
            $rows = [['Account #', 'Client', 'Client #', 'Product', 'Balance', 'Status']];
            foreach ($all as $acc) {
                $rows[] = [
                    $acc->account_number,
                    $acc->client->name ?? '',
                    $acc->client->client_number ?? '',
                    $acc->product->name ?? '',
                    $acc->balance,
                    ucfirst($acc->status),
                ];
            }
            $rows[] = ['', '', '', 'TOTAL', $totalBalance, $totalSavers . ' savers'];
            return $this->csvDownload($rows, 'savings-accounts-' . now()->format('Y-m-d'));
        }

        $accounts = $filtered->with('client', 'product')
            ->orderByDesc('balance')
            ->paginate(20);

        return view('savings.index', compact('accounts', 'totalBalance', 'totalSavers'));
    }

    public function create(Request $request)
    {
        $clients  = Client::where('status', 'active')->orderBy('name')->get();
        $products = SavingsProduct::where('is_active', true)->get();
        $selectedClient = $request->client_id ? Client::find($request->client_id) : null;

        return view('savings.create', compact('clients', 'products', 'selectedClient'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'client_id'   => 'required|exists:clients,id',
            'product_id'  => 'required|exists:savings_products,id',
            'opened_date' => 'nullable|date',
        ]);

        $account = $this->savingsService->openAccount($request->all());

        return redirect()->route('savings.show', $account)->with('success', 'Savings account opened.');
    }

    public function show(SavingsAccount $saving)
    {
        $saving->load('client', 'product', 'transactions.createdBy');
        $projectedInterest = $saving->product->interest_method === 'tiered'
            ? $this->savingsService->previewAccruedInterest($saving)
            : 0;
        return view('savings.show', compact('saving', 'projectedInterest'));
    }

    public function depositForm(SavingsAccount $saving)
    {
        $paymentSourceAccounts = \App\Models\Account::where('is_payment_source', true)->where('is_active', true)->orderBy('account_code')->get();
        return view('savings.deposit', compact('saving', 'paymentSourceAccounts'));
    }

    public function deposit(Request $request, SavingsAccount $saving)
    {
        $request->validate([
            'amount'                    => 'required|numeric|min:0.01',
            'date'                      => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
            'description'               => 'nullable|string|max:255',
            'reference'                 => 'required|string|max:100|unique:transactions,reference',
            'payment_source_account_id' => 'required|exists:accounts,id',
        ]);

        $this->savingsService->deposit(
            $saving,
            $request->amount,
            $request->date,
            $request->description ?? 'Deposit',
            $request->reference,
            $request->payment_source_account_id ? (int) $request->payment_source_account_id : null
        );

        return redirect()->route('savings.show', $saving)->with('success', 'Deposit posted successfully.');
    }

    public function withdrawForm(SavingsAccount $saving)
    {
        $paymentSourceAccounts = \App\Models\Account::where('is_payment_source', true)->where('is_active', true)->orderBy('account_code')->get();
        return view('savings.withdraw', compact('saving', 'paymentSourceAccounts'));
    }

    public function withdraw(Request $request, SavingsAccount $saving)
    {
        $request->validate([
            'amount'                    => 'required|numeric|min:0.01',
            'date'                      => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
            'description'               => 'nullable|string|max:255',
            'reference'                 => 'required|string|max:100|unique:transactions,reference',
            'withdrawal_fee'            => 'nullable|numeric|min:0',
            'institution_charge'        => 'nullable|numeric|min:0',
            'payment_source_account_id' => 'required|exists:accounts,id',
        ]);

        try {
            $fee               = $request->has('withdrawal_fee') ? (float) $request->withdrawal_fee : null;
            $institutionCharge = $request->has('institution_charge') ? (float) $request->institution_charge : null;
            $this->savingsService->withdraw(
                $saving,
                $request->amount,
                $request->date,
                $request->description ?? 'Withdrawal',
                $request->reference,
                $fee,
                $request->payment_source_account_id ? (int) $request->payment_source_account_id : null,
                $institutionCharge
            );
            return redirect()->route('savings.show', $saving)->with('success', 'Withdrawal processed.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function transferForm(SavingsAccount $saving)
    {
        $accounts = SavingsAccount::where('id', '!=', $saving->id)->where('status', 'active')->with('client')->get();
        return view('savings.transfer', compact('saving', 'accounts'));
    }

    public function transfer(Request $request, SavingsAccount $saving)
    {
        $request->validate([
            'to_account_id'  => 'required|exists:savings_accounts,id|different:' . $saving->id,
            'amount'         => 'required|numeric|min:0.01',
            'date'           => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
            'withdrawal_fee' => 'nullable|numeric|min:0',
        ]);

        try {
            $fee       = $request->has('withdrawal_fee') ? (float) $request->withdrawal_fee : null;
            $toAccount = SavingsAccount::findOrFail($request->to_account_id);
            $this->savingsService->transfer($saving, $toAccount, $request->amount, $request->date, null, $fee);
            return redirect()->route('savings.show', $saving)->with('success', 'Transfer completed.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function statement(Request $request, SavingsAccount $saving)
    {
        $saving->load('client', 'product');

        $query = $saving->transactions()->with('createdBy')->reorder('transaction_date', 'asc')->orderBy('id', 'asc');

        if ($request->from_date) {
            $query->where('transaction_date', '>=', $request->from_date);
        }
        if ($request->to_date) {
            $query->where('transaction_date', '<=', $request->to_date);
        }

        $transactions = $query->get();
        $account      = $saving;
        $fromDate     = $request->from_date;
        $toDate       = $request->to_date;
        $projectedInterest = $account->product->interest_method === 'tiered'
            ? $this->savingsService->previewAccruedInterest($account, $toDate)
            : 0;

        return view('savings.statement', compact('account', 'transactions', 'fromDate', 'toDate', 'projectedInterest'));
    }

    public function statementPdf(Request $request, SavingsAccount $saving)
    {
        $saving->load('client', 'product');

        $query = $saving->transactions()->with('createdBy')->reorder('transaction_date', 'asc')->orderBy('id', 'asc');

        if ($request->from_date) {
            $query->where('transaction_date', '>=', $request->from_date);
        }
        if ($request->to_date) {
            $query->where('transaction_date', '<=', $request->to_date);
        }

        $transactions = $query->get();
        $account      = $saving;
        $fromDate     = $request->from_date;
        $toDate       = $request->to_date;
        $projectedInterest = $account->product->interest_method === 'tiered'
            ? $this->savingsService->previewAccruedInterest($account, $toDate)
            : 0;

        $pdf = Pdf::loadView('pdf.savings-statement', compact('account', 'transactions', 'fromDate', 'toDate', 'projectedInterest'))
            ->setPaper('a4', 'portrait');
        return $pdf->download("savings-statement-{$saving->account_number}.pdf");
    }

    public function postInterest(Request $request, SavingsAccount $saving)
    {
        $request->validate([
            'interest_date' => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
        ]);

        try {
            $txn = $this->savingsService->postInterest($saving, $request->interest_date);
            if (!$txn) {
                return back()->with('error', 'No interest to post — check that the account has a positive balance and the product has an interest rate.');
            }
            return back()->with('success', 'Interest of ' . number_format($txn->amount, 2) . ' posted successfully.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function postInterestBulk(Request $request)
    {
        $request->validate([
            'interest_date' => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
        ]);

        // Tiered products are eligible regardless of their own (unused) interest_rate,
        // since the org-wide Savings Interest Tiers govern their rate instead.
        $accounts = SavingsAccount::with('product')
            ->where('status', 'active')
            ->whereHas('product', fn($q) => $q->where('interest_method', 'tiered')->orWhere('interest_rate', '>', 0))
            ->get();

        $posted  = 0;
        $skipped = 0;
        $errors  = [];

        foreach ($accounts as $account) {
            try {
                $txn = $this->savingsService->postInterest($account, $request->interest_date);
                $txn ? $posted++ : $skipped++;
            } catch (\InvalidArgumentException $e) {
                $skipped++;
            } catch (\Throwable $e) {
                $errors[] = $account->account_number . ': ' . $e->getMessage();
            }
        }

        $msg = "Interest posted for {$posted} account(s). Skipped: {$skipped}.";
        if ($errors) {
            $msg .= ' Errors: ' . implode('; ', $errors);
        }

        return back()->with($errors ? 'error' : 'success', $msg);
    }

    public function destroy(SavingsAccount $saving)
    {
        if (round($saving->balance, 2) != 0) {
            return back()->with('error', 'Cannot delete: account balance must be zero (current: ' . number_format($saving->balance, 2) . ').');
        }
        if ($saving->transactions()->exists()) {
            return back()->with('error', 'Cannot delete: account has transaction history. Close the account instead.');
        }
        $saving->delete();
        return redirect()->route('savings.index')->with('success', "Account {$saving->account_number} deleted.");
    }

    public function markDormant(SavingsAccount $saving)
    {
        if ($saving->status !== 'active') {
            return back()->with('error', 'Only active accounts can be flagged as dormant.');
        }
        $saving->update(['status' => 'dormant']);
        return back()->with('success', "Account {$saving->account_number} flagged as dormant.");
    }

    public function close(SavingsAccount $saving)
    {
        if ($saving->status === 'closed') {
            return back()->with('error', 'Account is already closed.');
        }
        if (round($saving->balance, 2) != 0) {
            return back()->with('error', 'Balance must be zero before closing. Current balance: ' . number_format($saving->balance, 2) . '. Please withdraw or transfer the funds first.');
        }
        $saving->update(['status' => 'closed']);
        return back()->with('success', "Account {$saving->account_number} closed.");
    }

    public function reactivate(SavingsAccount $saving)
    {
        if ($saving->status === 'active') {
            return back()->with('error', 'Account is already active.');
        }
        $saving->update(['status' => 'active']);
        return back()->with('success', "Account {$saving->account_number} reactivated.");
    }

    public function reverseInterestBulk(Request $request)
    {
        $request->validate([
            'interest_date' => ['required', 'date'],
        ]);

        $date = $request->interest_date;

        $interestRows = SavingsTransaction::with('savingsAccount')
            ->where('transaction_date', $date)
            ->where('description', 'like', 'Interest credit%')
            ->get();

        if ($interestRows->isEmpty()) {
            return back()->with('error', "No interest postings found for {$date}.");
        }

        $reversed = 0;
        $errors   = [];

        foreach ($interestRows as $st) {
            $account = $st->savingsAccount;
            if (!$account) {
                $errors[] = "Account not found for savings_transaction #{$st->id}";
                continue;
            }

            try {
                \DB::transaction(function () use ($st, $account, $date) {
                    // Roll back balance
                    $account->decrement('balance', $st->amount);

                    // Reset last_interest_date to the previous interest posting on this account
                    $prev = SavingsTransaction::where('savings_account_id', $account->id)
                        ->where('id', '<', $st->id)
                        ->where('description', 'like', 'Interest credit%')
                        ->orderByDesc('transaction_date')
                        ->orderByDesc('id')
                        ->value('transaction_date');
                    $account->update(['last_interest_date' => $prev]);

                    // Delete the linked journal entry (lines cascade via DB or we delete manually)
                    if ($st->transaction_id) {
                        $journal = Transaction::find($st->transaction_id);
                        if ($journal) {
                            $journal->lines()->delete();
                            $journal->delete();
                        }
                    }

                    $st->delete();
                });

                $reversed++;
            } catch (\Throwable $e) {
                $errors[] = ($account->account_number ?? "ID {$st->savings_account_id}") . ': ' . $e->getMessage();
            }
        }

        $msg = "Reversed interest for {$reversed} account(s) on {$date}.";
        if ($errors) {
            $msg .= ' Errors: ' . implode('; ', $errors);
        }

        return back()->with($errors ? 'error' : 'success', $msg);
    }
}
