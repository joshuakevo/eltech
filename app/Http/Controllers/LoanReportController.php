<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanComment;
use App\Models\LoanProduct;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class LoanReportController extends Controller
{
    public function disbursements(Request $request)
    {
        // Excludes the special "Locked-Up Loans" product (0% rate, historical
        // debt import) -- same exclusion the main Loans list applies to its
        // "Normal Loans" tab, since these aren't real day-to-day disbursements.
        $lockedUpProductId = LoanProduct::where('name', 'Locked-Up Loans')->value('id');

        $filtered = Loan::with('client', 'product')
            ->whereNotNull('disbursement_date')
            ->when($lockedUpProductId, fn($q) => $q->where(fn($q2) => $q2
                ->where('loan_product_id', '!=', $lockedUpProductId)
                ->orWhereNull('loan_product_id')))
            ->when($request->from_date, fn($q) => $q->where('disbursement_date', '>=', $request->from_date))
            ->when($request->to_date, fn($q) => $q->where('disbursement_date', '<=', $request->to_date))
            ->when($request->search, fn($q) => $q->where('loan_number', 'like', "%{$request->search}%")
                ->orWhereHas('client', fn($q2) => $q2->where('name', 'like', "%{$request->search}%")))
            ->orderByDesc('disbursement_date');

        $totalPrincipal = (clone $filtered)->sum('principal');
        $totalCount     = (clone $filtered)->count();

        if ($request->format === 'pdf') {
            $loans = (clone $filtered)->get();
            $pdf = Pdf::loadView('pdf.loan-reports.disbursements', compact('loans', 'totalPrincipal', 'totalCount'))
                ->setPaper('a4', 'landscape');
            return $pdf->download('loan-disbursements-' . now()->format('Y-m-d') . '.pdf');
        }

        if ($request->format === 'excel') {
            $loans = (clone $filtered)->get();
            $rows = [['Loan #', 'Client', 'Client #', 'Principal', 'Interest Rate', 'Period (months)', 'Date Given']];
            foreach ($loans as $loan) {
                $rows[] = [
                    $loan->loan_number,
                    $loan->client->name ?? '',
                    $loan->client->client_number ?? '',
                    $loan->principal,
                    $loan->interest_rate . '%',
                    $loan->term_months,
                    $loan->disbursement_date?->format('Y-m-d') ?? '',
                ];
            }
            $rows[] = ['', '', 'TOTAL', $totalPrincipal, '', '', $totalCount . ' loans'];
            return $this->csvDownload($rows, 'loan-disbursements-' . now()->format('Y-m-d'));
        }

        $loans = $filtered->paginate(30)->withQueryString();

        return view('loan-reports.disbursements', compact('loans', 'totalPrincipal', 'totalCount'));
    }

    /**
     * Loan penalties for a date range, per loan: installments missed (due before the end date, unpaid), the penalty
     * incurred in the range (installments that fell due in it), penalty paid in the range (repayments) and the
     * penalty outstanding at the end date. Penalty = flat amount per missed installment (Loan Penalty Tiers).
     */
    public function penalties(Request $request, \App\Services\LoanService $loanService)
    {
        $from = $request->from_date ?: now()->startOfMonth()->toDateString();
        $to   = $request->to_date ?: now()->toDateString();
        $paidInRange = \App\Models\LoanRepayment::whereBetween('payment_date', [$from, $to])->where('penalty_paid', '>', 0)
            ->groupBy('loan_id')->selectRaw('loan_id, SUM(penalty_paid) as paid')->pluck('paid', 'loan_id');

        $loans = Loan::with('client.relationshipManager', 'client.segment', 'product')
            ->where(fn ($q) => $q->whereIn('status', ['active', 'defaulted'])->orWhereIn('id', $paidInRange->keys())->orWhere('penalty_brought_forward', '>', 0))
            ->when($request->search, fn ($q) => $q->where(fn ($q2) => $q2->where('loan_number', 'like', "%{$request->search}%")
                ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$request->search}%"))))
            ->when($request->rm_id, fn ($q) => $q->whereHas('client', fn ($c) => $c->where('relationship_manager_id', $request->rm_id)))
            ->when($request->segment_id, fn ($q) => $q->whereHas('client', fn ($c) => $c->where('segment_id', $request->segment_id)))
            ->get();

        $rows = collect();
        foreach ($loans as $loan) {
            $breakdown = $loanService->penaltyBreakdown($loan, $to);      // missed installments as at the end date
            $dueDates  = $breakdown ? $loan->schedules()->whereIn('id', array_keys($breakdown))->pluck('due_date', 'id') : collect();
            $incurred  = 0.0;
            $missedInRange = 0;
            foreach ($breakdown as $id => $amount) {
                $due = \Carbon\Carbon::parse($dueDates[$id])->toDateString();
                if ($due >= $from) {
                    $incurred += $amount;
                    $missedInRange++;
                }
            }
            $broughtForward = round(max(0, (float) $loan->penalty_brought_forward), 2);   // old system, owed at 31/07/2026
            $outstanding = round(array_sum($breakdown) + $broughtForward, 2);
            $paid = round((float) ($paidInRange[$loan->id] ?? 0), 2);
            if ($outstanding <= 0 && $paid <= 0) {
                continue;
            }
            $rows->push((object) [
                'loan' => $loan, 'missed' => count($breakdown), 'missed_in_range' => $missedInRange,
                'oldest_due' => $dueDates->min(), 'incurred' => round($incurred, 2), 'paid' => $paid, 'outstanding' => $outstanding,
                'brought_forward' => $broughtForward,
            ]);
        }
        $rows = $rows->sortByDesc('outstanding')->values();
        $totals = ['incurred' => $rows->sum('incurred'), 'paid' => $rows->sum('paid'), 'outstanding' => $rows->sum('outstanding'), 'missed' => $rows->sum('missed'), 'brought_forward' => $rows->sum('brought_forward')];

        if ($request->format === 'excel') {
            $out = [["Loan penalties {$from} to {$to}"], [], ['Loan #', 'Client', 'Client #', 'Segment', 'RM', 'Product', 'Missed installments', 'Missed in period', 'Oldest missed', 'Brought forward 31/07/2026 (unpaid)', 'Penalty incurred (period)', 'Penalty paid (period)', "Penalty outstanding at {$to}"]];
            foreach ($rows as $r) {
                $out[] = [$r->loan->loan_number, $r->loan->client->name ?? '', $r->loan->client->client_number ?? '', $r->loan->client->segment->name ?? '', $r->loan->client->relationshipManager->name ?? '',
                    $r->loan->product->name ?? '', $r->missed, $r->missed_in_range, $r->oldest_due ? \Carbon\Carbon::parse($r->oldest_due)->toDateString() : '', $r->brought_forward, $r->incurred, $r->paid, $r->outstanding];
            }
            $out[] = ['', '', 'TOTAL', '', '', '', $totals['missed'], '', '', $totals['brought_forward'], $totals['incurred'], $totals['paid'], $totals['outstanding']];
            return $this->csvDownload($out, "loan-penalties-{$from}-to-{$to}");
        }
        if ($request->format === 'pdf') {
            return Pdf::loadView('pdf.loan-reports.penalties', compact('rows', 'totals', 'from', 'to'))->setPaper('a4', 'landscape')
                ->download("loan-penalties-{$from}-to-{$to}.pdf");
        }

        $relationshipManagers = User::where('is_relationship_manager', true)->orderBy('name')->get();
        $segments = \App\Models\ClientSegment::orderBy('name')->get();
        return view('loan-reports.penalties', compact('rows', 'totals', 'from', 'to', 'relationshipManagers', 'segments'));
    }

    public function recoveries(Request $request)
    {
        $filtered = Loan::with('client.relationshipManager', 'comments')
            ->where('status', 'active')
            ->when($request->search, fn($q) => $q->where('loan_number', 'like', "%{$request->search}%")
                ->orWhereHas('client', fn($q2) => $q2->where('name', 'like', "%{$request->search}%")))
            ->when($request->rm_id, fn($q) => $q->whereHas('client', fn($q2) => $q2->where('relationship_manager_id', $request->rm_id)));

        $totalPrincipalBalance = (clone $filtered)->sum('outstanding_principal');
        $totalInterestBalance  = (clone $filtered)->sum('outstanding_interest');
        $totalCount            = (clone $filtered)->count();

        if ($request->format === 'pdf') {
            $loans = (clone $filtered)->get();
            $pdf = Pdf::loadView('pdf.loan-reports.recoveries', compact('loans', 'totalPrincipalBalance', 'totalInterestBalance', 'totalCount'))
                ->setPaper('a4', 'landscape');
            return $pdf->download('loan-recoveries-' . now()->format('Y-m-d') . '.pdf');
        }

        if ($request->format === 'excel') {
            $loans = (clone $filtered)->get();
            $rows = [['Loan #', 'Client', 'Client #', 'Principal Balance', 'Interest Balance', 'RM', 'Last Comment', 'Comment Date']];
            foreach ($loans as $loan) {
                $lastComment = $loan->comments->first();
                $rows[] = [
                    $loan->loan_number,
                    $loan->client->name ?? '',
                    $loan->client->client_number ?? '',
                    $loan->outstanding_principal,
                    $loan->outstanding_interest,
                    $loan->client->relationshipManager->name ?? '',
                    $lastComment->comment ?? '',
                    $lastComment?->created_at->format('Y-m-d H:i') ?? '',
                ];
            }
            $rows[] = ['', '', 'TOTAL', $totalPrincipalBalance, $totalInterestBalance, '', '', $totalCount . ' loans'];
            return $this->csvDownload($rows, 'loan-recoveries-' . now()->format('Y-m-d'));
        }

        $loans = $filtered->paginate(30)->withQueryString();

        $relationshipManagers = User::where('is_relationship_manager', true)->orderBy('name')->get();

        return view('loan-reports.recoveries', compact('loans', 'totalPrincipalBalance', 'totalInterestBalance', 'totalCount', 'relationshipManagers'));
    }

    public function addComment(Request $request, Loan $loan)
    {
        $request->validate([
            'comment' => 'required|string|max:1000',
        ]);

        LoanComment::create([
            'loan_id'    => $loan->id,
            'comment'    => $request->comment,
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', "Comment added for {$loan->loan_number}.");
    }
}
