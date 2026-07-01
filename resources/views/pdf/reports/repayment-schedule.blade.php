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
    .meta { margin-bottom:10px; border-collapse:collapse; width:100%; }
    .meta td { padding:3px 8px; font-size:8px; }
    .meta .lbl { color:#6b7280; width:120px; }
    table.main { width:100%; border-collapse:collapse; }
    table.main thead th { background:#0f2444; color:#fff; padding:5px 6px; font-size:8px; text-transform:uppercase; text-align:left; }
    table.main thead th.r { text-align:right; }
    table.main tbody td { padding:5px 6px; border-bottom:1px solid #f3f4f6; font-size:9px; }
    table.main tbody td.r { text-align:right; }
    table.main tbody tr:nth-child(even) td { background:#f9fafb; }
    table.main tbody tr.overdue td { background:#fef2f2; }
    table.main tfoot td { padding:6px; font-weight:bold; font-size:9px; background:#e5e7eb; border-top:2px solid #0f2444; }
    table.main tfoot td.r { text-align:right; }
    .badge { padding:2px 4px; border-radius:3px; font-size:7px; color:#fff; }
    .badge-paid    { background:#059669; }
    .badge-partial { background:#d97706; }
    .badge-pending { background:#6b7280; }
    .badge-overdue { background:#dc2626; }
    .footer { margin-top:10px; padding-top:6px; border-top:1px solid #e5e7eb; font-size:7px; color:#9ca3af; }
</style>
</head>
<body>
<div class="header clearfix">
    <div class="header-right">
        <div>Generated: {{ now()->format('d M Y H:i') }}</div>
    </div>
    <h1>@php $_logo = \App\Models\SystemSetting::get('org_logo'); @endphp@if($_logo)<img src="{{ public_path($_logo) }}" style="height:32px;max-width:160px;object-fit:contain;vertical-align:middle">@else{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}@endif — Repayment Schedule</h1>
    <p>{{ $loan->loan_number }} &bull; {{ $loan->client->name }}</p>
</div>

<table class="meta">
    <tr>
        <td class="lbl">Product</td><td>{{ $loan->product->name }}</td>
        <td class="lbl">Principal</td><td>{{ number_format($loan->principal, 2) }}</td>
    </tr>
    <tr>
        <td class="lbl">Interest Rate</td><td>{{ $loan->interest_rate }}% {{ ucfirst($loan->interest_method) }}</td>
        <td class="lbl">Term</td><td>{{ $loan->term_months }} months</td>
    </tr>
    <tr>
        <td class="lbl">Disbursed</td><td>{{ $loan->disbursement_date?->format('d M Y') }}</td>
        <td class="lbl">Maturity</td><td>{{ $loan->maturity_date?->format('d M Y') }}</td>
    </tr>
</table>

<table class="main">
    <thead>
        <tr>
            <th>#</th>
            <th>Due Date</th>
            <th class="r">Principal</th>
            <th class="r">Interest</th>
            <th class="r">Total Due</th>
            <th class="r">Principal Paid</th>
            <th class="r">Interest Paid</th>
            <th class="r">Balance After</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
    @foreach($loan->schedules as $s)
    <tr class="{{ $s->isOverdue() ? 'overdue' : '' }}">
        <td>{{ $s->installment_no }}</td>
        <td>{{ $s->due_date->format('d M Y') }}</td>
        <td class="r">{{ number_format($s->principal_due, 2) }}</td>
        <td class="r">{{ number_format($s->interest_due, 2) }}</td>
        <td class="r"><strong>{{ number_format($s->total_due, 2) }}</strong></td>
        <td class="r">{{ $s->principal_paid > 0 ? number_format($s->principal_paid, 2) : '—' }}</td>
        <td class="r">{{ $s->interest_paid > 0 ? number_format($s->interest_paid, 2) : '—' }}</td>
        <td class="r">{{ number_format($s->balance_after, 2) }}</td>
        <td><span class="badge badge-{{ $s->status }}">{{ ucfirst($s->status) }}</span></td>
    </tr>
    @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="2">TOTALS</td>
            <td class="r">{{ number_format($loan->schedules->sum('principal_due'), 2) }}</td>
            <td class="r">{{ number_format($loan->schedules->sum('interest_due'), 2) }}</td>
            <td class="r">{{ number_format($loan->schedules->sum('total_due'), 2) }}</td>
            <td class="r">{{ number_format($loan->schedules->sum('principal_paid'), 2) }}</td>
            <td class="r">{{ number_format($loan->schedules->sum('interest_paid'), 2) }}</td>
            <td></td><td></td>
        </tr>
    </tfoot>
</table>
<div class="footer">Printed by {{ auth()->user()->name ?? 'System' }} &bull; {{ now()->format('d M Y H:i') }}</div>
</body>
</html>
