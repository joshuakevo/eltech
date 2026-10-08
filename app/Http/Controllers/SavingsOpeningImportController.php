<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\SavingsTransaction;
use App\Services\AccountingService;
use App\Services\SavingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Bulk savings opening balances from CSV (balances brought over from another system):
 * upload -> preview -> confirm. Columns: client_number, balance (required), name (optional, shown
 * for checking). For each member the chosen product's account is opened (or the member's existing
 * account in that product is used) and one journal is posted on the chosen date:
 *   positive balance  DR offset (3004 Opening Balance Equity) / CR product savings liability
 *   negative balance  DR product savings liability / CR offset   (account starts overdrawn)
 * The savings_transactions row carries transaction_id, so reversing the journal unwinds the statement.
 */
class SavingsOpeningImportController extends Controller
{
    public const DESCRIPTION = 'Opening balance';

    public function __construct(protected SavingsService $savings, protected AccountingService $accounting) {}

    public function form()
    {
        return view('savings.import-opening', [
            'preview'  => session('savings_opening_import'),
            'products' => SavingsProduct::orderBy('name')->get(['id', 'name']),
            'offsets'  => Account::where('is_active', true)->whereIn('account_type', ['equity', 'asset', 'liability'])->orderBy('account_code')->get(['id', 'account_code', 'account_name']),
            'defaultOffset' => Account::where('account_code', '3004')->value('id'),
        ]);
    }

    public function template()
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['client_number', 'name', 'balance']);
            fputcsv($out, ['NK00999', 'Example Member', '1500000']);
            fputcsv($out, ['NK00998', 'Overdrawn Member', '-40000']);
            fclose($out);
        }, 'savings-opening-balances-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function preview(Request $request)
    {
        $request->validate([
            'file'       => 'required|file|mimes:csv,txt|max:5120',
            'product_id' => 'required|exists:savings_products,id',
            'date'       => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
            'offset_account_id' => 'required|exists:accounts,id',
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = fgetcsv($handle);
        if (!$header) {
            return back()->with('error', 'The file is empty.');
        }
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);
        foreach (['client_number', 'balance'] as $required) {
            if (!in_array($required, $header, true)) {
                return back()->with('error', "The file needs a \"{$required}\" column. Download the template to see the format.");
            }
        }
        $col = fn ($cells, $name) => ($i = array_search($name, $header, true)) === false ? '' : trim(str_replace("\xC2\xA0", ' ', (string) ($cells[$i] ?? '')));

        $product = SavingsProduct::findOrFail($request->product_id);
        $clients = Client::get(['id', 'client_number', 'name'])->keyBy(fn ($c) => strtoupper(trim($c->client_number)));

        $rows = []; $seen = []; $line = 1;
        while (($cells = fgetcsv($handle)) !== false) {
            $line++;
            if (count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $code = strtoupper($col($cells, 'client_number'));
            $raw  = str_replace([',', ' '], '', $col($cells, 'balance'));
            $issues = []; $skip = null; $note = null;

            $client = $clients[$code] ?? null;
            if ($code === '') {
                $issues[] = 'Missing member code';
            } elseif (!$client) {
                $issues[] = "No member with code {$code}";
            } elseif (isset($seen[$code])) {
                $issues[] = "Member repeated in this file (line {$seen[$code]})";
            }
            $seen[$code] = $seen[$code] ?? $line;

            if (!is_numeric($raw)) {
                $issues[] = 'Balance is not a number';
            }
            $balance = is_numeric($raw) ? round((float) $raw, 2) : 0;
            if (is_numeric($raw) && $balance == 0) {
                $skip = 'Zero balance — nothing to post';
            }

            $accountId = null; $accountNumber = null;
            if ($client && !$issues) {
                $existing = SavingsAccount::where('client_id', $client->id)->where('product_id', $product->id)->where('status', 'active')->orderBy('id')->first();
                if ($existing) {
                    $accountId = $existing->id; $accountNumber = $existing->account_number;
                    if (SavingsTransaction::where('savings_account_id', $existing->id)->where('description', self::DESCRIPTION)->exists()) {
                        $skip = "{$existing->account_number} already has an opening balance — skipped";
                    } else {
                        $note = "Into existing account {$existing->account_number} (balance " . number_format($existing->balance, 0) . ')';
                    }
                }
            }

            $rows[] = [
                'line' => $line, 'client_number' => $code, 'file_name' => $col($cells, 'name'),
                'client_id' => $client->id ?? null, 'client_name' => $client->name ?? null,
                'balance' => $balance, 'account_id' => $accountId, 'account_number' => $accountNumber,
                'note' => $note, 'issues' => $issues, 'skip' => $skip,
            ];
        }
        fclose($handle);

        session(['savings_opening_import' => [
            'file' => $request->file('file')->getClientOriginalName(), 'rows' => $rows,
            'product_id' => $product->id, 'product' => $product->name, 'date' => $request->date,
            'offset_account_id' => (int) $request->offset_account_id,
            'offset' => ($o = Account::find($request->offset_account_id)) ? "{$o->account_code} {$o->account_name}" : '',
        ]]);
        return redirect()->route('savings.import-opening');
    }

    public function confirm()
    {
        $p = session('savings_opening_import');
        if (!$p) {
            return redirect()->route('savings.import-opening')->with('error', 'Nothing to import — upload a file first.');
        }
        $product = SavingsProduct::findOrFail($p['product_id']);
        $offsetId = $p['offset_account_id'];

        $posted = 0; $total = 0.0;
        DB::transaction(function () use ($p, $product, $offsetId, &$posted, &$total) {
            foreach ($p['rows'] as $r) {
                if ($r['issues'] || $r['skip']) {
                    continue;
                }
                $account = $r['account_id']
                    ? SavingsAccount::findOrFail($r['account_id'])
                    : $this->savings->openAccount(['client_id' => $r['client_id'], 'product_id' => $product->id, 'opened_date' => $p['date']]);
                if (SavingsTransaction::where('savings_account_id', $account->id)->where('description', self::DESCRIPTION)->exists()) {
                    continue;   // already imported (e.g. confirm pressed twice)
                }

                $amount = abs($r['balance']);
                $credit = $r['balance'] > 0;   // positive = we owe the member
                $journal = $this->accounting->post($p['date'], self::DESCRIPTION . " - {$account->account_number}", [
                    ['account_id' => $credit ? $offsetId : $product->savings_liability_account_id, 'debit' => $amount, 'credit' => 0,
                     'client_id' => $credit ? null : $r['client_id'], 'description' => self::DESCRIPTION . " - {$account->account_number}"],
                    ['account_id' => $credit ? $product->savings_liability_account_id : $offsetId, 'debit' => 0, 'credit' => $amount,
                     'client_id' => $credit ? $r['client_id'] : null, 'description' => self::DESCRIPTION . " - {$account->account_number}"],
                ], 'savings', $account->id);

                $before = $this->savings->balanceAsOf($account, $p['date']);
                SavingsTransaction::create([
                    'savings_account_id' => $account->id,
                    'transaction_type'   => $credit ? 'deposit' : 'withdrawal',
                    'amount'             => $amount,
                    'balance_before'     => $before,
                    'balance_after'      => $before + $r['balance'],
                    'transaction_date'   => $p['date'],
                    'reference'          => $journal->reference,
                    'description'        => self::DESCRIPTION,
                    'transaction_id'     => $journal->id,
                    'created_by'         => auth()->id(),
                ]);
                $this->savings->recalculateLedger($account);
                $posted++; $total += $r['balance'];
            }
            AuditLog::record('savings_opening_imported', "Posted {$posted} {$product->name} opening balance(s) as at {$p['date']} from {$p['file']} (net " . number_format($total, 2) . ')', 'savings');
        });

        session()->forget('savings_opening_import');
        return redirect()->route('savings.import-opening')->with('success', "Posted {$posted} opening balance(s) to {$product->name}, net " . number_format($total, 0) . '.');
    }

    public function cancel()
    {
        session()->forget('savings_opening_import');
        return redirect()->route('savings.import-opening');
    }
}
