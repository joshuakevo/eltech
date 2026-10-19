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
        $orphans = $this->links->orphans();
        return view('loans.orphan-recoveries', compact('orphans'));
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
