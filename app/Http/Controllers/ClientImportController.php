<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientSegment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Bulk member import from CSV: upload -> preview (validated, matched) -> confirm.
 * Columns (header row, any order): client_number, name, first_name, last_name, segment,
 * relationship_manager, status, membership_fee, phone, email. Only client_number and name
 * are required. Existing member codes are skipped, never overwritten.
 */
class ClientImportController extends Controller
{
    private const COLUMNS = ['client_number', 'name', 'first_name', 'last_name', 'segment', 'relationship_manager', 'status', 'membership_fee', 'phone', 'email'];

    public function form()
    {
        return view('clients.import', ['preview' => session('client_import')]);
    }

    public function template()
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, self::COLUMNS);
            fputcsv($out, ['NK00999', 'Example Member', 'Example', 'Member', 'KDF', 'Deborah Avinyia', 'active', '50000', '', '']);
            fclose($out);
        }, 'member-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    /** Parse + validate the upload and keep the result in the session for confirmation. */
    public function preview(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:5120']);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = fgetcsv($handle);
        if (!$header) {
            return back()->with('error', 'The file is empty.');
        }
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);
        foreach (['client_number', 'name'] as $required) {
            if (!in_array($required, $header, true)) {
                return back()->with('error', "The file needs a \"{$required}\" column. Download the template to see the format.");
            }
        }

        $segments = ClientSegment::get(['id', 'name']);
        $users    = User::get(['id', 'name']);
        $existing = Client::withTrashed()->pluck('client_number')->map(fn ($c) => strtoupper(trim($c)))->flip();

        $rows = []; $seen = []; $line = 1;
        while (($cells = fgetcsv($handle)) !== false) {
            $line++;
            if (count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $r = [];
            foreach (self::COLUMNS as $col) {
                $i = array_search($col, $header, true);
                $r[$col] = $i === false ? '' : trim(preg_replace('/\s+/u', ' ', str_replace("\xC2\xA0", ' ', (string) ($cells[$i] ?? ''))));
            }

            $issues = []; $skip = null;
            $code = strtoupper($r['client_number']);
            if ($code === '' || $r['name'] === '') {
                $issues[] = 'Missing member code or name';
            } elseif (isset($existing[$code])) {
                $skip = 'Code already exists — skipped';
            } elseif (isset($seen[$code])) {
                $issues[] = "Code repeated in this file (line {$seen[$code]})";
            }
            $seen[$code] = $seen[$code] ?? $line;

            $segmentId = null;
            if ($r['segment'] !== '') {
                $seg = $segments->first(fn ($s) => strcasecmp($s->name, $r['segment']) === 0);
                $seg ? $segmentId = $seg->id : $issues[] = "Unknown segment \"{$r['segment']}\"";
            }
            $rmId = null; $rmName = null;
            if ($r['relationship_manager'] !== '') {
                $exact = $users->filter(fn ($u) => strcasecmp($u->name, $r['relationship_manager']) === 0);
                $match = $exact->count() === 1 ? $exact : $users->filter(fn ($u) => stripos($u->name, $r['relationship_manager']) !== false);
                if ($match->count() === 1) {
                    $rmId = $match->first()->id; $rmName = $match->first()->name;
                } else {
                    $issues[] = $match->count() ? "Relationship manager \"{$r['relationship_manager']}\" matches several users — use the full name" : "Unknown relationship manager \"{$r['relationship_manager']}\"";
                }
            }
            $status = strtolower($r['status'] ?: 'active');
            if (!in_array($status, ['active', 'inactive', 'blacklisted'], true)) {
                $issues[] = "Invalid status \"{$r['status']}\"";
            }
            $fee = $r['membership_fee'] === '' ? 50000 : (float) str_replace(',', '', $r['membership_fee']);

            $parts = explode(' ', $r['name']);
            $rows[] = [
                'line' => $line, 'client_number' => $r['client_number'], 'name' => $r['name'],
                'first_name' => $r['first_name'] ?: $parts[0], 'last_name' => $r['last_name'] ?: (count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : ''),
                'segment_id' => $segmentId, 'segment' => $r['segment'], 'rm_id' => $rmId, 'rm' => $rmName ?? $r['relationship_manager'],
                'status' => $status, 'membership_fee' => $fee, 'phone' => $r['phone'] ?: null, 'email' => $r['email'] ?: null,
                'issues' => $issues, 'skip' => $skip,
            ];
        }
        fclose($handle);

        session(['client_import' => ['file' => $request->file('file')->getClientOriginalName(), 'rows' => $rows]]);
        return redirect()->route('clients.import');
    }

    /** Create the valid, new members from the previewed file. */
    public function confirm()
    {
        $preview = session('client_import');
        if (!$preview) {
            return redirect()->route('clients.import')->with('error', 'Nothing to import — upload a file first.');
        }

        $created = 0; $skipped = 0;
        DB::transaction(function () use ($preview, &$created, &$skipped) {
            foreach ($preview['rows'] as $r) {
                if ($r['issues'] || $r['skip'] || Client::withTrashed()->where('client_number', $r['client_number'])->exists()) {
                    $skipped++;
                    continue;
                }
                Client::create([
                    'client_number' => $r['client_number'], 'client_type' => 'individual', 'name' => $r['name'],
                    'first_name' => $r['first_name'], 'last_name' => $r['last_name'] ?: null,
                    'segment_id' => $r['segment_id'], 'relationship_manager_id' => $r['rm_id'],
                    'status' => $r['status'], 'membership_fee' => $r['membership_fee'], 'membership_fee_paid' => 0,
                    'membership_fee_status' => 'unpaid', 'phone' => $r['phone'], 'email' => $r['email'],
                    'created_by' => auth()->id(),
                ]);
                $created++;
            }
            AuditLog::record('clients_imported', "Imported {$created} member(s) from {$preview['file']} ({$skipped} skipped)", 'clients');
        });

        session()->forget('client_import');
        return redirect()->route('clients.import')->with('success', "Imported {$created} member(s)." . ($skipped ? " {$skipped} row(s) skipped (existing codes or problems)." : ''));
    }

    public function cancel()
    {
        session()->forget('client_import');
        return redirect()->route('clients.import');
    }
}
