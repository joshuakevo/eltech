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
    .total-box .val { font-size:18px; font-weight:bold; color:#059669; }
    table { width:100%; border-collapse:collapse; }
    thead th { background:#0f2444; color:#fff; padding:5px 6px; font-size:8px; text-transform:uppercase; text-align:left; }
    thead th.r { text-align:right; }
    tbody td { padding:5px 6px; border-bottom:1px solid #f3f4f6; font-size:9px; }
    tbody td.r { text-align:right; }
    tbody tr:nth-child(even) td { background:#f9fafb; }
    tfoot td { padding:6px; font-weight:bold; font-size:9px; background:#e5e7eb; border-top:2px solid #0f2444; }
    tfoot td.r { text-align:right; }
    .footer { margin-top:10px; padding-top:6px; border-top:1px solid #e5e7eb; font-size:7px; color:#9ca3af; }
</style>
</head>
<body>
<div class="header clearfix">
    <div class="header-right">
        <div>Generated: {{ now()->format('d M Y H:i') }}</div>
        <div>Period: {{ $fromDate }} to {{ $toDate }}</div>
    </div>
    <h1>@php $_logo = \App\Models\SystemSetting::get('org_logo'); @endphp@if($_logo)<img src="{{ public_path($_logo) }}" style="height:32px;max-width:160px;object-fit:contain;vertical-align:middle">@else{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}@endif — Interest Income Report</h1>
    <p>Revenue account balances for the selected period</p>
</div>

<div class="total-box">
    <div class="lbl">Total Interest &amp; Revenue Income</div>
    <div class="val">{{ number_format($total, 2) }}</div>
</div>

<table>
    <thead><tr><th style="width:80px">Code</th><th>Account Name</th><th class="r" style="width:130px">Amount</th></tr></thead>
    <tbody>
    @forelse($rows as $row)
    <tr>
        <td style="font-family:monospace">{{ $row['account']->account_code }}</td>
        <td>{{ $row['account']->account_name }}</td>
        <td class="r" style="color:#059669;font-weight:bold">{{ number_format($row['balance'], 2) }}</td>
    </tr>
    @empty
    <tr><td colspan="3" style="text-align:center;color:#9ca3af;padding:10px">No income records found.</td></tr>
    @endforelse
    </tbody>
    <tfoot><tr><td colspan="2">TOTAL</td><td class="r" style="color:#059669">{{ number_format($total, 2) }}</td></tr></tfoot>
</table>
<div class="footer">Printed by {{ auth()->user()->name ?? 'System' }} &bull; {{ now()->format('d M Y H:i') }}</div>
</body>
</html>
