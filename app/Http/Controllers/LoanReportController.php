<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanComment;
use App\Models\LoanProduct;
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

    public function recoveries(Request $request)
    {
        $filtered = Loan::with('client.relationshipManager', 'comments')
            ->where('status', 'active')
            ->when($request->search, fn($q) => $q->where('loan_number', 'like', "%{$request->search}%")
                ->orWhereHas('client', fn($q2) => $q2->where('name', 'like', "%{$request->search}%")));

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

        return view('loan-reports.recoveries', compact('loans', 'totalPrincipalBalance', 'totalInterestBalance', 'totalCount'));
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
