<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ClientSegment;
use App\Models\TransactionLine;
use App\Services\AccountingService;
use Illuminate\Http\Request;

/**
 * Segment attribution of journal lines. A line belongs to a segment through its member (line client or the
 * loan / savings / deposit it posts to), else the segment picked on the line, else its account's default segment
 * (AccountingService::segmentsForLines). Segment Check lists income & expense lines that end up in no segment, so they
 * can be tagged in bulk; the same action tags lines from a journal's own page.
 */
class SegmentCheckController extends Controller
{
    public function index(Request $request, AccountingService $accounting)
    {
        $from = $request->from_date ?: now()->startOfYear()->toDateString();
        $to   = $request->to_date ?: now()->toDateString();

        $lines = TransactionLine::with('account:id,account_code,account_name,account_type')
            ->join('transactions', 'transaction_lines.transaction_id', '=', 'transactions.id')
            ->join('accounts', 'transaction_lines.account_id', '=', 'accounts.id')
            ->whereIn('accounts.account_type', ['revenue', 'expense'])
            ->whereBetween('transactions.date', [$from, $to])
            ->whereNull('transactions.reversed_by')->whereNull('transactions.reversal_of')
            ->select('transaction_lines.*', 'transactions.date', 'transactions.reference', 'transactions.description as tx_description',
                'transactions.module', 'transactions.module_id')
            ->orderBy('transactions.date')->orderBy('transactions.id')
            ->get()->values();

        $segments = $accounting->segmentsForLines($lines);
        $unassigned = $lines->filter(fn ($l, $i) => $segments[$i] === null)->values();

        // A member was found but has no segment: fix on the member, not the line
        $memberNoSegment = $unassigned->filter(fn ($l) => $l->client_id)->count();
        $byAccount = $unassigned->groupBy('account_id')->map(fn ($g) => [
            'account' => $g->first()->account, 'lines' => $g->count(), 'amount' => round($g->sum(fn ($l) => (float) $l->debit + (float) $l->credit), 2),
        ])->sortByDesc('amount')->values();

        $allSegments = ClientSegment::orderBy('name')->get();
        return view('accounts.segment-check', compact('from', 'to', 'unassigned', 'byAccount', 'allSegments', 'memberNoSegment', 'lines'));
    }

    /** Tag lines with a segment (lines tied to a member keep the member's segment). Blank segment removes the tag. */
    public function assign(Request $request)
    {
        $data = $request->validate([
            'line_ids'   => 'required|array|min:1',
            'line_ids.*' => 'integer',
            'segment_id' => 'nullable|exists:client_segments,id',
        ]);
        $lines = TransactionLine::with('transaction:id,reference')->whereIn('id', $data['line_ids'])->whereNull('client_id')->get();
        foreach ($lines as $line) {
            $line->update(['segment_id' => $data['segment_id'] ?: null]);
        }
        $name = $data['segment_id'] ? ClientSegment::find($data['segment_id'])->name : 'no segment';
        AuditLog::record('segment_tagged', "Tagged {$lines->count()} journal line(s) to {$name}: " . $lines->pluck('transaction.reference')->unique()->take(20)->implode(', '), 'transactions');

        return back()->with('success', "{$lines->count()} line(s) now count under {$name}.");
    }
}
