<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Penalties owed at the 31/07/2026 transfer, brought over from the old system: CSV upload -> preview -> confirm.
 * Columns: loan_number and/or client_number, penalty (optional: name). Off the books — no journal: the amount is
 * set on the loan (penalty_brought_forward), collected before newer penalties and credited to penalty income (4004)
 * when paid. Loans already imported are skipped (safe to re-run).
 */
class PenaltyImportController extends Controller
{
    private const SESSION = 'penalty_import';

    public function form()
    {
        return view('loans.import-penalties', ['preview' => session(self::SESSION)]);
    }

    public function template()
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['loan_number', 'client_number', 'name', 'penalty']);
            fputcsv($out, ['LN-NK00999', 'NK00999', 'Example Member', '60000']);
            fclose($out);
        }, 'penalties-31-jul-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function preview(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:5120']);

        $fh = fopen($request->file('file')->getRealPath(), 'r');
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), fgetcsv($fh) ?: []);
        if (!in_array('penalty', $header) || (!in_array('loan_number', $header) && !in_array('client_number', $header))) {
            return back()->with('error', 'The file needs a "penalty" column and a "loan_number" or "client_number" column.');
        }

        $rows = [];
        $line = 1;
        while (($data = fgetcsv($fh)) !== false) {
            $line++;
            if (!array_filter($data, fn ($v) => trim((string) $v) !== '')) {
                continue;
            }
            $r = array_combine($header, array_pad(array_slice($data, 0, count($header)), count($header), ''));
            $loanNo   = trim($r['loan_number'] ?? '');
            $clientNo = trim($r['client_number'] ?? '');
            $amount   = (float) str_replace([',', ' '], '', (string) ($r['penalty'] ?? ''));
            $issue = null;
            $loan  = null;
            $action = 'running';
            $createFor = null;

            if ($loanNo !== '') {
                $loan = Loan::with('client')->where('loan_number', $loanNo)->first();
                $issue = $loan ? null : "No loan {$loanNo}";
            } else {
                $client = Client::where('client_number', $clientNo)->first();
                if (!$client) {
                    $issue = "No member {$clientNo}";
                } else {
                    $running = Loan::with('client')->where('client_id', $client->id)->whereIn('status', ['active', 'defaulted'])->get();
                    $issue = $running->count() > 1 ? "{$clientNo} has {$running->count()} running loans — give the loan_number" : null;
                    $loan = $running->count() === 1 ? $running->first() : null;
                    if ($running->isEmpty()) {
                        // Loan closed: on their latest closed loan, or a closed penalty-only record if no loan was transferred
                        $loan = Loan::with('client')->where('client_id', $client->id)->where('status', 'closed')->orderByDesc('disbursement_date')->orderByDesc('id')->first();
                        $action = $loan ? 'closed' : 'create';
                        $createFor = $loan ? null : $client;
                    }
                }
            }
            if (!$issue && $amount <= 0) {
                $issue = 'Penalty must be more than 0';
            }
            if (!$issue && $action === 'create' && Loan::withTrashed()->where('loan_number', 'PEN-' . strtoupper($createFor->client_number))->exists()) {
                $issue = 'Already imported (penalty record PEN-' . strtoupper($createFor->client_number) . ') — skipped';
            }
            if (!$issue && $loan && $loan->penalty_bf_imported !== null) {
                $issue = 'Already imported (' . number_format((float) $loan->penalty_bf_imported, 2) . ') — skipped';
            }

            $rows[] = [
                'line' => $line, 'loan_id' => $loan?->id, 'action' => $action, 'client_id' => $createFor?->id ?? $loan?->client_id,
                'loan_number' => $loan->loan_number ?? ($action === 'create' && $createFor ? 'PEN-' . strtoupper($createFor->client_number) . ' (new)' : $loanNo),
                'client' => $loan->client->name ?? $createFor->name ?? ($r['name'] ?? ''), 'client_number' => $loan->client->client_number ?? $createFor->client_number ?? $clientNo,
                'status' => $loan->status ?? ($action === 'create' ? 'closed (penalty only)' : ''), 'amount' => round($amount, 2), 'issue' => $issue,
            ];
        }
        fclose($fh);

        session([self::SESSION => ['rows' => $rows, 'file' => $request->file('file')->getClientOriginalName()]]);
        return redirect()->route('loans.import-penalties');
    }

    public function confirm()
    {
        $preview = session(self::SESSION);
        if (!$preview) {
            return redirect()->route('loans.import-penalties');
        }
        $ok = collect($preview['rows'])->whereNull('issue');

        $count = 0;
        $total = 0.0;
        DB::transaction(function () use ($ok, &$count, &$total) {
            $product = \App\Models\LoanProduct::where('name', 'Locked-Up Loans')->first();
            foreach ($ok as $r) {
                if (($r['action'] ?? '') === 'create') {
                    // No loan was transferred (closed in the old system): a closed penalty-only record, no principal, no journal
                    $client = Client::find($r['client_id']);
                    $number = 'PEN-' . strtoupper($client->client_number);
                    if (!$client || !$product || Loan::withTrashed()->where('loan_number', $number)->exists()) {
                        continue;
                    }
                    Loan::create([
                        'loan_number' => $number, 'client_id' => $client->id, 'loan_product_id' => $product->id,
                        'principal' => 0, 'interest_rate' => 0, 'interest_method' => 'flat', 'repayment_frequency' => 'monthly',
                        'term_months' => 0, 'disbursement_date' => '2026-07-31',
                        'outstanding_principal' => 0, 'outstanding_interest' => 0, 'outstanding_penalty' => 0,
                        'penalty_brought_forward' => $r['amount'], 'penalty_bf_imported' => $r['amount'],
                        'status' => 'closed', 'created_by' => auth()->id(),
                        'notes' => 'Loan closed in the old system before the 31/07/2026 transfer — penalty still owed, brought forward.',
                    ]);
                    $count++;
                    $total += $r['amount'];
                    continue;
                }
                $loan = Loan::lockForUpdate()->find($r['loan_id']);
                if (!$loan || $loan->penalty_bf_imported !== null) {
                    continue;
                }
                $loan->update(['penalty_brought_forward' => $r['amount'], 'penalty_bf_imported' => $r['amount']]);
                $count++;
                $total += $r['amount'];
            }
        });
        session()->forget(self::SESSION);
        AuditLog::record('penalty_import', "Imported penalties owed at 31/07/2026 on {$count} loan(s), total " . number_format($total, 2) . ' (no journal — penalty income when paid)', 'loans');

        return redirect()->route('loan-reports.penalties', ['from_date' => '2026-07-31'])
            ->with('success', "Penalties brought forward on {$count} loan(s) — " . number_format($total, 2) . '.');
    }

    /** Add / edit one loan's penalty owed at 31/07/2026. Still owed = the new figure minus what has already been paid. */
    public function setForLoan(Request $request, Loan $loan)
    {
        $data = $request->validate(['amount' => 'required|numeric|min:0', 'note' => 'nullable|string|max:200']);
        $amount = round((float) $data['amount'], 2);
        $paid   = round((float) $loan->repayments()->reorder()->sum('penalty_bf_paid'), 2);
        if ($amount + 0.005 < $paid) {
            return back()->with('error', 'The member has already paid ' . number_format($paid, 2) . ' of this penalty — it can\'t be set lower than that.');
        }
        $before = $loan->penalty_bf_imported;
        $loan->update([
            'penalty_bf_imported'     => ($amount > 0 || $paid > 0) ? $amount : null,
            'penalty_brought_forward' => round(max(0, $amount - $paid), 2),
        ]);
        AuditLog::record('penalty_brought_forward', "{$loan->loan_number}: penalty at 31/07/2026 " . ($before === null ? 'set to ' : 'changed from ' . number_format((float) $before, 2) . ' to ')
            . number_format($amount, 2) . (!empty($data['note']) ? " — {$data['note']}" : ''), 'loans');

        return back()->with('success', 'Penalty brought forward saved: ' . number_format($amount, 2) . ' (still owed ' . number_format(max(0, $amount - $paid), 2) . ').');
    }

    public function cancel()
    {
        session()->forget(self::SESSION);
        return redirect()->route('loans.import-penalties');
    }
}
