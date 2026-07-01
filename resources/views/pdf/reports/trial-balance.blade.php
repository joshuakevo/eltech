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
    table { width:100%; border-collapse:collapse; }
    thead th { background:#0f2444; color:#fff; padding:5px 6px; font-size:8px; text-transform:uppercase; letter-spacing:.05em; text-align:left; }
    thead th.r { text-align:right; }
    tbody td { padding:5px 6px; border-bottom:1px solid #f3f4f6; font-size:9px; }
    tbody td.r { text-align:right; }
    tbody tr:nth-child(even) td { background:#f9fafb; }
    tfoot td { padding:6px; font-weight:bold; font-size:9px; background:#e5e7eb; border-top:2px solid #0f2444; }
    tfoot td.r { text-align:right; }
    .badge { padding:2px 5px; border-radius:3px; font-size:7px; color:#fff; }
    .badge-asset     { background:#2563eb; }
    .badge-liability { background:#dc2626; }
    .badge-equity    { background:#7c3aed; }
    .badge-income    { background:#059669; }
    .badge-expense   { background:#d97706; }
    .footer { margin-top:10px; padding-top:6px; border-top:1px solid #e5e7eb; font-size:7px; color:#9ca3af; }
    .status-badge { padding:2px 5px; border-radius:3px; font-size:7px; color:#fff; }
    .balanced { background:#059669; }
    .unbalanced { background:#dc2626; }
</style>
</head>
<body>
<div class="header clearfix">
    <div class="header-right">
        <div>Generated: {{ now()->format('d M Y H:i') }}</div>
        @if($fromDate)<div>Period: {{ $fromDate }} to {{ $toDate }}</div>@else<div>As of {{ $toDate }}</div>@endif
    </div>
    <h1>@php $_logo = \App\Models\SystemSetting::get('org_logo'); @endphp@if($_logo)<img src="{{ public_path($_logo) }}" style="height:32px;max-width:160px;object-fit:contain;vertical-align:middle">@else{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}@endif — Trial Balance</h1>
    <p>
        @if($data['balanced'])
            <span class="status-badge balanced">Balanced</span>
        @else
            <span class="status-badge unbalanced">Unbalanced</span>
        @endif
    </p>
</div>
<table>
    <thead>
        <tr>
            <th style="width:70px">Code</th>
            <th>Account Name</th>
            <th style="width:80px">Type</th>
            <th class="r" style="width:110px">Debit</th>
            <th class="r" style="width:110px">Credit</th>
        </tr>
    </thead>
    <tbody>
    @foreach($data['rows'] as $row)
    <tr>
        <td style="font-family:monospace">{{ $row['account_code'] }}</td>
        <td>{{ $row['account_name'] }}</td>
        <td><span class="badge badge-{{ $row['account_type'] }}">{{ ucfirst($row['account_type']) }}</span></td>
        <td class="r">{{ $row['debit'] > 0 ? number_format($row['debit'], 2) : '' }}</td>
        <td class="r">{{ $row['credit'] > 0 ? number_format($row['credit'], 2) : '' }}</td>
    </tr>
    @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3" style="text-align:right">TOTALS</td>
            <td class="r">{{ number_format($data['total_debit'], 2) }}</td>
            <td class="r">{{ number_format($data['total_credit'], 2) }}</td>
        </tr>
    </tfoot>
</table>
<div class="footer">Printed by {{ auth()->user()->name ?? 'System' }} &bull; {{ now()->format('d M Y H:i') }}</div>
</body>
</html>
