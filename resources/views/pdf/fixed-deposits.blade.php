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
    table.main tfoot td { padding: 6px; font-weight: bold; font-size: 9px; background: #e5e7eb; border-top: 2px solid #0f2444; }
    table.main tfoot td.r { text-align: right; }
    .text-muted { color: #6b7280; }
    .badge-active { color: #16a34a; }
    .badge-other { color: #6b7280; }
    .footer { margin-top: 12px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 8px; color: #9ca3af; }
</style>
</head>
<body>
<div class="header clearfix">
    <div class="header-right">
        <div>Generated: {{ now()->format('d M Y H:i') }}</div>
    </div>
    <h1>@php $_logo = \App\Models\SystemSetting::get('org_logo'); @endphp@if($_logo)<img src="{{ public_path($_logo) }}" style="height:32px;max-width:160px;object-fit:contain;vertical-align:middle">@else{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}@endif — Fixed Deposits</h1>
    <p>Sorted by nearest maturity date first</p>
</div>

<table class="summary-row">
    <tr>
        <td><span class="lbl">Number of Deposits</span><span class="val">{{ number_format($totalCount) }}</span></td>
        <td><span class="lbl">Total Principal</span><span class="val">{{ number_format($totalPrincipal, 0) }}</span></td>
    </tr>
</table>

<table class="main">
    <thead>
        <tr>
            <th>#</th>
            <th>Deposit #</th>
            <th>Client</th>
            <th>Product</th>
            <th class="r">Principal</th>
            <th>Maturity Date</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
    @foreach($deposits as $i => $fd)
    <tr>
        <td class="text-muted">{{ $i + 1 }}</td>
        <td>{{ $fd->deposit_number }}</td>
        <td><strong>{{ $fd->client->name ?? '—' }}</strong> <span class="text-muted">{{ $fd->client->client_number ?? '' }}</span></td>
        <td class="text-muted">{{ $fd->product->name ?? '—' }}</td>
        <td class="r">{{ number_format($fd->principal, 0) }}</td>
        <td>{{ optional($fd->maturity_date)->format('d M Y') ?? '—' }}</td>
        <td class="{{ $fd->status === 'active' ? 'badge-active' : 'badge-other' }}">{{ ucfirst($fd->status) }}</td>
    </tr>
    @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4">TOTAL ({{ number_format($totalCount) }} deposits)</td>
            <td class="r">{{ number_format($totalPrincipal, 0) }}</td>
            <td colspan="2"></td>
        </tr>
    </tfoot>
</table>

<div class="footer">
    ElTech Finance &bull; Fixed Deposits export
</div>
</body>
</html>
