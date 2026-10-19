<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; }
    h2 { margin: 0 0 2px; font-size: 14px; color: #0f2444; }
    .muted { color: #6b7280; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th { background: #0f2444; color: #fff; text-align: left; padding: 4px; font-size: 8px; text-transform: uppercase; }
    td { padding: 3px 4px; border-bottom: 1px solid #e5e7eb; }
    .r { text-align: right; }
    tfoot td { font-weight: bold; background: #f3f4f6; }
</style>
</head>
<body>
    @php $dp = (int) \App\Models\SystemSetting::get('decimal_places', 2); @endphp
    <h2>{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }} — Loan Penalties</h2>
    <div class="muted">{{ \Carbon\Carbon::parse($from)->format('d M Y') }} – {{ \Carbon\Carbon::parse($to)->format('d M Y') }} · {{ $rows->count() }} loans · outstanding {{ number_format($totals['outstanding'], $dp) }} · incurred {{ number_format($totals['incurred'], $dp) }} · paid {{ number_format($totals['paid'], $dp) }}</div>
    <table>
        <thead><tr><th>Loan #</th><th>Client</th><th>Client #</th><th>RM</th><th class="r">Missed</th><th>Oldest missed</th><th class="r">Brought fwd</th><th class="r">Incurred (period)</th><th class="r">Paid (period)</th><th class="r">Outstanding</th></tr></thead>
        <tbody>
        @foreach($rows as $r)
            <tr>
                <td>{{ $r->loan->loan_number }}</td>
                <td>{{ $r->loan->client->name ?? '' }}</td>
                <td>{{ $r->loan->client->client_number ?? '' }}</td>
                <td>{{ $r->loan->client->relationshipManager->name ?? '' }}</td>
                <td class="r">{{ $r->missed }}</td>
                <td>{{ $r->oldest_due ? \Carbon\Carbon::parse($r->oldest_due)->format('d M Y') : '' }}</td>
                <td class="r">{{ number_format($r->brought_forward, $dp) }}</td>
                <td class="r">{{ number_format($r->incurred, $dp) }}</td>
                <td class="r">{{ number_format($r->paid, $dp) }}</td>
                <td class="r">{{ number_format($r->outstanding, $dp) }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot><tr><td colspan="4">Total</td><td class="r">{{ $totals['missed'] }}</td><td></td><td class="r">{{ number_format($totals['brought_forward'], $dp) }}</td><td class="r">{{ number_format($totals['incurred'], $dp) }}</td><td class="r">{{ number_format($totals['paid'], $dp) }}</td><td class="r">{{ number_format($totals['outstanding'], $dp) }}</td></tr></tfoot>
    </table>
    <p class="muted">Generated {{ now()->format('d M Y H:i') }}</p>
</body>
</html>
