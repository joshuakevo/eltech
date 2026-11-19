<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Loan;
use App\Services\LoanProvisionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Old system's provision report → new system. Upload the report (CSV: member_id, loan_bal, arrears, days; optional
 * loan_number) for its as-at date: the preview compares every member with the new system (balance, days in arrears,
 * specific provision) and lists loans only one side has. Confirm saves each loan's old arrears start date
 * (as-at − days) and arrears amount (loans.arrears_bf_*), which the specific provision then counts from.
 */
class ProvisionArrearsImportController extends Controller
{
    public function __construct(protected LoanProvisionService $provisions) {}

    public function form()
    {
        $imported = Loan::with('client')->whereNotNull('arrears_bf_since')->orderBy('arrears_bf_since')->get();

        return view('loan-provisions.import-arrears', ['imported' => $imported, 'preview' => session('provision_arrears_preview')]);
    }

    public function template()
    {
        return response("member_id,loan_bal,arrears,days\nEK00003,6406945,6406945,307\n", 200, [
            'Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="old-provision-report.csv"',
        ]);
    }

    public function preview(Request $request)
    {
        $request->validate(['file' => 'required|file|max:2048', 'as_at_date' => 'required|date|before_or_equal:today']);
        $asAt = Carbon::parse($request->as_at_date)->toDateString();

        $path = $request->file('file')->getRealPath();
        $isPdf = str_starts_with((string) file_get_contents($path, false, null, 0, 5), '%PDF');
        $reportDate = null;
        $rows = $isPdf ? $this->parsePdf($path, $reportDate) : $this->parse($path);
        if (!$rows) {
            return back()->with('error', $isPdf
                ? 'No member rows could be read from this PDF. Upload the old system provision report PDF, or a CSV (member_id, loan_bal, arrears, days).'
                : 'No rows found. The file needs the columns member_id, loan_bal, arrears, days.');
        }
        if ($reportDate && $reportDate !== $asAt) {
            return back()->with('error', 'This report is dated ' . Carbon::parse($reportDate)->format('d/m/Y') . ' but you chose ' . Carbon::parse($asAt)->format('d/m/Y') . '. Choose the date of the report.');
        }

        $newLoans = $this->provisions->outstandingAsAt($asAt);
        $newLoans->load('schedules');
        $byClient = $newLoans->groupBy('client_id');
        $usedLoanIds = [];
        $lines = [];

        foreach ($rows as $r) {
            $client = Client::whereRaw('UPPER(client_number) = ?', [strtoupper($r['member_id'])])->first();
            $loan = null;
            if (!empty($r['loan_number'])) {
                $loan = $newLoans->first(fn ($l) => strcasecmp($l->loan_number, $r['loan_number']) === 0);
            } elseif ($client) {
                $loan = ($byClient[$client->id] ?? collect())->sortByDesc('outstanding_as_at')->first();
            }
            $note = null;
            if (!$client) {
                $note = 'Member not found in the new system';
            } elseif (!$loan) {
                $lu = Loan::where('client_id', $client->id)->whereHas('product', fn ($q) => $q->where('name', 'Locked-Up Loans'))->whereIn('status', ['defaulted', 'written_off'])->first();
                $later = Loan::where('client_id', $client->id)->whereDate('disbursement_date', '>', $asAt)->orderBy('disbursement_date')->first();
                $note = $lu ? "Locked-Up in the new system ({$lu->loan_number}, principal " . number_format($lu->outstanding_principal) . ') — not in provisions'
                    : ($later ? "Loan {$later->loan_number} disbursed " . $later->disbursement_date->format('d/m/Y') . ' — after the as-at date'
                    : 'No running loan in the new system on this date');
            }

            $newDays = $newBal = $newWithBf = null;
            if ($loan) {
                $usedLoanIds[] = (int) $loan->id;
                $bare = clone $loan;
                $bare->arrears_bf_since = null;
                $d = $this->provisions->oldestArrearsDate($bare, $asAt);
                $newDays = $d ? (int) $d->diffInDays(Carbon::parse($asAt)) : 0;
                $newBal = round((float) $loan->outstanding_as_at, 2);
            }
            $since = $r['days'] > 0 && $r['arrears'] > 0 ? Carbon::parse($asAt)->subDays($r['days'])->toDateString() : null;

            $lines[] = $r + [
                'client' => $client?->name, 'loan_id' => $loan?->id, 'loan_number' => $loan?->loan_number,
                'new_bal' => $newBal, 'new_days' => $newDays, 'since' => $since, 'note' => $note,
                'old_specific' => round($r['loan_bal'] * $this->rateFor($r['days']) / 100, 2),
                'new_specific' => $newBal !== null ? round($newBal * $this->rateFor(max($newDays, $since ? $r['days'] : 0)) / 100, 2) : null,
            ];
        }

        $onlyNew = $newLoans->reject(fn ($l) => in_array((int) $l->id, $usedLoanIds, true))->map(function ($l) use ($asAt) {
            $d = $this->provisions->oldestArrearsDate($l, $asAt);
            return ['loan_number' => $l->loan_number, 'client_number' => $l->client->client_number ?? '', 'client' => $l->client->name ?? '',
                'bal' => round((float) $l->outstanding_as_at, 2), 'days' => $d ? (int) $d->diffInDays(Carbon::parse($asAt)) : 0,
                'disbursed' => $l->disbursement_date?->format('d/m/Y')];
        })->values()->all();

        session(['provision_arrears_preview' => ['as_at' => $asAt, 'lines' => $lines, 'only_new' => $onlyNew,
            'new_total' => round($newLoans->sum('outstanding_as_at'), 2)]]);

        return redirect()->route('loan-provisions.import-arrears');
    }

    public function confirm(Request $request)
    {
        $p = session('provision_arrears_preview');
        abort_unless($p, 404);
        $saved = 0;
        $run = null;
        try {
        DB::transaction(function () use ($p, &$saved, &$run, $request) {
            foreach ($p['lines'] as $l) {
                if (!$l['loan_id'] || !$l['since']) continue;
                Loan::whereKey($l['loan_id'])->update([
                    'arrears_bf_since' => $l['since'], 'arrears_bf_amount' => $l['arrears'], 'arrears_bf_as_at' => $p['as_at'],
                ]);
                $saved++;
            }
            // Optionally: the specific provision at the report date = the old report's figures, as they are
            if ($request->boolean('set_provision')) {
                $run = $this->provisions->setSpecificFromReport($p['as_at'], $p['lines']);
            }
        });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }
        session()->forget('provision_arrears_preview');
        if ($run) {
            AuditLog::record('provision_arrears_imported', "Old-system arrears imported for {$saved} loan(s) as at {$p['as_at']}", 'loans');
            return redirect()->route('loan-provisions.show', $run)->with('success', "Old arrears saved on {$saved} loan(s). Specific provision as at "
                . Carbon::parse($p['as_at'])->format('d/m/Y') . ' recorded from the old report: ' . number_format($run->required_provision)
                . ' (no journal — the old system already posted it; it is in the 1109 opening balance). Later runs post the movement from it.');
        }
        AuditLog::record('provision_arrears_imported', "Old-system arrears imported for {$saved} loan(s) as at {$p['as_at']} (specific provision counts days from the old arrears start)", 'loans');

        return redirect()->route('loan-provisions.import-arrears')->with('success', "Old arrears saved on {$saved} loan(s). The specific provision now counts their days from the old system's arrears start.");
    }

    public function removeBaseline(\App\Models\LoanProvision $loanProvision)
    {
        try {
            $this->provisions->removeReportBaseline($loanProvision);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }
        return redirect()->route('loan-provisions.index', ['type' => 'specific'])->with('success', 'Old-report starting figure removed.');
    }

    public function cancel()
    {
        session()->forget('provision_arrears_preview');
        return redirect()->route('loan-provisions.import-arrears');
    }

    public function clear()
    {
        $n = Loan::whereNotNull('arrears_bf_since')->update(['arrears_bf_since' => null, 'arrears_bf_amount' => null, 'arrears_bf_as_at' => null]);
        AuditLog::record('provision_arrears_cleared', "Cleared old-system arrears on {$n} loan(s)", 'loans');
        return back()->with('success', "Old arrears cleared on {$n} loan(s).");
    }

    private function rateFor(int $days): float
    {
        $band = collect(LoanProvisionService::SPECIFIC_BANDS)->first(fn ($b) => $days >= $b[0]);
        return $band ? (float) $band[1] : 0.0;
    }

    /**
     * The old system's provision report PDF (Access "ProvRptQ"): rows "MemberID LoanBal GenProvision LnID Arrears TtlDays [SpecificProv]";
     * the "Provion date dd/mm/yyyy" line gives the report date.
     */
    private function parsePdf(string $path, ?string &$reportDate): array
    {
        $num = fn ($v) => (float) str_replace([',', ' '], '', (string) $v);
        $rows = [];
        foreach (\App\Support\PdfTextRows::rows((string) file_get_contents($path)) as $cells) {
            $line = implode(' ', $cells);
            if (!$reportDate && preg_match('/date\s+(\d{1,2})\/(\d{1,2})\/(\d{4})/i', $line, $d)) {
                $reportDate = sprintf('%04d-%02d-%02d', $d[3], $d[2], $d[1]);
            }
            if (count($cells) < 6 || !preg_match('/^[A-Za-z]{1,4}\d{3,}$/', $cells[0])) {
                continue;
            }
            $vals = array_slice($cells, 1);
            if (count(array_filter($vals, fn ($v) => !preg_match('/^-?[\d,.]+$/', $v)))) {
                continue;
            }
            // LoanBal, GenProvision, LnID, Arrears, TtlDays[, SpecificProv]
            $rows[] = ['member_id' => strtoupper($cells[0]), 'loan_number' => '', 'loan_bal' => $num($vals[0]),
                'arrears' => $num($vals[3]), 'days' => (int) $num($vals[4])];
        }
        return $rows;
    }

    /** CSV with a header row; accepts MemberID / member_id / client_number, LoanBal, Arrears, TtlDays / days, LnID ignored. */
    private function parse(string $path): array
    {
        $fh = fopen($path, 'r');
        $header = null;
        $rows = [];
        $num = fn ($v) => (float) str_replace([',', ' '], '', (string) $v);
        while (($cols = fgetcsv($fh)) !== false) {
            if ($header === null) {
                $header = array_map(fn ($h) => strtolower(preg_replace('/[^a-z_]/i', '', (string) $h)), $cols);
                continue;
            }
            $row = array_combine(array_slice($header, 0, count($cols)), array_slice($cols, 0, count($header)));
            $member = trim($row['member_id'] ?? $row['memberid'] ?? $row['client_number'] ?? '');
            if ($member === '') continue;
            $rows[] = [
                'member_id' => strtoupper($member),
                'loan_number' => trim($row['loan_number'] ?? ''),
                'loan_bal' => $num($row['loan_bal'] ?? $row['loanbal'] ?? 0),
                'arrears' => $num($row['arrears'] ?? 0),
                'days' => (int) $num($row['days'] ?? $row['ttldays'] ?? 0),
            ];
        }
        fclose($fh);
        return $rows;
    }
}
