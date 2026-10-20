<?php

namespace App\Http\Controllers;

use App\Services\LoanPenaltyBackfillService;
use Illuminate\Http\Request;

/** One-off: penalties for installments paid late / short / unpaid between the 31/07/2026 transfer and go-live. */
class PenaltyBackfillController extends Controller
{
    public function __construct(protected LoanPenaltyBackfillService $backfill) {}

    public function index(Request $request)
    {
        $plan = $this->backfill->plan();
        if ($request->get('format') === 'excel') {
            $rows = [['Loan #', 'Member', 'Member #', 'Installment', 'Due', 'Paid off', 'Penalty']];
            foreach ($plan as $r) {
                $rows[] = [$r->loan->loan_number, $r->loan->client->name ?? '', $r->loan->client->client_number ?? '', $r->schedule->installment_no, $r->due, $r->completed ?? 'still unpaid', $r->penalty];
            }
            $rows[] = ['', '', '', '', '', 'TOTAL', $plan->sum('penalty')];
            return $this->csvDownload($rows, 'penalty-backfill-preview-' . now()->toDateString());
        }
        $applied = $this->backfill->appliedCount();
        return view('loans.penalty-backfill', compact('plan', 'applied'));
    }

    public function apply()
    {
        $r = $this->backfill->apply();
        return redirect()->route('loan-reports.penalties', ['from_date' => '2026-07-31'])
            ->with('success', "Charged {$r['count']} penalties on {$r['loans']} loans — " . number_format($r['total'], 2) . '.');
    }

    public function undo(Request $request)
    {
        $request->validate(['confirm' => 'required|in:UNDO']);
        $n = $this->backfill->undo();
        return redirect()->route('loans.penalty-backfill')->with('success', "Removed {$n} back-filled penalties.");
    }
}
