<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1a1a1a; }
    .header { background: #0f2444; color: #fff; padding: 14px 20px; margin-bottom: 14px; }
    .header h1 { font-size: 15px; font-weight: bold; }
    .header p { font-size: 9px; opacity: 0.7; margin-top: 2px; }
    .header-right { float: right; text-align: right; }
    .clearfix::after { content: ''; display: table; clear: both; }
    .summary-row { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    .summary-row td { border: 1px solid #e5e7eb; padding: 6px 10px; text-align: center; }
    .summary-row .lbl { font-size: 8px; color: #6b7280; display: block; }
    .summary-row .val { font-size: 11px; font-weight: bold; color: #0f2444; }
    table.main { width: 100%; border-collapse: collapse; }
    table.main thead th { background: #0f2444; color: #fff; padding: 5px 6px; font-size: 8px; text-transform: uppercase; letter-spacing: 0.05em; text-align: left; }
    table.main thead th.r { text-align: right; }
    table.main tbody td { padding: 5px 6px; border-bottom: 1px solid #f3f4f6; font-size: 9px; }
    table.main tbody td.r { text-align: right; }
    table.main tbody tr:nth-child(even) td { background: #f9fafb; }
    .text-muted { color: #6b7280; }
    .footer { margin-top: 12px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 8px; color: #9ca3af; }
</style>
</head>
<body>
<div class="header clearfix">
    <div class="header-right">
        <div>Generated: {{ now()->format('d M Y H:i') }}</div>
        <div>{{ \Carbon\Carbon::parse($fromDate)->format('d M Y') }} to {{ \Carbon\Carbon::parse($toDate)->format('d M Y') }}</div>
    </div>
    <h1>@php $_logo = \App\Models\SystemSetting::get('org_logo'); @endphp@if($_logo)<img src="{{ public_path($_logo) }}" style="height:32px;max-width:160px;object-fit:contain;vertical-align:middle">@else{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}@endif — Journal Entries</h1>
    <p>All accounts</p>
</div>

<table class="summary-row">
    <tr>
        <td><span class="lbl">Number of Entries</span><span class="val">{{ number_format($all->count()) }}</span></td>
        <td><span class="lbl">Total Amount</span><span class="val">{{ number_format($all->sum('lines_sum_debit'), 0) }}</span></td>
    </tr>
</table>

<table class="main">
    <thead>
        <tr>
            <th>Reference</th>
            <th>Date</th>
            <th>Description</th>
            <th>Module</th>
            <th class="r">Amount</th>
            <th>Created By</th>
        </tr>
    </thead>
    <tbody>
    @forelse($all as $txn)
    <tr>
        <td style="font-family:monospace">{{ $txn->reference }}</td>
        <td class="text-muted">{{ $txn->date->format('d M Y') }}</td>
        <td>{{ Str::limit($txn->description, 60) }}</td>
        <td class="text-muted">{{ ucfirst($txn->module ?? 'manual') }}</td>
        <td class="r">{{ number_format($txn->lines_sum_debit ?? 0, 0) }}</td>
        <td class="text-muted">{{ $txn->createdBy->name ?? '—' }}</td>
    </tr>
    @empty
    <tr><td colspan="6" style="text-align:center;color:#9ca3af;padding:10px">No transactions found.</td></tr>
    @endforelse
    </tbody>
</table>

<div class="footer">ElTech Finance &bull; Journal Entries export</div>
</body>
</html>
