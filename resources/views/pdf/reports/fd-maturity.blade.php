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
    .total-box .val { font-size:18px; font-weight:bold; color:#d97706; }
    table { width:100%; border-collapse:collapse; }
    thead th { background:#0f2444; color:#fff; padding:5px 6px; font-size:8px; text-transform:uppercase; text-align:left; }
    thead th.r { text-align:right; }
    tbody td { padding:5px 6px; border-bottom:1px solid #f3f4f6; font-size:9px; }
    tbody td.r { text-align:right; }
    tbody tr:nth-child(even) td { background:#f9fafb; }
    tfoot td { padding:6px; font-weight:bold; font-size:9px; background:#e5e7eb; border-top:2px solid #0f2444; }
    tfoot td.r { text-align:right; }
    .matured { color:#dc2626; font-weight:bold; }
    .footer { margin-top:10px; padding-top:6px; border-top:1px solid #e5e7eb; font-size:7px; color:#9ca3af; }
</style>
</head>
<body>
<div class="header clearfix">
    <div class="header-right">
        <div>Generated: {{ now()->format('d M Y H:i') }}</div>
        <div>Maturity window: {{ $fromDate }} to {{ $toDate }}</div>
    </div>
    <h1>@php $_logo = \App\Models\SystemSetting::get('org_logo'); @endphp@if($_logo)<img src="{{ public_path('storage/'.$_logo) }}" style="height:32px;max-width:160px;object-fit:contain;vertical-align:middle">@else{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}@endif — Fixed Deposit Maturity Report</h1>
    <p>Active FDs maturing in the selected date range</p>
</div>

<div class="total-box">
    <div class="lbl">Total Maturity Payouts Due</div>
    <div class="val">{{ number_format($total, 2) }}</div>
</div>

<table>
    <thead>
        <tr>
            <th>Deposit #</th><th>Client</th><th>Product</th>
            <th class="r">Principal</th><th class="r">Interest</th>
            <th class="r">Maturity Amount</th><th>Maturity Date</th>
        </tr>
    </thead>
    <tbody>
    @forelse($deposits as $fd)
    <tr>
        <td style="font-family:monospace;font-size:8px">{{ $fd->deposit_number }}</td>
        <td>{{ $fd->client->name }}</td>
        <td style="font-size:8px;color:#6b7280">{{ $fd->product->name }}</td>
        <td class="r">{{ number_format($fd->principal, 2) }}</td>
        <td class="r" style="color:#059669">{{ number_format($fd->interest_amount, 2) }}</td>
        <td class="r" style="font-weight:bold">{{ number_format($fd->maturity_amount, 2) }}</td>
        <td class="{{ $fd->isMatured() ? 'matured' : '' }}" style="color:#d97706">{{ $fd->maturity_date->format('d M Y') }}</td>
    </tr>
    @empty
    <tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:10px">No FDs maturing in this period.</td></tr>
    @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5">TOTAL ({{ $deposits->count() }} deposits)</td>
            <td class="r" style="color:#d97706">{{ number_format($total, 2) }}</td>
            <td></td>
        </tr>
    </tfoot>
</table>
<div class="footer">Printed by {{ auth()->user()->name ?? 'System' }} &bull; {{ now()->format('d M Y H:i') }}</div>
</body>
</html>
