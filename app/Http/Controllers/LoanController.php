<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Loan;
use App\Models\LoanGuarantor;
use App\Models\LoanProduct;
use App\Models\SavingsAccount;
use App\Services\LoanService;
use App\Services\SavingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    public function __construct(
        protected LoanService $loanService,
        protected SavingsService $savingsService,
    ) {}

    public function index(Request $request)
    {
        $lockedUpProductId = LoanProduct::where('name', 'Locked-Up Loans')->value('id');
        $type = in_array($request->type, ['locked-up', 'closed']) ? $request->type : 'normal';

        $filtered = Loan::query()
            ->when($lockedUpProductId && $type !== 'closed', fn($q) => $type === 'locked-up'
                ? $q->where('loan_product_id', $lockedUpProductId)
                : $q->where(fn($q2) => $q2->where('loan_product_id', '!=', $lockedUpProductId)->orWhereNull('loan_product_id')))
            ->when($type === 'closed', fn($q) => $q->where('status', 'closed'))
            ->when($type !== 'closed' && !$request->status, fn($q) => $q->where('status', '!=', 'closed'))
            ->when($type !== 'closed' && $request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->where('loan_number', 'like', "%{$request->search}%")
                ->orWhereHas('client', fn($q2) => $q2->where('name', 'like', "%{$request->search}%")));

        $totalOutstanding = (clone $filtered)->sum('outstanding_principal');
        $totalInterest    = (clone $filtered)->sum('outstanding_interest');
        $totalCount       = (clone $filtered)->count();

        if ($request->format === 'pdf') {
            $all = (clone $filtered)->with('client', 'product')->orderByDesc('disbursement_date')->get();
            $pdf = Pdf::loadView('pdf.loans', [
                'loans'            => $all,
                'totalOutstanding' => $totalOutstanding,
                'totalInterest'    => $totalInterest,
                'totalCount'       => $totalCount,
                'type'             => $type,
            ])->setPaper('a4', 'landscape');
            return $pdf->download('loans-' . now()->format('Y-m-d') . '.pdf');
        }

        if ($request->format === 'excel') {
            $all = (clone $filtered)->with('client', 'product')->orderByDesc('disbursement_date')->get();
            $header = $type === 'locked-up'
                ? ['Loan #', 'Client', 'Client #', 'Product', 'Principal', 'Interest', 'Disbursed', 'Status']
                : ['Loan #', 'Client', 'Client #', 'Product', 'Principal', 'Outstanding', 'Disbursed', 'Status'];
            $rows = [$header];
            foreach ($all as $loan) {
                $rows[] = [
                    $loan->loan_number,
                    $loan->client->name ?? '',
                    $loan->client->client_number ?? '',
                    $loan->product->name ?? '',
                    $loan->principal,
                    $type === 'locked-up' ? $loan->outstanding_interest : $loan->outstanding_principal,
                    $loan->disbursement_date ? $loan->disbursement_date->format('Y-m-d') : '',
                    ucfirst($loan->status),
                ];
            }
            $rows[] = $type === 'locked-up'
                ? ['', '', '', 'TOTAL', $totalOutstanding, $totalInterest, '', $totalCount . ' loans']
                : ['', '', '', 'TOTAL', '', $totalOutstanding, '', $totalCount . ' loans'];
            return $this->csvDownload($rows, 'loans-' . now()->format('Y-m-d'));
        }

        $loans = $filtered->with('client', 'product')
            ->orderByDesc('disbursement_date')
            ->paginate(20);

        return view('loans.index', compact('loans', 'totalOutstanding', 'totalInterest', 'totalCount', 'type'));
    }

    public function create(Request $request)
    {
        $clients  = Client::where('status', 'active')->orderBy('name')->get();
        $products = LoanProduct::where('is_active', true)->get();
        $selectedClient = $request->client_id ? Client::find($request->client_id) : null;

        return view('loans.create', compact('clients', 'products', 'selectedClient'));
    }

    /**
     * "Run Loans" — active loans grouped by their anniversary day-of-month
     * (the disbursement day, which every installment due date is anchored
     * to), so a collector can pick a date and see everyone due that day
     * along with when each one last paid.
     */
    public function run(Request $request, \App\Services\LoanInterestService $interest)
    {
        $date = $request->date ? \Carbon\Carbon::parse($request->date)->startOfDay() : today();
        $day  = $date->day;

        $lockedUpProductId = LoanProduct::where('name', 'Locked-Up Loans')->value('id');

        // Loans due on this date: anniversary day-of-month, or an installment falling on the date
        // (covers month-end due dates that shift, e.g. 31st -> 30th).
        $loans = Loan::with(['client', 'product'])
            ->where('status', 'active')
            ->whereNotNull('disbursement_date')
            ->where(fn ($q) => $q->whereRaw('DAY(disbursement_date) = ?', [$day])
                ->orWhereHas('schedules', fn ($s) => $s->whereDate('due_date', $date->toDateString())))
            ->when($lockedUpProductId, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('loan_product_id', '!=', $lockedUpProductId)
                ->orWhereNull('loan_product_id')))
            ->when($request->search, fn ($q) => $q->where(fn ($q2) => $q2->where('loan_number', 'like', "%{$request->search}%")
                ->orWhereHas('client', fn ($q3) => $q3->where('name', 'like', "%{$request->search}%"))))
            ->get()
            ->sortBy(fn ($loan) => $loan->client->name ?? '')
            ->values();

        // Preview = the real run, rolled back -- so what you see is exactly what Run will do.
        $previews = $loans->mapWithKeys(fn ($loan) => [$loan->id => $interest->preview($loan, $date)]);

        $totals = [
            'principal' => $loans->sum('outstanding_principal'),
            'interest'  => $loans->sum('outstanding_interest'),
            'charge'    => $previews->sum(fn ($p) => collect($p['steps'])->sum('accrued')),
            'recover'   => $previews->sum(fn ($p) => collect($p['steps'])->sum(fn ($s) => $s['recovery']['recovered'] ?? 0)),
            'expected'  => $previews->sum(fn ($p) => collect($p['steps'])->sum(fn ($s) => $s['recovery']['expected'] ?? 0)),
        ];
        $totalCount = $loans->count();

        return view('loans.run', compact('loans', 'previews', 'date', 'day', 'totals', 'totalCount'));
    }

    /** Runs the selected loans for the date: charge day-based interest, recover from savings. */
    public function runProcess(Request $request, \App\Services\LoanInterestService $interest)
    {
        $request->validate([
            'date'       => ['required', 'date', 'before_or_equal:today'],
            'loan_ids'   => 'required|array|min:1',
            'loan_ids.*' => 'integer|exists:loans,id',
        ]);
        $date = \Carbon\Carbon::parse($request->date)->startOfDay();

        $results = [];
        foreach (Loan::with('client')->whereIn('id', $request->loan_ids)->get() as $loan) {
            try {
                $r = $interest->run($loan, $date);
            } catch (\Throwable $e) {
                $r = ['loan_id' => $loan->id, 'steps' => [], 'errors' => [$e->getMessage()]];
            }
            $results[] = [
                'loan'      => $loan->loan_number,
                'client'    => $loan->client->name ?? '—',
                'charged'   => round(collect($r['steps'])->sum('accrued'), 2),
                'recovered' => round(collect($r['steps'])->sum(fn ($s) => $s['recovery']['recovered'] ?? 0), 2),
                'expected'  => round(collect($r['steps'])->sum(fn ($s) => $s['recovery']['expected'] ?? 0), 2),
                'errors'    => $r['errors'] ?? [],
            ];
        }

        $ok = collect($results)->filter(fn ($r) => !$r['errors'])->count();
        return redirect()->route('loans.run', ['date' => $date->toDateString()])
            ->with('run_results', $results)
            ->with('success', "Run complete for {$date->format('d M Y')}: {$ok} of " . count($results) . ' loans processed, '
                . number_format(collect($results)->sum('charged'), 0) . ' interest charged, '
                . number_format(collect($results)->sum('recovered'), 0) . ' recovered from savings.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id'       => 'required|exists:clients,id',
            'loan_product_id' => 'required|exists:loan_products,id',
            'principal'       => 'required|numeric|min:1',
            'interest_rate'   => 'nullable|numeric|min:0',
            'interest_method' => 'nullable|in:flat,reducing',
            'term_months'     => 'nullable|integer|min:1',
            'notes'           => 'nullable|string',
            // Guarantors
            'guarantors'                    => 'nullable|array',
            'guarantors.*.name'             => 'required_with:guarantors|string|max:100',
            'guarantors.*.phone'            => 'nullable|string|max:20',
            'guarantors.*.id_number'        => 'nullable|string|max:30',
            'guarantors.*.relationship'     => 'nullable|string|max:50',
            'guarantors.*.address'          => 'nullable|string|max:200',
            'guarantors.*.employer'         => 'nullable|string|max:100',
            'guarantors.*.monthly_income'   => 'nullable|numeric|min:0',
        ]);

        $loan = $this->loanService->createLoan($data);

        // Save guarantors
        if (!empty($data['guarantors'])) {
            foreach ($data['guarantors'] as $g) {
                if (empty(trim($g['name'] ?? ''))) continue;
                LoanGuarantor::create([
                    'loan_id'       => $loan->id,
                    'name'          => $g['name'],
                    'phone'         => $g['phone'] ?? null,
                    'id_number'     => $g['id_number'] ?? null,
                    'relationship'  => $g['relationship'] ?? null,
                    'address'       => $g['address'] ?? null,
                    'employer'      => $g['employer'] ?? null,
                    'monthly_income'=> $g['monthly_income'] ?? null,
                ]);
            }
        }

        return redirect()->route('loans.show', $loan)->with('success', 'Loan application created. Pending disbursement.');
    }

    public function show(Loan $loan)
    {
        $loan->load('client', 'product', 'schedules', 'repayments.receivedBy', 'createdBy', 'guarantors');
        $schedulePreview = $loan->status === 'pending'
            ? $this->loanService->previewSchedule($loan)
            : [];

        // Savings accounts for this client (for fee deduction)
        $clientSavingsAccounts = $loan->status === 'pending'
            ? SavingsAccount::where('client_id', $loan->client_id)->where('status', 'active')->with('product')->get()
            : collect();

        $currentPenalty   = $this->loanService->calculatePenaltyPublic($loan);
        $penaltyBreakdown = $this->loanService->penaltyBreakdown($loan);

        $paymentSourceAccounts = $loan->status === 'pending'
            ? \App\Models\Account::where('is_payment_source', true)->where('is_active', true)->orderBy('account_code')->get()
            : collect();

        $corrections    = \App\Models\LoanCorrection::with('createdBy', 'transaction', 'offsetAccount')
            ->where('loan_id', $loan->id)->latest('id')->get();
        $offsetAccounts = app(\App\Services\LoanCorrectionService::class)->offsetAccounts();

        return view('loans.show', compact('loan', 'schedulePreview', 'clientSavingsAccounts', 'currentPenalty', 'penaltyBreakdown', 'paymentSourceAccounts', 'corrections', 'offsetAccounts'));
    }

    // ── Balance correction ──────────────────────────────────────────────────

    private function correctionInput(Request $request): array
    {
        $request->validate([
            'as_at_date'        => 'required|date',
            'principal'         => 'required|numeric|min:0',
            'interest'          => 'nullable|numeric|min:0',
            'journal_date'      => 'nullable|date',
            'offset_account_id' => 'nullable|integer',
            'no_journal'        => 'nullable|boolean',
        ]);
        return $request->only(['as_at_date', 'principal', 'interest', 'journal_date', 'offset_account_id', 'no_journal']);
    }

    /** JSON preview of a correction (nothing is saved). */
    public function correctionPreview(Request $request, Loan $loan, \App\Services\LoanCorrectionService $corrections)
    {
        return response()->json($corrections->build($loan, $this->correctionInput($request)));
    }

    public function correct(Request $request, Loan $loan, \App\Services\LoanCorrectionService $corrections)
    {
        $input = $this->correctionInput($request);
        $request->validate(['reason' => 'required|string|max:500']);

        $correction = $corrections->apply($loan, $input, $request->reason);

        return redirect()->route('loans.show', $loan)->with('success',
            'Loan balances corrected: principal ' . number_format($correction->new_principal, 0)
            . ', interest ' . number_format($correction->new_interest, 0)
            . ($correction->transaction ? '. Adjustment journal ' . $correction->transaction->reference . ' posted.' : '. No journal posted.'));
    }

    public function undoCorrection(Request $request, Loan $loan, \App\Models\LoanCorrection $correction, \App\Services\LoanCorrectionService $corrections)
    {
        abort_unless($correction->loan_id === $loan->id, 404);
        $request->validate(['reversal_date' => ['nullable', 'date', 'before_or_equal:today']]);
        $corrections->undo($correction, true, $request->reversal_date);

        return redirect()->route('loans.show', $loan)->with('success', 'Correction undone — balances and schedule restored.');
    }

    public function disburse(Request $request, Loan $loan)
    {
        $request->validate([
            'disbursement_date'        => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
            'disbursement_account_id'  => 'required|exists:accounts,id',
            'application_fee_amount'   => 'required|numeric|min:0',
            'application_fee_method'   => 'required|in:loan,savings',
            'management_fee_rate'      => 'required|numeric|min:0|max:100',
            'management_fee_method'    => 'required|in:loan,savings',
            'insurance_fee_rate'       => 'required|numeric|min:0|max:100',
            'insurance_fee_method'     => 'required|in:loan,savings',
            'fee_savings_account_id'   => 'nullable|exists:savings_accounts,id',
        ]);

        // Savings account required if any fee method is savings
        $needsSavings = in_array('savings', [
            $request->application_fee_method,
            $request->management_fee_method,
            $request->insurance_fee_method,
        ]);
        if ($needsSavings && empty($request->fee_savings_account_id)) {
            return back()->withErrors(['fee_savings_account_id' => 'A savings account is required when any fee is set to deduct from savings.'])->withInput();
        }

        if ($loan->status !== 'pending') {
            return back()->with('error', 'Only pending loans can be disbursed.');
        }

        try {
            $this->loanService->disburseLoan($loan, $request->disbursement_date, [
                'disbursement_account_id' => $request->disbursement_account_id,
                'savings_account_id'      => $request->fee_savings_account_id,
                'application_fee_amount'  => $request->application_fee_amount,
                'application_fee_method'  => $request->application_fee_method,
                'management_fee_rate'     => $request->management_fee_rate,
                'management_fee_method'   => $request->management_fee_method,
                'insurance_fee_rate'      => $request->insurance_fee_rate,
                'insurance_fee_method'    => $request->insurance_fee_method,
            ]);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('loans.show', $loan)->with('success', 'Loan disbursed successfully. Schedule generated.');
    }

    public function repayForm(Loan $loan)
    {
        if ($loan->status !== 'active' && !($loan->isLockedUp() && $loan->status === 'defaulted')) {
            return back()->with('error', 'Only active loans can accept repayments.');
        }

        $loan->load('client', 'product', 'schedules');

        // Next unpaid/partial installment
        $nextInstallment = $loan->schedules
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->sortBy('installment_no')
            ->first();

        // Overdue installments count
        $overdueInstallments = $loan->schedules
            ->filter(fn($s) => $s->isOverdue() && $s->status !== 'paid');

        // Amount owed on overdue installments (excluding penalty)
        $overdueAmount = $overdueInstallments->sum(
            fn($s) => ($s->principal_due - $s->principal_paid) + ($s->interest_due - $s->interest_paid)
        );

        // Suggested = next installment remaining (principal + interest)
        $suggestedAmount = $nextInstallment
            ? round(($nextInstallment->principal_due - $nextInstallment->principal_paid) + ($nextInstallment->interest_due - $nextInstallment->interest_paid), 2)
            : round($loan->total_outstanding, 2);

        // Client savings accounts
        $savingsAccounts = \App\Models\SavingsAccount::where('client_id', $loan->client_id)
            ->where('status', 'active')
            ->with('product')
            ->get();

        // Penalty
        $penaltyDue = $this->loanService->calculatePenaltyPublic($loan);

        // Early settlement amount (principal + interest due to today only)
        $earlySettlement = $this->loanService->calculateEarlySettlement($loan, today()->toDateString());

        // Schedules as JSON for JS allocation preview
        $schedulesJson = $loan->schedules
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->sortBy('installment_no')
            ->values()
            ->map(fn($s) => [
                'installment_no' => $s->installment_no,
                'interest_rem'   => round(max(0, $s->interest_due - $s->interest_paid), 2),
                'principal_rem'  => round(max(0, $s->principal_due - $s->principal_paid), 2),
            ]);

        $paymentSourceAccounts = \App\Models\Account::where('is_payment_source', true)->where('is_active', true)->orderBy('account_code')->get();

        return view('loans.repay', compact(
            'loan', 'nextInstallment', 'overdueInstallments', 'overdueAmount',
            'suggestedAmount', 'savingsAccounts', 'penaltyDue', 'earlySettlement', 'schedulesJson', 'paymentSourceAccounts'
        ));
    }

    /**
     * Live penalty preview for the repayment form: recomputes penalty as of
     * whatever payment_date the cashier has selected (not today), so a
     * backdated recovery date shows the same $0 (or reduced) penalty it will
     * actually be charged on submit.
     */
    public function penaltyPreview(Request $request, Loan $loan)
    {
        $request->validate(['payment_date' => 'required|date']);

        return response()->json([
            'penalty' => $this->loanService->calculatePenaltyPublic($loan, $request->payment_date),
        ]);
    }

    public function repay(Request $request, Loan $loan)
    {
        $request->validate([
            'payment_date'              => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
            'amount'                    => 'required|numeric|min:0.01',
            'payment_source_account_id' => 'required',
            'savings_account_id'        => 'required_if:payment_source_account_id,savings|nullable|exists:savings_accounts,id',
            'reference'                 => 'nullable|string|max:100|unique:transactions,reference',
            'notes'                     => 'nullable|string',
        ]);

        $isSavings = $request->payment_source_account_id === 'savings';
        $request->merge([
            'payment_method'            => $isSavings ? 'savings' : 'direct',
            'payment_source_account_id' => $isSavings ? null : (int) $request->payment_source_account_id,
            'reference'                 => $request->reference ?: 'LR-' . $loan->loan_number . '-' . now()->format('YmdHis'),
        ]);

        if ($loan->status !== 'active' && !($loan->isLockedUp() && $loan->status === 'defaulted')) {
            return back()->with('error', 'Only active loans can accept repayments.');
        }

        $total = $loan->outstanding_principal + $loan->outstanding_interest + $loan->outstanding_penalty;
        if ($request->amount > $total + 0.01) {
            return back()->withErrors(['amount' => 'Repayment cannot exceed total outstanding balance.'])->withInput();
        }

        // Deduct from savings account if payment method is savings
        if ($request->payment_method === 'savings' && $request->savings_account_id) {
            $savingsAccount = SavingsAccount::findOrFail($request->savings_account_id);
            try {
                $this->savingsService->withdraw(
                    $savingsAccount,
                    $request->amount,
                    $request->payment_date,
                    "Loan repayment - {$loan->loan_number}",
                    null,  // let accounting auto-generate; user's reference is reserved for the loan repayment journal
                    0.0   // no withdrawal fee on loan recoveries
                );
            } catch (\InvalidArgumentException $e) {
                return back()->with('error', 'Savings withdrawal failed: ' . $e->getMessage())->withInput();
            }
        }

        $this->loanService->processRepayment($loan, $request->all());

        return redirect()->route('loans.show', $loan)->with('success', 'Repayment processed successfully.');
    }

    public function storeGuarantor(Request $request, Loan $loan)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'phone'         => 'nullable|string|max:20',
            'id_number'     => 'nullable|string|max:30',
            'relationship'  => 'nullable|string|max:50',
            'address'       => 'nullable|string|max:200',
            'employer'      => 'nullable|string|max:100',
            'monthly_income'=> 'nullable|numeric|min:0',
        ]);

        LoanGuarantor::create(array_merge($data, ['loan_id' => $loan->id]));

        return back()->with('success', 'Guarantor added successfully.');
    }

    public function destroyGuarantor(Loan $loan, LoanGuarantor $guarantor)
    {
        abort_if((int) $guarantor->loan_id !== (int) $loan->id, 403);
        $guarantor->delete();
        return back()->with('success', 'Guarantor removed.');
    }

    public function schedule(Loan $loan)
    {
        $loan->load('schedules', 'client', 'product');
        $schedulePreview  = $loan->status === 'pending'
            ? $this->loanService->previewSchedule($loan)
            : [];
        $currentPenalty   = $this->loanService->calculatePenaltyPublic($loan);
        $penaltyBreakdown = $this->loanService->penaltyBreakdown($loan);
        return view('loans.schedule', compact('loan', 'schedulePreview', 'currentPenalty', 'penaltyBreakdown'));
    }

    public function statement(Loan $loan)
    {
        $loan->load(['client', 'product', 'schedules', 'repayments' => fn($q) => $q->with('receivedBy')->orderBy('payment_date')->orderBy('id')]);
        $currentPenalty   = $this->loanService->calculatePenaltyPublic($loan);
        $penaltyBreakdown = $this->loanService->penaltyBreakdown($loan);
        return view('loans.statement', compact('loan', 'currentPenalty', 'penaltyBreakdown'));
    }

    public function statementPdf(Loan $loan)
    {
        $loan->load(['client', 'product', 'schedules', 'repayments' => fn($q) => $q->with('receivedBy')->orderBy('payment_date')->orderBy('id')]);
        $currentPenalty   = $this->loanService->calculatePenaltyPublic($loan);
        $penaltyBreakdown = $this->loanService->penaltyBreakdown($loan);
        $pdf = Pdf::loadView('pdf.loan-statement', compact('loan', 'currentPenalty', 'penaltyBreakdown'))
            ->setPaper('a4', 'portrait');
        return $pdf->download("loan-statement-{$loan->loan_number}.pdf");
    }

    public function schedulePdf(Loan $loan)
    {
        $loan->load('client', 'product', 'schedules');
        $schedulePreview  = $loan->status === 'pending'
            ? $this->loanService->previewSchedule($loan)
            : [];
        $currentPenalty   = $this->loanService->calculatePenaltyPublic($loan);
        $penaltyBreakdown = $this->loanService->penaltyBreakdown($loan);
        $pdf = Pdf::loadView('pdf.loan-schedule', compact('loan', 'schedulePreview', 'currentPenalty', 'penaltyBreakdown'))
            ->setPaper('a4', 'portrait');
        return $pdf->download("loan-schedule-{$loan->loan_number}.pdf");
    }

    public function destroy(Loan $loan)
    {
        if ($loan->status !== 'pending') {
            return back()->with('error', 'Only pending loans can be deleted.');
        }
        $loan->guarantors()->delete();
        $loan->delete();
        return redirect()->route('loans.index')->with('success', 'Pending loan deleted.');
    }
}
