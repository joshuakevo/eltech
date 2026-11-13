<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Loan;
use App\Models\LoanWriteOff;
use App\Models\LoanWriteOffRecovery;
use App\Models\SystemSetting;
use App\Services\LoanWriteOffService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Loan write-offs: "Write off" on a Locked-Up loan, Undo, the Written-Off tab and its account settings. */
class LoanWriteOffController extends Controller
{
    public function __construct(protected LoanWriteOffService $service) {}

    public function index(Request $request)
    {
        $writeOffs = LoanWriteOff::with('loan.client', 'createdBy')->withSum('recoveries as recovered_sum', 'amount')
            ->where('status', 'applied')
            ->when($request->search, fn ($q) => $q->whereHas('loan', fn ($l) => $l->where('loan_number', 'like', "%{$request->search}%")
                ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$request->search}%")->orWhere('client_number', 'like', "%{$request->search}%"))))
            ->orderByDesc('writeoff_date')->orderByDesc('id')->get();

        return view('loans.write-offs', [
            'writeOffs' => $writeOffs,
            'accounts'  => $this->service->accounts(),
            'settings'  => [
                'expense'         => SystemSetting::get('loan_writeoff_expense_account', '5121'),
                'interest_debit'  => SystemSetting::get('loan_writeoff_interest_debit_account', '1106'),
                'interest_credit' => SystemSetting::get('loan_writeoff_interest_credit_account', '1112'),
                'recovered'       => SystemSetting::get('bad_debts_recovered_account', '4116'),
            ],
            'recoveredAccount' => $this->service->recoveredAccount(),
            'allAccounts' => Account::where('is_active', true)->orderBy('account_code')->get(['account_code', 'account_name', 'account_type']),
        ]);
    }

    public function store(Request $request, Loan $loan)
    {
        $data = $request->validate([
            'writeoff_date' => 'required|date',
            'reason'        => 'required|string|max:500',
        ]);

        try {
            $wo = $this->service->writeOff($loan, $data);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->implode(' '))->withInput();
        }

        return back()->with('success', "{$loan->loan_number} written off: principal " . number_format($wo->principal, 2)
            . ' (DR Bad Loan Write-Off), interest ' . number_format($wo->interest, 2) . ' (DR Accrued Interest).');
    }

    public function undo(Loan $loan, LoanWriteOff $writeOff)
    {
        abort_unless((int) $writeOff->loan_id === (int) $loan->id, 404);

        try {
            $this->service->undo($writeOff);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', "Write-off of {$loan->loan_number} undone; the loan is back in Locked-Up Loans.");
    }

    /** Bad debt recovered on a written-off loan (permission: repay loans). */
    public function recover(Request $request, Loan $loan)
    {
        $data = $request->validate([
            'recovery_date'      => 'required|date',
            'amount'             => 'required|numeric|min:0.01',
            'source'             => 'required|in:cash,savings',
            'payment_account_id' => 'nullable|integer',
            'savings_account_id' => 'nullable|integer',
            'notes'              => 'nullable|string|max:255',
        ]);

        try {
            $rec = $this->service->recover($loan, $data);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->implode(' '))->withInput();
        }

        return back()->with('success', number_format($rec->amount, 2) . " recovered on {$loan->loan_number} (CR Bad Debts Recovered).");
    }

    public function undoRecovery(Loan $loan, LoanWriteOffRecovery $recovery)
    {
        abort_unless((int) $recovery->loan_id === (int) $loan->id, 404);
        $this->service->undoRecovery($recovery);

        return back()->with('success', 'Recovery of ' . number_format($recovery->amount, 2) . ' undone.');
    }

    public function saveSettings(Request $request)
    {
        $codes = Account::pluck('account_code')->all();
        $data = $request->validate([
            'loan_writeoff_expense_account'         => ['required', 'in:' . implode(',', $codes)],
            'loan_writeoff_interest_debit_account'  => ['required', 'in:' . implode(',', $codes)],
            'loan_writeoff_interest_credit_account' => ['required', 'in:' . implode(',', $codes)],
            'bad_debts_recovered_account'           => ['required', 'in:' . implode(',', $codes)],
        ]);
        foreach ($data as $key => $value) {
            SystemSetting::set($key, $value);
        }
        \App\Models\AuditLog::record('settings_updated', 'Loan write-off accounts: principal DR ' . $data['loan_writeoff_expense_account']
            . ', interest DR ' . $data['loan_writeoff_interest_debit_account'] . ' / CR ' . $data['loan_writeoff_interest_credit_account']
            . ', recoveries CR ' . $data['bad_debts_recovered_account'], 'settings');

        return back()->with('success', 'Write-off accounts saved.');
    }
}
