<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\FixedDeposit;
use App\Models\FixedDepositProduct;
use App\Models\SavingsAccount;
use App\Services\FixedDepositService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class FixedDepositController extends Controller
{
    public function __construct(protected FixedDepositService $fdService) {}

    public function index(Request $request)
    {
        $deposits = FixedDeposit::with('client', 'product')
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->where('deposit_number', 'like', "%{$request->search}%")
                ->orWhereHas('client', fn($q2) => $q2->where('name', 'like', "%{$request->search}%")))
            ->latest()
            ->paginate(20);

        return view('fixed-deposits.index', compact('deposits'));
    }

    public function create(Request $request)
    {
        $clients        = Client::where('status', 'active')->orderBy('name')->get();
        $products       = FixedDepositProduct::where('is_active', true)->get();
        $selectedClient = $request->client_id ? Client::find($request->client_id) : null;
        $savingsAccounts = $selectedClient
            ? SavingsAccount::where('client_id', $selectedClient->id)->where('status', 'active')->with('product')->get()
            : collect();

        // Pre-build savings lookup for JS (avoids Blade @json parsing issues with parens in strings)
        $savingsByClient = SavingsAccount::where('status', 'active')
            ->with('product')
            ->get()
            ->groupBy('client_id')
            ->map(fn($accounts) => $accounts->map(fn($a) => [
                'id'      => $a->id,
                'label'   => $a->account_number . ' — ' . $a->product->name . ' (Bal: ' . number_format($a->balance, 2) . ')',
                'balance' => $a->balance,
            ]));

        $paymentSourceAccounts = \App\Models\Account::where('is_payment_source', true)->where('is_active', true)->orderBy('account_code')->get();
        return view('fixed-deposits.create', compact('clients', 'products', 'selectedClient', 'savingsAccounts', 'savingsByClient', 'paymentSourceAccounts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'client_id'                 => 'required|exists:clients,id',
            'product_id'                => 'required|exists:fixed_deposit_products,id',
            'principal'                 => 'required|numeric|min:1',
            'interest_rate'             => 'nullable|numeric|min:0',
            'term_months'               => 'nullable|integer|min:1',
            'start_date'                => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
            'savings_account_id'        => 'nullable|exists:savings_accounts,id',
            'reference'                 => 'required|string|max:100|unique:transactions,reference',
            'payment_source_account_id' => 'required_without:savings_account_id|nullable|exists:accounts,id',
        ]);

        try {
            $deposit = $this->fdService->createDeposit($request->all());
            return redirect()->route('fixed-deposits.show', $deposit)->with('success', 'Fixed deposit created successfully.');
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(FixedDeposit $fixedDeposit)
    {
        $fixedDeposit->load('client', 'product', 'createdBy', 'savingsAccount');
        return view('fixed-deposits.show', compact('fixedDeposit'));
    }

    public function matureForm(FixedDeposit $fixedDeposit)
    {
        if ($fixedDeposit->status === 'closed') {
            return back()->with('error', 'This deposit is already closed.');
        }
        $fixedDeposit->load('savingsAccount');
        return view('fixed-deposits.mature', compact('fixedDeposit'));
    }

    public function mature(Request $request, FixedDeposit $fixedDeposit)
    {
        $request->validate([
            'payout_date' => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
            'reference'   => 'required|string|max:100|unique:transactions,reference',
        ]);

        try {
            $this->fdService->matureDeposit($fixedDeposit, $request->payout_date, $request->reference);
            return redirect()->route('fixed-deposits.show', $fixedDeposit)->with('success', 'Fixed deposit matured and payout posted.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function breakForm(FixedDeposit $fixedDeposit)
    {
        if ($fixedDeposit->status !== 'active') {
            return back()->with('error', 'Only active deposits can be broken early.');
        }
        $fixedDeposit->load('client', 'product', 'savingsAccount');
        return view('fixed-deposits.break', compact('fixedDeposit'));
    }

    public function breakDeposit(Request $request, FixedDeposit $fixedDeposit)
    {
        $request->validate([
            'break_date' => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
            'reference'  => 'required|string|max:100|unique:transactions,reference',
        ]);

        try {
            $this->fdService->breakDeposit($fixedDeposit, $request->break_date, $request->reference);
            return redirect()->route('fixed-deposits.show', $fixedDeposit)
                ->with('success', 'Fixed deposit broken early. Principal returned, interest forfeited.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function accrueInterestBulk(Request $request)
    {
        $request->validate([
            'interest_date' => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
        ]);

        $deposits = FixedDeposit::with('product')->where('status', 'active')->get();

        $posted  = 0;
        $skipped = 0;
        $errors  = [];

        foreach ($deposits as $deposit) {
            try {
                $before = $deposit->accrued_interest;
                $this->fdService->accrueInterest($deposit, $request->interest_date);
                $deposit->refresh();
                ($deposit->accrued_interest > $before) ? $posted++ : $skipped++;
            } catch (\Throwable $e) {
                $errors[] = $deposit->deposit_number . ': ' . $e->getMessage();
            }
        }

        $msg = "Interest accrued for {$posted} deposit(s). Skipped: {$skipped}.";
        if ($errors) {
            $msg .= ' Errors: ' . implode('; ', $errors);
        }

        return back()->with($errors ? 'error' : 'success', $msg);
    }

    public function certificate(FixedDeposit $fixedDeposit)
    {
        $fixedDeposit->load('client', 'product');
        $pdf = Pdf::loadView('pdf.fd-certificate', compact('fixedDeposit'))
            ->setPaper('a4', 'portrait');
        return $pdf->download("fd-certificate-{$fixedDeposit->deposit_number}.pdf");
    }
}
