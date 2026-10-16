<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\FixedDeposit;
use App\Models\Loan;
use App\Models\LoanSchedule;
use App\Models\MobileMoneyTransaction;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Models\SystemSetting;
use App\Services\LoanService;
use App\Services\MobileMoneyService;
use App\Services\SavingsService;
use Illuminate\Http\Request;

class ClientPortalController extends Controller
{
    public function __construct(protected LoanService $loanService, protected MobileMoneyService $mobileMoneyService, protected SavingsService $savingsService) {}

    private function abortIfMobileMoneyDisabled(): void
    {
        abort_unless((bool) SystemSetting::get('mobile_money_enabled', '0'), 403, 'Mobile money deposits/withdrawals are not currently available.');
    }

    private function mobileMoneyMinAmount(): float
    {
        return (float) SystemSetting::get('mobile_money_min_amount', 1000);
    }
    /** All clients linked to the logged-in user (pivot + same-email auto-discovery). */
    private function linkedClients()
    {
        $user = auth()->user();

        // Pivot-linked clients
        $clients = $user->portalClients;

        // Auto-discover: any client sharing this user's email (covers legacy client_id links
        // and cases where only one of multiple same-email clients was ever invited)
        if ($user->email) {
            $byEmail = \App\Models\Client::where('email', $user->email)->get();
            if ($byEmail->isNotEmpty()) {
                // Silently sync them into the pivot so future loads are consistent
                $user->portalClients()->syncWithoutDetaching($byEmail->pluck('id')->toArray());
                $clients = $clients->merge($byEmail)->unique('id');
            }
        }

        if ($clients->isEmpty()) {
            abort(403, 'No client profile linked to this account.');
        }

        return $clients;
    }

    /** The currently active client (from session, or first linked). */
    private function activeClient()
    {
        $clients  = $this->linkedClients();
        $activeId = session('portal_client_id');
        return $clients->firstWhere('id', $activeId) ?? $clients->first();
    }

    /** Switch the active client and redirect back to dashboard. */
    public function switchAccount(Client $client)
    {
        // Make sure the requested client is actually linked to this user
        abort_unless(auth()->user()->portalClients->contains($client->id), 403);
        session(['portal_client_id' => $client->id]);
        return redirect()->route('client-portal.dashboard');
    }

    public function overview()
    {
        session(['active_portal' => 'client']);
        $clients = $this->linkedClients();
        $client  = $this->activeClient();

        $clientIds = collect([$client->id]);

        $totalSavings  = SavingsAccount::where('client_id', $client->id)->sum('balance');
        $totalLoanDebt = Loan::where('client_id', $client->id)
            ->whereIn('status', ['active', 'disbursed', 'overdue', 'defaulted'])
            ->selectRaw('SUM(outstanding_principal + outstanding_interest + outstanding_penalty) as total')
            ->value('total') ?? 0;
        $totalFdValue  = FixedDeposit::where('client_id', $client->id)->whereIn('status', ['active', 'matured'])->sum('principal');
        $activeLoans   = Loan::where('client_id', $client->id)->whereIn('status', ['active', 'disbursed', 'overdue', 'defaulted'])->count();

        // Monthly savings trend — last 6 months
        $savingsAccountIds = SavingsAccount::where('client_id', $client->id)->pluck('id');
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->startOfMonth()->subMonths($i);
            $net = SavingsTransaction::whereIn('savings_account_id', $savingsAccountIds)
                ->whereMonth('transaction_date', $month->month)
                ->whereYear('transaction_date', $month->year)
                ->selectRaw("SUM(CASE WHEN transaction_type='deposit' THEN amount ELSE -amount END) as net")
                ->value('net') ?? 0;
            $trend[] = ['label' => $month->format('M Y'), 'amount' => (float) $net];
        }

        // Next loan due
        $nextDue = LoanSchedule::whereHas('loan', fn($q) => $q->where('client_id', $client->id))
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->where('due_date', '>=', today())
            ->orderBy('due_date')
            ->first();

        // Overdue installments
        $overdueCount = LoanSchedule::whereHas('loan', fn($q) => $q->where('client_id', $client->id))
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->where('due_date', '<', today())
            ->count();

        // Savings accounts
        $savingsAccounts = SavingsAccount::where('client_id', $client->id)->with('product')->get();

        // Active FDs
        $activeFds = FixedDeposit::where('client_id', $client->id)
            ->whereIn('status', ['active', 'matured'])->with('product')->orderBy('maturity_date')->get();
        $sharesValue = (float) \App\Models\MemberShare::where('client_id', $client->id)->where('status', '!=', 'liquidated')->sum('amount_paid');

        // This month cashflow
        $depositsThisMonth    = SavingsTransaction::whereIn('savings_account_id', $savingsAccountIds)
            ->where('transaction_type', 'deposit')
            ->whereYear('transaction_date', now()->year)->whereMonth('transaction_date', now()->month)
            ->sum('amount');
        $withdrawalsThisMonth = SavingsTransaction::whereIn('savings_account_id', $savingsAccountIds)
            ->where('transaction_type', 'withdrawal')
            ->whereYear('transaction_date', now()->year)->whereMonth('transaction_date', now()->month)
            ->sum('amount');

        // Active loans list
        $activeLoansData = Loan::where('client_id', $client->id)
            ->whereIn('status', ['active', 'disbursed', 'overdue', 'defaulted'])->with('product')->get();

        return view('client-portal.overview', compact(
            'client', 'clients', 'totalSavings', 'totalLoanDebt', 'totalFdValue',
            'activeLoans', 'trend', 'nextDue', 'overdueCount',
            'savingsAccounts', 'activeFds',
            'depositsThisMonth', 'withdrawalsThisMonth', 'activeLoansData', 'sharesValue'
        ));
    }

    public function accounts()
    {
        $clients = $this->linkedClients();
        $client  = $this->activeClient();
        $client->load(['savingsAccounts.product', 'loans.product', 'fixedDeposits.product', 'shares']);
        $groupMemberships = \App\Models\GroupMember::with('group')->where('client_id', $client->id)->get();

        return view('client-portal.accounts', compact('client', 'clients', 'groupMemberships'));
    }

    public function activity()
    {
        $clients           = $this->linkedClients();
        $client            = $this->activeClient();
        $savingsAccountIds = SavingsAccount::where('client_id', $client->id)->pluck('id');

        $transactions = SavingsTransaction::whereIn('savings_account_id', $savingsAccountIds)
            ->with('savingsAccount')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(30);

        return view('client-portal.activity', compact('client', 'clients', 'transactions'));
    }

    public function savingsStatement(SavingsAccount $savingsAccount)
    {
        $clients = $this->linkedClients();
        $client  = $this->activeClient();
        abort_if((int) $savingsAccount->client_id !== (int) $client->id, 403);

        // Same data as the staff savings page: every transaction oldest first with its running balance
        $savingsAccount->load([
            'client', 'product',
            'transactions' => fn ($q) => $q->reorder('transaction_date', 'asc')->orderBy('id', 'asc'),
        ]);

        return view('client-portal.savings', compact('client', 'clients', 'savingsAccount'));
    }

    public function loanStatement(Loan $loan)
    {
        $clients = $this->linkedClients();
        $client  = $this->activeClient();
        abort_if((int) $loan->client_id !== (int) $client->id, 403);

        // Same data as the staff loan statement
        $loan->load(['client', 'product', 'schedules', 'repayments' => fn ($q) => $q->orderBy('payment_date')->orderBy('id')]);
        $currentPenalty   = $this->loanService->calculatePenaltyPublic($loan);
        $penaltyBreakdown = $this->loanService->penaltyBreakdown($loan);

        return view('client-portal.loan', compact('client', 'clients', 'loan', 'currentPenalty', 'penaltyBreakdown'));
    }

    public function fdStatement(FixedDeposit $fixedDeposit)
    {
        $clients = $this->linkedClients();
        $client  = $this->activeClient();
        abort_if((int) $fixedDeposit->client_id !== (int) $client->id, 403);

        $fixedDeposit->load('product');

        return view('client-portal.fd', compact('client', 'clients', 'fixedDeposit'));
    }

    public function savingsStatementPdf(Request $request, SavingsAccount $savingsAccount)
    {
        $this->linkedClients();
        $client = $this->activeClient();
        abort_if((int) $savingsAccount->client_id !== (int) $client->id, 403);

        // Exactly the statement staff export: chosen period, opening and closing balance, PDF or Excel
        return app(\App\Http\Controllers\SavingsAccountController::class)->statementPdf($request, $savingsAccount);
    }

    public function loanStatementPdf(Loan $loan)
    {
        $this->linkedClients();
        $client = $this->activeClient();
        abort_if((int) $loan->client_id !== (int) $client->id, 403);

        return app(\App\Http\Controllers\LoanController::class)->statementPdf($loan);
    }

    public function fdStatementPdf(FixedDeposit $fixedDeposit)
    {
        $clients = $this->linkedClients();
        $client  = $this->activeClient();
        abort_if((int) $fixedDeposit->client_id !== (int) $client->id, 403);

        $fixedDeposit->load('product', 'client');
        $view = $fixedDeposit->product && ($fixedDeposit->product->isMonthlyRate() || $fixedDeposit->product->isAnniversary())
            ? 'pdf.investment-certificate' : 'pdf.fd-certificate';
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($view, compact('client', 'fixedDeposit'))->setPaper('a4', 'portrait');

        return $pdf->download("certificate-{$fixedDeposit->deposit_number}.pdf");
    }

    /** Monthly statement of an investment: interest earned on each monthly anniversary up to the payout. */
    public function fdMonthlyStatementPdf(FixedDeposit $fixedDeposit)
    {
        $this->linkedClients();
        $client = $this->activeClient();
        abort_if((int) $fixedDeposit->client_id !== (int) $client->id, 403);

        $fixedDeposit->load('product', 'client');
        $schedule = $fixedDeposit->monthlySchedule();
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.fd-monthly-statement', compact('fixedDeposit', 'schedule'))->setPaper('a4', 'portrait');

        return $pdf->download("statement-{$fixedDeposit->deposit_number}.pdf");
    }

    public function mobileMoneyIndex()
    {
        $clients = $this->linkedClients();
        $client  = $this->activeClient();

        // Re-check this client's own non-final transactions on every page load -- see
        // MobileMoneyController::index() for why this can't rely on cron alone here.
        MobileMoneyTransaction::where('client_id', $client->id)
            ->whereIn('status', ['pending', 'processing'])
            ->where('created_at', '>=', now()->subDays(3))
            ->get()
            ->each(fn ($mm) => $this->mobileMoneyService->reconcile($mm));

        $transactions = MobileMoneyTransaction::where('client_id', $client->id)
            ->with('savingsAccount')
            ->latest()
            ->paginate(20);

        return view('client-portal.mobile-money.index', compact('client', 'clients', 'transactions'));
    }

    public function depositForm()
    {
        $this->abortIfMobileMoneyDisabled();
        $clients = $this->linkedClients();
        $client  = $this->activeClient();
        $accounts = SavingsAccount::where('client_id', $client->id)->where('status', 'active')->with('product')->get();
        $minAmount = $this->mobileMoneyMinAmount();

        return view('client-portal.mobile-money.deposit', compact('client', 'clients', 'accounts', 'minAmount'));
    }

    public function deposit(Request $request)
    {
        $this->abortIfMobileMoneyDisabled();
        $client = $this->activeClient();
        $minAmount = $this->mobileMoneyMinAmount();

        $request->validate([
            'savings_account_id' => 'required|exists:savings_accounts,id',
            'amount'             => "required|numeric|min:{$minAmount}",
            'phone_number'       => 'required|string|max:20',
        ]);

        $account = SavingsAccount::where('client_id', $client->id)->where('status', 'active')->findOrFail($request->savings_account_id);

        try {
            $mm = $this->mobileMoneyService->initiateDeposit($client, $account, (float) $request->amount, $request->phone_number);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        if ($mm->status === 'failed') {
            return back()->withInput()->with('error', 'Could not start the deposit: ' . $mm->failure_reason);
        }

        return redirect()->route('client-portal.mobile-money.index')
            ->with('success', 'Deposit request sent. Check your phone to approve the mobile money prompt.');
    }

    public function withdrawForm()
    {
        $this->abortIfMobileMoneyDisabled();
        $clients = $this->linkedClients();
        $client  = $this->activeClient();
        $accounts = SavingsAccount::where('client_id', $client->id)->where('status', 'active')->with('product')->get();
        $minAmount = $this->mobileMoneyMinAmount();

        return view('client-portal.mobile-money.withdraw', compact('client', 'clients', 'accounts', 'minAmount'));
    }

    public function withdraw(Request $request)
    {
        $this->abortIfMobileMoneyDisabled();
        $client = $this->activeClient();
        $minAmount = $this->mobileMoneyMinAmount();

        $request->validate([
            'savings_account_id' => 'required|exists:savings_accounts,id',
            'amount'             => "required|numeric|min:{$minAmount}",
            'phone_number'       => 'required|string|max:20',
        ]);

        $account = SavingsAccount::where('client_id', $client->id)->where('status', 'active')->findOrFail($request->savings_account_id);

        if (!$account->canWithdraw((float) $request->amount)) {
            return back()->withInput()->with('error', 'Insufficient balance for this withdrawal (minimum balance rules apply).');
        }

        try {
            $this->mobileMoneyService->requestWithdrawal($client, $account, (float) $request->amount, $request->phone_number);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('client-portal.mobile-money.index')
            ->with('success', 'Withdrawal request submitted. It will be paid out once approved by staff.');
    }

    public function mobileMoneyStatus(MobileMoneyTransaction $mobileMoneyTransaction)
    {
        $client = $this->activeClient();
        abort_if((int) $mobileMoneyTransaction->client_id !== (int) $client->id, 403);

        // Actively re-check with MarzPay on every poll rather than waiting on the webhook --
        // the webhook may be delayed or unreachable (e.g. misconfigured APP_URL), so this is
        // what actually makes the status update feel instant to the client.
        $this->mobileMoneyService->reconcile($mobileMoneyTransaction);
        $mobileMoneyTransaction->refresh();

        return response()->json([
            'status'         => $mobileMoneyTransaction->status,
            'failure_reason' => $mobileMoneyTransaction->failure_reason,
        ]);
    }

    public function mobileMoneyCancel(MobileMoneyTransaction $mobileMoneyTransaction)
    {
        $client = $this->activeClient();
        abort_if((int) $mobileMoneyTransaction->client_id !== (int) $client->id, 403);

        $result = $this->mobileMoneyService->cancelDeposit($mobileMoneyTransaction);

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
