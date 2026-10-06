<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size:9px; color:#1a1a1a; }
    .header { background:#0f2444; color:#fff; padding:12px 18px; margin-bottom:12px; }
    .header h1 { font-size:14px; font-weight:bold; }
    .header p  { font-size:8px; opacity:0.7; margin-top:2px; }
    .header-right { float:right; text-align:right; font-size:8px; }
    .clearfix::after { content:''; display:table; clear:both; }
    .total-box { text-align:center; border:2px solid #0f2444; padding:10px; margin-bottom:12px; }
    .total-box .lbl { font-size:9px; color:#6b7280; }
    .total-box .val { font-size:18px; font-weight:bold; color:#2563eb; }
    table { width:100%; border-collapse:collapse; }
    thead th { background:#0f2444; color:#fff; padding:5px 6px; font-size:8px; text-transform:uppercase; text-align:left; }
    thead th.r { text-align:right; }
    tbody td { padding:5px 6px; border-bottom:1px solid #f3f4f6; font-size:9px; }
    tbody td.r { text-align:right; }
    tbody tr:nth-child(even) td { background:#f9fafb; }
    tfoot td { padding:6px; font-weight:bold; font-size:9px; background:#e5e7eb; border-top:2px solid #0f2444; }
    tfoot td.r { text-align:right; }
    .kpi { width:20%; border:1px solid #e5e7eb; padding:6px 8px; vertical-align:top; }
    .kpi .lbl { font-size:7px; text-transform:uppercase; color:#6b7280; font-weight:bold; }
    .kpi .val { font-size:12px; font-weight:bold; margin:2px 0; }
    .kpi .sub { font-size:7px; color:#6b7280; }
    .footer { margin-top:10px; padding-top:6px; border-top:1px solid #e5e7eb; font-size:7px; color:#9ca3af; }
</style>
</head>
<body>
<div class="header clearfix">
    <div class="header-right">
        <div>Generated: {{ now()->format('d M Y H:i') }}</div>
        <div>Period: {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</div>
    </div>
    <h1>@php $_logo = \App\Models\SystemSetting::get('org_logo'); @endphp@if($_logo)<img src="{{ public_path($_logo) }}" style="height:32px;max-width:160px;object-fit:contain;vertical-align:middle">@else{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}@endif — Savings Report</h1>
    <p>Member saving activity and balances</p>
</div>

<table style="margin-bottom:10px">
    <tr>
        <td class="kpi"><div class="lbl">Deposits</div><div class="val" style="color:#059669">{{ number_format($flows['deposits'], 0) }}</div><div class="sub">{{ $flows['deposit_count'] }} deposits{{ $insights['deposits_change'] !== null ? ' · ' . ($insights['deposits_change'] >= 0 ? '+' : '') . $insights['deposits_change'] . '% vs previous' : '' }}</div></td>
        <td class="kpi"><div class="lbl">Withdrawals</div><div class="val" style="color:#dc2626">{{ number_format($flows['withdrawals'], 0) }}</div><div class="sub">{{ $flows['withdrawal_count'] }} withdrawals{{ $insights['withdrawal_ratio'] !== null ? ' · ' . $insights['withdrawal_ratio'] . '% of deposits' : '' }}</div></td>
        <td class="kpi"><div class="lbl">Net saved</div><div class="val">{{ number_format($flows['net'], 0) }}</div><div class="sub">incl. {{ number_format($flows['interest'], 0) }} interest</div></td>
        <td class="kpi"><div class="lbl">Active savers</div><div class="val">{{ $flows['savers'] }} / {{ $insights['active_accounts'] }}</div><div class="sub">{{ $insights['participation'] }}% deposited</div></td>
        <td class="kpi"><div class="lbl">Total savings</div><div class="val" style="color:#2563eb">{{ number_format($insights['total_balance'], 0) }}</div><div class="sub">Growing {{ $insights['growing'] }} · Declining {{ $insights['declining'] }} · Dormant {{ $insights['dormant'] }} · Overdrawn {{ $insights['overdrawn'] }}</div></td>
    </tr>
</table>

<table>
    <thead>
        <tr>
            <th>Account #</th><th>Client</th><th class="r">Start balance</th><th class="r">Deposits</th>
            <th class="r">Withdrawals</th><th class="r">Net saved</th><th class="r">Balance</th><th>Last deposit</th><th>Trend</th>
        </tr>
    </thead>
    <tbody>
    @forelse($rows->sortByDesc('net') as $r)
    <tr>
        <td style="font-family:monospace;font-size:8px">{{ $r->account->account_number }}</td>
        <td>{{ $r->account->client?->name }}</td>
        <td class="r">{{ number_format($r->opening, 0) }}</td>
        <td class="r" style="color:#059669">{{ $r->deposits ? number_format($r->deposits, 0) : '—' }}</td>
        <td class="r" style="color:#dc2626">{{ $r->withdrawals ? number_format($r->withdrawals, 0) : '—' }}</td>
        <td class="r"><strong>{{ number_format($r->net, 0) }}</strong></td>
        <td class="r" style="font-weight:bold">{{ number_format($r->balance, 0) }}</td>
        <td style="font-size:8px">{{ $r->last_deposit?->format('d M Y') ?? 'Never' }}</td>
        <td style="font-size:8px">{{ ucfirst($r->trend) }}</td>
    </tr>
    @empty
    <tr><td colspan="9" style="text-align:center;color:#9ca3af;padding:10px">No savings accounts found.</td></tr>
    @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="2">Totals — {{ $rows->count() }} accounts</td>
            <td class="r">{{ number_format($rows->sum('opening'), 0) }}</td>
            <td class="r">{{ number_format($rows->sum('deposits'), 0) }}</td>
            <td class="r">{{ number_format($rows->sum('withdrawals'), 0) }}</td>
            <td class="r">{{ number_format($rows->sum('net'), 0) }}</td>
            <td class="r">{{ number_format($rows->sum('balance'), 0) }}</td>
            <td colspan="2"></td>
        </tr>
    </tfoot>
</table>

<div class="footer">Printed by {{ auth()->user()->name ?? 'System' }} &bull; {{ now()->format('d M Y H:i') }}</div>
</body>
</html>
