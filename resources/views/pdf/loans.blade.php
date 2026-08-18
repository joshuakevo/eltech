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
    <h1>@php $_logo = \App\Models\SystemSetting::get('org_logo'); @endphp@if($_logo)<img src="{{ public_path($_logo) }}" style="height:32px;max-width:160px;object-fit:contain;vertical-align:middle">@else{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}@endif — {{ ($type ?? 'normal') === 'locked-up' ? 'Locked-Up Loans' : 'Loans' }}</h1>
    <p>Most recently created first</p>
</div>

@php $isLockedUp = ($type ?? 'normal') === 'locked-up'; @endphp
<table class="summary-row">
    <tr>
        <td><span class="lbl">Number of Loans</span><span class="val">{{ number_format($totalCount) }}</span></td>
        <td><span class="lbl">{{ $isLockedUp ? 'Total Principal' : 'Total Outstanding' }}</span><span class="val">{{ number_format($totalOutstanding, 0) }}</span></td>
        @if($isLockedUp)
        <td><span class="lbl">Total Interest</span><span class="val">{{ number_format($totalInterest ?? 0, 0) }}</span></td>
        <td><span class="lbl">Total</span><span class="val">{{ number_format($totalOutstanding + ($totalInterest ?? 0), 0) }}</span></td>
        @endif
    </tr>
</table>

<table class="main">
    <thead>
        <tr>
            <th>#</th>
            <th>Loan #</th>
            <th>Client</th>
            <th>Product</th>
            <th class="r">Principal</th>
            @if($isLockedUp)
                <th class="r">Interest</th>
                <th class="r">Total</th>
            @else
                <th class="r">Outstanding</th>
            @endif
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
    @foreach($loans as $i => $loan)
    <tr>
        <td class="text-muted">{{ $i + 1 }}</td>
        <td>{{ $loan->loan_number }}</td>
        <td><strong>{{ $loan->client->name ?? '—' }}</strong> <span class="text-muted">{{ $loan->client->client_number ?? '' }}</span></td>
        <td class="text-muted">{{ $loan->product->name ?? '—' }}</td>
        <td class="r">{{ number_format($loan->principal, 0) }}</td>
        @if($isLockedUp)
            <td class="r">{{ number_format($loan->outstanding_interest, 0) }}</td>
            <td class="r">{{ number_format($loan->principal + $loan->outstanding_interest, 0) }}</td>
        @else
            <td class="r">{{ number_format($loan->outstanding_principal, 0) }}</td>
        @endif
        <td class="{{ $loan->status === 'active' ? 'badge-active' : 'badge-other' }}">{{ ucfirst($loan->status) }}</td>
    </tr>
    @endforeach
    </tbody>
    <tfoot>
        @if($isLockedUp)
        <tr>
            <td colspan="4">TOTAL ({{ number_format($totalCount) }} loans)</td>
            <td class="r">{{ number_format($totalOutstanding, 0) }}</td>
            <td class="r">{{ number_format($totalInterest ?? 0, 0) }}</td>
            <td class="r">{{ number_format($totalOutstanding + ($totalInterest ?? 0), 0) }}</td>
            <td></td>
        </tr>
        @else
        <tr>
            <td colspan="4">TOTAL ({{ number_format($totalCount) }} loans)</td>
            <td class="r">{{ number_format($totalOutstanding, 0) }}</td>
            <td></td>
        </tr>
        @endif
    </tfoot>
</table>

<div class="footer">
    ElTech Finance &bull; Loans export
</div>
</body>
</html>
