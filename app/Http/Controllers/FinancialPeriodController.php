<?php

namespace App\Http\Controllers;

use App\Models\FinancialPeriod;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FinancialPeriodController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) $request->get('year', now()->year);

        // Auto-create all 12 months for the requested year so the grid is always full
        for ($m = 1; $m <= 12; $m++) {
            FinancialPeriod::firstOrCreate(
                ['year' => $year, 'month' => $m],
                ['status' => 'open']
            );
        }

        $periods = FinancialPeriod::with(['closedBy', 'reopenedBy'])
            ->where('year', $year)
            ->orderBy('month')
            ->get();

        // Years available (from earliest period or current year - 3)
        $years = FinancialPeriod::selectRaw('DISTINCT year')->orderByDesc('year')->pluck('year');
        if (!$years->contains($year)) $years->prepend($year);

        $allClosed = $periods->every(fn($p) => $p->status === 'closed');

        return view('periods.index', compact('periods', 'year', 'years', 'allClosed'));
    }

    public function closeMonth(Request $request, FinancialPeriod $period)
    {
        abort_if($period->status === 'closed', 422, 'Period is already closed.');

        $request->validate(['notes' => 'nullable|string|max:500']);

        $period->update([
            'status'    => 'closed',
            'closed_by' => auth()->id(),
            'closed_at' => now(),
            'notes'     => $request->notes,
        ]);

        $label = Carbon::create($period->year, $period->month, 1)->format('F Y');
        return back()->with('success', "{$label} has been closed. No further postings are allowed in this period.");
    }

    public function reopenMonth(Request $request, FinancialPeriod $period)
    {
        abort_if($period->status === 'open', 422, 'Period is already open.');

        $request->validate(['notes' => 'nullable|string|max:500']);

        $period->update([
            'status'      => 'open',
            'reopened_by' => auth()->id(),
            'reopened_at' => now(),
            'notes'       => $request->notes,
        ]);

        $label = Carbon::create($period->year, $period->month, 1)->format('F Y');
        return back()->with('success', "{$label} has been reopened for postings.");
    }

    public function closeYear(Request $request, int $year)
    {
        $request->validate(['notes' => 'nullable|string|max:500']);

        $open = FinancialPeriod::where('year', $year)->where('status', 'open')->get();

        if ($open->isEmpty()) {
            return back()->with('error', "All periods in {$year} are already closed.");
        }

        foreach ($open as $period) {
            $period->update([
                'status'    => 'closed',
                'closed_by' => auth()->id(),
                'closed_at' => now(),
                'notes'     => $request->notes ?? "Closed as part of year-end close for {$year}.",
            ]);
        }

        return back()->with('success', "Financial year {$year} has been closed ({$open->count()} period(s) closed).");
    }

    public function reopenYear(Request $request, int $year)
    {
        $request->validate(['notes' => 'nullable|string|max:500']);

        $closed = FinancialPeriod::where('year', $year)->where('status', 'closed')->get();

        if ($closed->isEmpty()) {
            return back()->with('error', "All periods in {$year} are already open.");
        }

        foreach ($closed as $period) {
            $period->update([
                'status'      => 'open',
                'reopened_by' => auth()->id(),
                'reopened_at' => now(),
                'notes'       => $request->notes ?? "Reopened as part of year reopen for {$year}.",
            ]);
        }

        return back()->with('success', "Financial year {$year} has been reopened ({$closed->count()} period(s) opened).");
    }
}
