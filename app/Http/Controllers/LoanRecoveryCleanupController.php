<?php

namespace App\Http\Controllers;

use App\Models\SavingsTransaction;
use App\Services\LoanRecoveryLinkService;
use Illuminate\Http\Request;

/** Leftover savings withdrawals ("Loan repayment - LN-…") whose loan repayment was deleted — review and remove. */
class LoanRecoveryCleanupController extends Controller
{
    public function __construct(protected LoanRecoveryLinkService $links) {}

    public function index()
    {
        $orphans  = $this->links->orphans();
        $overpaid = $this->links->overpaid();
        return view('loans.orphan-recoveries', compact('orphans', 'overpaid'));
    }

    public function fixOverpaid(Request $request)
    {
        $request->validate(['repayment_ids' => 'required|array|min:1', 'repayment_ids.*' => 'integer']);
        $total = 0.0;
        $n = 0;
        foreach (\App\Models\LoanRepayment::with('loan')->whereIn('id', $request->repayment_ids)->get() as $r) {
            try {
                $fixed = $this->links->fixOverpaid($r);
            } catch (\RuntimeException $e) {
                return redirect()->route('loans.orphan-recoveries')->with('error', $e->getMessage());
            }
            if ($fixed > 0) {
                $total += $fixed;
                $n++;
            }
        }
        return redirect()->route('loans.orphan-recoveries')
            ->with('success', "Corrected {$n} overpaid repayment(s) — " . number_format($total, 2) . " back on members' savings.");
    }

    public function destroy(Request $request)
    {
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer']);

        // Only remove rows that are still leftovers right now
        $still = $this->links->orphans()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $removed = 0;
        $total = 0.0;
        foreach (SavingsTransaction::with('savingsAccount')->whereIn('id', $request->ids)->get() as $w) {
            if (!in_array((int) $w->id, $still, true)) {
                continue;
            }
            $total += (float) $w->amount;
            $this->links->removeWithdrawal($w, 'its loan repayment no longer exists (cleanup)');
            $removed++;
        }

        return redirect()->route('loans.orphan-recoveries')
            ->with('success', "Removed {$removed} leftover withdrawal(s) — " . number_format($total, 2) . " back on members' savings.");
    }
}
