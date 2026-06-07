<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size:8px; color:#1a1a1a; }
    .header { background:#0f2444; color:#fff; padding:10px 16px; margin-bottom:10px; }
    .header h1 { font-size:13px; font-weight:bold; }
    .header p  { font-size:8px; opacity:0.7; margin-top:2px; }
    .header-right { float:right; text-align:right; font-size:8px; }
    .clearfix::after { content:''; display:table; clear:both; }
    .bucket-header { padding:5px 8px; font-weight:bold; font-size:9px; color:#fff; margin-top:8px; margin-bottom:2px; border-radius:3px; }
    .bucket-1-30   { background:#059669; }
    .bucket-31-60  { background:#0891b2; }
    .bucket-61-90  { background:#d97706; }
    .bucket-91-180 { background:#ea580c; }
    .bucket-181    { background:#dc2626; }
    table { width:100%; border-collapse:collapse; margin-bottom:6px; }
    thead th { background:#374151; color:#fff; padding:4px 5px; font-size:7px; text-transform:uppercase; text-align:left; }
    thead th.r { text-align:right; }
    tbody td { padding:4px 5px; border-bottom:1px solid #f3f4f6; font-size:8px; }
    tbody td.r { text-align:right; }
    tbody tr:nth-child(even) td { background:#f9fafb; }
    tfoot td { padding:4px 5px; font-weight:bold; font-size:8px; background:#e5e7eb; border-top:1px solid #374151; }
    tfoot td.r { text-align:right; }
    .footer { margin-top:8px; padding-top:5px; border-top:1px solid #e5e7eb; font-size:7px; color:#9ca3af; }
</style>
</head>
<body>
<div class="header clearfix">
    <div class="header-right">
        <div>Generated: {{ now()->format('d M Y H:i') }}</div>
        <div>As of {{ $asOf }}</div>
    </div>
    <h1>@php $_logo = \App\Models\SystemSetting::get('org_logo'); @endphp@if($_logo)<img src="{{ public_path('storage/'.$_logo) }}" style="height:32px;max-width:160px;object-fit:contain;vertical-align:middle">@else{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}@endif — Loan Aging Report</h1>
    <p>Overdue installments grouped by days past due</p>
</div>

@php
$bucketClasses = ['1-30'=>'bucket-1-30','31-60'=>'bucket-31-60','61-90'=>'bucket-61-90','91-180'=>'bucket-91-180','181+'=>'bucket-181'];
$bucketLabels  = ['1-30'=>'1–30 Days','31-60'=>'31–60 Days','61-90'=>'61–90 Days','91-180'=>'91–180 Days','181+'=>'181+ Days'];
@endphp

@foreach($buckets as $bucket => $items)
<div class="bucket-header {{ $bucketClasses[$bucket] }}">
    {{ $bucketLabels[$bucket] }} Overdue — {{ count($items) }} installment(s) &bull; {{ number_format(array_sum(array_column($items, 'outstanding')), 2) }}
</div>
@if(count($items) > 0)
<table>
    <thead>
        <tr>
            <th>Loan #</th><th>Client</th><th>Product</th><th>Due Date</th>
            <th class="r">Days Overdue</th><th class="r">Outstanding</th>
        </tr>
    </thead>
    <tbody>
    @foreach($items as $item)
    <tr>
        <td style="font-family:monospace;font-size:7px">{{ $item['schedule']->loan->loan_number }}</td>
        <td>{{ $item['schedule']->loan->client->name }}</td>
        <td style="font-size:7px;color:#6b7280">{{ $item['schedule']->loan->product->name }}</td>
        <td>{{ $item['schedule']->due_date->format('d M Y') }}</td>
        <td class="r"><strong>{{ $item['days'] }}</strong></td>
        <td class="r">{{ number_format($item['outstanding'], 2) }}</td>
    </tr>
    @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5">Subtotal</td>
            <td class="r">{{ number_format(array_sum(array_column($items, 'outstanding')), 2) }}</td>
        </tr>
    </tfoot>
</table>
@endif
@endforeach

<div class="footer">Printed by {{ auth()->user()->name ?? 'System' }} &bull; {{ now()->format('d M Y H:i') }}</div>
</body>
</html>
