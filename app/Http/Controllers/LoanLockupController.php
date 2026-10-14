<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanLockup;
use App\Services\LoanLockupService;
use Illuminate\Http\Request;

/** Four-eyes lock-up of normal loans: request → two approvals (Ceo manager + Credit manager) → Locked-Up. */
class LoanLockupController extends Controller
{
    public function __construct(protected LoanLockupService $lockups) {}

    public function index()
    {
        $pending = LoanLockup::with('loan.client', 'loan.product', 'requester')->where('status', 'pending')->latest('id')->get();
        $recent  = LoanLockup::with('loan.client', 'requester', 'rejecter')->where('status', '!=', 'pending')->latest('updated_at')->take(30)->get();
        $roles   = $this->lockups->approverRoles();

        return view('loans.lockups', compact('pending', 'recent', 'roles'));
    }

    public function store(Request $request, Loan $loan)
    {
        $data = $request->validate([
            'lockup_date' => ['required', 'date', 'before_or_equal:today'],
            'reason'      => 'required|string|max:500',
        ]);
        $this->lockups->request($loan, $data['lockup_date'], $data['reason']);

        return redirect()->route('loans.show', $loan)->with('success', 'Lock-up request sent for approval by a ' . implode(' and a ', $this->lockups->approverRoles()) . '.');
    }

    public function approve(Request $request, LoanLockup $lockup)
    {
        $request->validate(['note' => 'nullable|string|max:300']);
        $lockup = $this->lockups->approve($lockup, $request->user(), $request->note);

        return back()->with('success', $lockup->status === 'executed'
            ? "Approved. {$lockup->loan->loan_number} has been moved to Locked-Up Loans."
            : 'Approved. Waiting for the second approval.');
    }

    public function reject(Request $request, LoanLockup $lockup)
    {
        $request->validate(['reason' => 'required|string|max:500']);
        $this->lockups->reject($lockup, $request->user(), $request->reason);

        return back()->with('success', 'Lock-up request ' . $lockup->fresh()->status . '.');
    }

    public function undo(LoanLockup $lockup)
    {
        $this->lockups->undo($lockup);

        return redirect()->route('loans.show', $lockup->loan_id)->with('success', 'Lock-up undone — the loan is back as it was.');
    }
}
