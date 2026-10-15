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
        $filtered = FixedDeposit::query()
            // "due" = still active but past its maturity date (awaiting maturity / payout)
            ->when($request->status === 'due', fn($q) => $q->where('status', 'active')->whereDate('maturity_date', '<=', today()))
            // "soon" = maturing within the alert window; "alert" = matured or maturing soon (bell)
            ->when($request->status === 'soon', fn($q) => $q->where('status', 'active')->whereDate('maturity_date', '>', today())
                ->whereDate('maturity_date', '<=', today()->addDays((int) \App\Models\SystemSetting::get('fd_alert_days', 7))))
            ->when($request->status === 'alert', fn($q) => $q->where('status', 'active')
                ->whereDate('maturity_date', '<=', today()->addDays((int) \App\Models\SystemSetting::get('fd_alert_days', 7))))
            ->when($request->status && !in_array($request->status, ['due', 'soon', 'alert'], true), fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->where(fn($q1) => $q1->where('deposit_number', 'like', "%{$request->search}%")
                ->orWhereHas('client', fn($q2) => $q2->where('name', 'like', "%{$request->search}%"))));

        $totalPrincipal = (clone $filtered)->sum('principal');
        $totalCount     = (clone $filtered)->count();

        if ($request->format === 'pdf') {
            $all = (clone $filtered)->with('client', 'product')->orderBy('maturity_date')->get();
            $pdf = Pdf::loadView('pdf.fixed-deposits', [
                'deposits'       => $all,
                'totalPrincipal' => $totalPrincipal,
                'totalCount'     => $totalCount,
            ])->setPaper('a4', 'portrait');
            return $pdf->download('fixed-deposits-' . now()->format('Y-m-d') . '.pdf');
        }

        if ($request->format === 'excel') {
            $all = (clone $filtered)->with('client', 'product')->orderBy('maturity_date')->get();
            $rows = [['Deposit #', 'Client', 'Client #', 'Product', 'Principal', 'Maturity Date', 'Status']];
            foreach ($all as $fd) {
                $rows[] = [
                    $fd->deposit_number,
                    $fd->client->name ?? '',
                    $fd->client->client_number ?? '',
                    $fd->product->name ?? '',
                    $fd->principal,
                    optional($fd->maturity_date)->format('Y-m-d'),
                    ucfirst($fd->status),
                ];
            }
            $rows[] = ['', '', '', 'TOTAL', $totalPrincipal, '', $totalCount . ' deposits'];
            return $this->csvDownload($rows, 'fixed-deposits-' . now()->format('Y-m-d'));
        }

        $deposits = $filtered->with('client', 'product')
            ->orderBy('maturity_date')
            ->paginate(20);

        return view('fixed-deposits.index', compact('deposits', 'totalPrincipal', 'totalCount'));
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
        $allSavingsAccounts = SavingsAccount::with('client:id,name', 'product:id,name')->where('status', 'active')->orderBy('account_number')->get();
        return view('fixed-deposits.create', compact('clients', 'products', 'selectedClient', 'savingsAccounts', 'savingsByClient', 'paymentSourceAccounts', 'allSavingsAccounts'));
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
            'payment_source_account_id' => 'required_without_all:savings_account_id,funding_account_id|nullable|exists:accounts,id',
            'compounding'               => 'nullable|in:simple,monthly',
            'funding_account_id'        => 'nullable|exists:savings_accounts,id',
            'funding_date'              => ['nullable', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
            'allow_overdraft'           => 'nullable|boolean',
            'asset_class'               => 'nullable|string|max:255',
            'certificate_reference'     => 'nullable|string|max:100',
        ]);

        try {
            $data = $request->all();
            // "From another member's account" (e.g. a pool) funds through the savings path
            if (!empty($data['funding_account_id'])) {
                $data['savings_account_id'] = $data['funding_account_id'];
            }
            $data['allow_overdraft'] = $request->boolean('allow_overdraft') && $request->user()->can('overdraw savings');
            // A monthly-rate product's rate is typed per month; deposits store % p.a.
            if (($data['interest_rate'] ?? '') !== '' && $data['interest_rate'] !== null) {
                $data['interest_rate'] = \App\Models\FixedDepositProduct::findOrFail($data['product_id'])->toAnnualRate((float) $data['interest_rate']);
            }
            $deposit = $this->fdService->createDeposit($data);
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
        $payoutAccount = $this->fdService->payoutSavingsAccount($fixedDeposit);
        return view('fixed-deposits.mature', compact('fixedDeposit', 'payoutAccount'));
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
        $payoutAccount = $this->fdService->payoutSavingsAccount($fixedDeposit);
        return view('fixed-deposits.break', compact('fixedDeposit', 'payoutAccount'));
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
        // Monthly-rate / anniversary products (e.g. Venture Capital Studio) use the investment placement certificate
        $view = $fixedDeposit->product && ($fixedDeposit->product->isMonthlyRate() || $fixedDeposit->product->isAnniversary())
            ? 'pdf.investment-certificate' : 'pdf.fd-certificate';
        $pdf = Pdf::loadView($view, compact('fixedDeposit'))->setPaper('a4', 'portrait');
        return $pdf->download("certificate-{$fixedDeposit->deposit_number}.pdf");
    }

    /** Book this placement's interest due up to today (anniversary products: each anniversary reached). */
    public function accrueOne(FixedDeposit $fixedDeposit)
    {
        $before = (float) $fixedDeposit->accrued_interest;
        try {
            $this->fdService->accrueInterest($fixedDeposit, today()->toDateString());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        $added = round((float) $fixedDeposit->fresh()->accrued_interest - $before, 2);
        return back()->with('success', $added > 0 ? 'Booked ' . number_format($added, 2) . ' interest up to today.' : 'Nothing new to book — interest is up to date.');
    }

    /** Monthly statement: interest earned on each monthly anniversary up to the payout. */
    public function statement(FixedDeposit $fixedDeposit)
    {
        $fixedDeposit->load('client', 'product');
        $schedule = $fixedDeposit->monthlySchedule();
        $pdf = Pdf::loadView('pdf.fd-monthly-statement', compact('fixedDeposit', 'schedule'))->setPaper('a4', 'portrait');
        return $pdf->download("statement-{$fixedDeposit->deposit_number}.pdf");
    }
}
