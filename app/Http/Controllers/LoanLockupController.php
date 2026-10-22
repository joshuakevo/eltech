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
        // No automatic email: a super admin sends reminders from the Lock-up Approvals page when needed

        return redirect()->route('loans.show', $loan)->with('success', 'Lock-up request sent for approval by a ' . implode(' and a ', $this->lockups->approverRoles()) . '.');
    }

    /** Emails the approvers still to act on one request — super admin only, at most once every 30 minutes. */
    public function remind(LoanLockup $lockup)
    {
        if ($lockup->status !== 'pending') {
            return back()->with('error', 'That request is no longer waiting for approval.');
        }
        if ($lockup->reminded_at && $lockup->reminded_at->gt(now()->subMinutes(30))) {
            return back()->with('error', 'The approvers were emailed ' . $lockup->reminded_at->diffForHumans() . ' — you can remind them again after 30 minutes.');
        }
        $r = $this->lockups->remindApprovers($lockup);
        return back()->with($r['failed'] && !$r['sent'] ? 'error' : 'success', $this->reminderMessage($lockup, $r));
    }

    /** Emails the approvers of every pending request that wasn't reminded in the last 30 minutes. */
    public function remindAll()
    {
        $sent = 0;
        $people = [];
        $failed = [];
        foreach (LoanLockup::where('status', 'pending')->get() as $lockup) {
            if ($lockup->reminded_at && $lockup->reminded_at->gt(now()->subMinutes(30))) {
                continue;
            }
            $r = $this->lockups->remindApprovers($lockup);
            $sent += count($r['sent']);
            $people = array_merge($people, $r['sent']);
            $failed = array_merge($failed, $r['failed']);
        }
        if (!$sent && !$failed) {
            return back()->with('error', 'Nothing to remind — no approver is waiting, or everyone was reminded in the last 30 minutes.');
        }
        return back()->with($failed && !$sent ? 'error' : 'success', "Sent {$sent} reminder email(s) to " . implode(', ', array_unique($people)) . '.' . ($failed ? ' Failed: ' . implode('; ', array_slice($failed, 0, 3)) : ''));
    }

    private function reminderMessage(LoanLockup $lockup, array $r): string
    {
        if (!$r['sent'] && !$r['failed']) {
            return 'No approver to remind — everyone who can approve has done so, or they have no email address.';
        }
        return ($r['sent'] ? 'Reminder emailed to ' . implode(', ', $r['sent']) . " for {$lockup->loan->loan_number}." : '') . ($r['failed'] ? ' Failed: ' . implode('; ', $r['failed']) : '');
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
