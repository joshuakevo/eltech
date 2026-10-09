<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Bring loans over from another system as Locked-Up Loans: CSV upload -> preview -> confirm.
 * Columns: client_number, principal, interest (required), name, loan_number (optional).
 * Each loan is created like the existing Locked-Up book (status defaulted, no schedule, rate 0)
 * dated the as-at date. The principal posts DR Locked-Up receivable (1104) / CR offset
 * (default 3004 Opening Balance Equity), module `loan_transfer`; reversing that journal removes
 * the loan (blocked once it has recoveries). Interest needs no journal — it is income when recovered.
 */
class LockedUpLoanImportController extends Controller
{
    public const MODULE = 'loan_transfer';

    public function __construct(protected AccountingService $accounting) {}

    private function product(): ?LoanProduct
    {
        return LoanProduct::where('name', 'Locked-Up Loans')->first();
    }

    public function form()
    {
        return view('loans.import-locked-up', [
            'preview' => session('locked_up_import'),
            'offsets' => Account::where('is_active', true)->whereIn('account_type', ['equity', 'asset', 'liability'])->orderBy('account_code')->get(['id', 'account_code', 'account_name']),
            'defaultOffset' => Account::where('account_code', '3004')->value('id'),
        ]);
    }

    public function template()
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['client_number', 'name', 'principal', 'interest', 'loan_number']);
            fputcsv($out, ['NK00999', 'Example Member', '5000000', '750000', '']);
            fclose($out);
        }, 'locked-up-loans-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function preview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
            'date' => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
            'offset_account_id' => 'required|exists:accounts,id',
        ]);
        $product = $this->product();
        if (!$product || !$product->receivable_account_id) {
            return back()->with('error', 'The "Locked-Up Loans" product (with a receivable account) was not found.');
        }

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = fgetcsv($handle);
        if (!$header) {
            return back()->with('error', 'The file is empty.');
        }
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);
        foreach (['client_number', 'principal', 'interest'] as $required) {
            if (!in_array($required, $header, true)) {
                return back()->with('error', "The file needs a \"{$required}\" column. Download the template to see the format.");
            }
        }
        $col = fn ($cells, $name) => ($i = array_search($name, $header, true)) === false ? '' : trim(str_replace("\xC2\xA0", ' ', (string) ($cells[$i] ?? '')));
        $money = fn ($v) => ($v = str_replace([',', ' '], '', $v)) === '' ? 0.0 : (is_numeric($v) ? round((float) $v, 2) : null);

        $clients = Client::get(['id', 'client_number', 'name'])->keyBy(fn ($c) => strtoupper(trim($c->client_number)));
        $taken   = Loan::withTrashed()->pluck('loan_number')->map(fn ($n) => strtoupper($n))->flip()->all();

        $rows = []; $seen = []; $line = 1;
        while (($cells = fgetcsv($handle)) !== false) {
            $line++;
            if (count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $code = strtoupper($col($cells, 'client_number'));
            $principal = $money($col($cells, 'principal'));
            $interest  = $money($col($cells, 'interest'));
            $issues = []; $skip = null;

            $client = $clients[$code] ?? null;
            if ($code === '') {
                $issues[] = 'Missing member code';
            } elseif (!$client) {
                $issues[] = "No member with code {$code}";
            } elseif (isset($seen[$code])) {
                $issues[] = "Member repeated in this file (line {$seen[$code]})";
            }
            $seen[$code] = $seen[$code] ?? $line;
            if ($principal === null || $interest === null) {
                $issues[] = 'Principal / interest is not a number';
            } elseif ($principal < 0 || $interest < 0) {
                $issues[] = 'Principal / interest cannot be negative';
            } elseif ($principal == 0 && $interest == 0) {
                $skip = 'Nothing owed — skipped';
            }

            $number = strtoupper($col($cells, 'loan_number')) ?: "LU-{$code}";
            if ($client && !$issues && !$skip) {
                $existing = Loan::where('client_id', $client->id)->where('loan_product_id', $this->product()->id)
                    ->whereDate('disbursement_date', $request->date)->first();
                if ($existing) {
                    $skip = "Already transferred as {$existing->loan_number} — skipped";
                } elseif ($col($cells, 'loan_number') !== '' && isset($taken[$number])) {
                    $issues[] = "Loan number {$number} already exists";
                } else {
                    $base = $number; $n = 2;
                    while (isset($taken[$number])) {
                        $number = "{$base}-{$n}"; $n++;
                    }
                    $taken[$number] = true;
                }
            }

            $rows[] = [
                'line' => $line, 'client_number' => $code, 'file_name' => $col($cells, 'name'),
                'client_id' => $client->id ?? null, 'client_name' => $client->name ?? null,
                'principal' => $principal ?? 0, 'interest' => $interest ?? 0, 'loan_number' => $number,
                'issues' => $issues, 'skip' => $skip,
            ];
        }
        fclose($handle);

        $offset = Account::find($request->offset_account_id);
        session(['locked_up_import' => [
            'file' => $request->file('file')->getClientOriginalName(), 'rows' => $rows, 'date' => $request->date,
            'offset_account_id' => $offset->id, 'offset' => "{$offset->account_code} {$offset->account_name}",
        ]]);
        return redirect()->route('loans.import-locked-up');
    }

    public function confirm()
    {
        $p = session('locked_up_import');
        if (!$p) {
            return redirect()->route('loans.import-locked-up')->with('error', 'Nothing to import — upload a file first.');
        }
        $product = $this->product();

        $created = 0; $principal = 0.0; $interest = 0.0;
        DB::transaction(function () use ($p, $product, &$created, &$principal, &$interest) {
            foreach ($p['rows'] as $r) {
                if ($r['issues'] || $r['skip'] || Loan::withTrashed()->where('loan_number', $r['loan_number'])->exists()) {
                    continue;
                }
                $loan = Loan::create([
                    'loan_number' => $r['loan_number'], 'client_id' => $r['client_id'], 'loan_product_id' => $product->id,
                    'principal' => $r['principal'], 'interest_rate' => 0, 'interest_method' => 'flat', 'repayment_frequency' => 'monthly',
                    'term_months' => 0, 'disbursement_date' => $p['date'],
                    'outstanding_principal' => $r['principal'], 'outstanding_interest' => $r['interest'], 'outstanding_penalty' => 0,
                    'status' => 'defaulted', 'created_by' => auth()->id(),
                    'notes' => "Transferred from the previous system as at {$p['date']} ({$p['file']})",
                ]);
                if ($r['principal'] > 0) {
                    $desc = "Locked-Up loan transfer - {$loan->loan_number}";
                    $this->accounting->post($p['date'], $desc, [
                        ['account_id' => $product->receivable_account_id, 'debit' => $r['principal'], 'credit' => 0, 'client_id' => $r['client_id'], 'description' => $desc],
                        ['account_id' => $p['offset_account_id'], 'debit' => 0, 'credit' => $r['principal'], 'description' => $desc],
                    ], self::MODULE, $loan->id);
                }
                $created++; $principal += $r['principal']; $interest += $r['interest'];
            }
            AuditLog::record('locked_up_loans_imported', "Transferred {$created} Locked-Up loan(s) as at {$p['date']} from {$p['file']} (principal " . number_format($principal, 2) . ', interest ' . number_format($interest, 2) . ')', 'loans');
        });

        session()->forget('locked_up_import');
        return redirect()->route('loans.import-locked-up')->with('success', "Transferred {$created} Locked-Up loan(s): principal " . number_format($principal, 0) . ', interest ' . number_format($interest, 0) . '.');
    }

    public function cancel()
    {
        session()->forget('locked_up_import');
        return redirect()->route('loans.import-locked-up');
    }
}
