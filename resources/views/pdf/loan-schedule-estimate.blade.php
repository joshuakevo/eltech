<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; background: #fff; }
    .header { background: #0f2444; color: #fff; padding: 16px 24px; margin-bottom: 18px; }
    .header h1 { font-size: 17px; font-weight: bold; margin-bottom: 2px; }
    .header p { font-size: 10px; opacity: 0.75; }
    .header-right { float: right; text-align: right; font-size: 10px; opacity: .8; }
    .clearfix::after { content: ''; display: table; clear: both; }
    .wrap { padding: 0 24px 20px; }
    .summary { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    .summary td { width: 25%; padding: 8px 10px; background: #f0f2f5; border: 2px solid #fff; vertical-align: top; }
    .summary .l { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: .05em; }
    .summary .v { font-size: 14px; font-weight: bold; color: #0f2444; margin-top: 2px; }
    .terms { font-size: 10px; color: #374151; margin-bottom: 10px; }
    .fees { width: 100%; border-collapse: collapse; margin-bottom: 14px; font-size: 10px; }
    .fees td { padding: 4px 8px; border-bottom: 1px solid #e5e7eb; }
    .fees td.r { text-align: right; font-weight: bold; }
    .data-table { width: 100%; border-collapse: collapse; }
    .data-table th { background: #0f2444; color: #fff; font-size: 9px; text-transform: uppercase; letter-spacing: .05em; padding: 6px 8px; text-align: left; }
    .data-table td { padding: 5px 8px; border-bottom: 1px solid #f3f4f6; font-size: 10px; }
    .data-table tbody tr:nth-child(even) td { background: #f9fafb; }
    .data-table tfoot td { font-weight: bold; background: #f0f2f5; padding: 6px 8px; font-size: 10px; }
    .r { text-align: right; }
    .note { margin-top: 14px; font-size: 9px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 8px; }
</style>
</head>
<body>
@php
    $orgName = \App\Models\SystemSetting::get('org_name', 'ElTech Finance');
    $logo = \App\Models\SystemSetting::get('org_logo');
    $freq = \App\Models\Loan::FREQUENCIES[$in['frequency']][0] ?? 'Monthly';
@endphp
<div class="header clearfix">
    <div class="header-right">Prepared {{ now()->format('d M Y H:i') }}<br>{{ auth()->user()->name ?? '' }}</div>
    <h1>@if($logo && file_exists(public_path($logo)))<img src="{{ public_path($logo) }}" style="height:34px;max-width:170px;vertical-align:middle">@else{{ $orgName }}@endif</h1>
    <p>Loan Repayment Schedule — estimate{{ $product ? ' · ' . $product->name : '' }}</p>
</div>

<div class="wrap">
    <div class="terms">
        <strong>{{ number_format($in['principal'], 0) }}</strong> at <strong>{{ $in['monthly'] + 0 }}% per month</strong> ({{ round($in['rate'], 2) + 0 }}% p.a.),
        {{ $in['method'] === 'flat' ? 'flat rate' : 'reducing balance' }}, {{ $in['term'] }} months, {{ strtolower($freq) }} repayments ·
        disbursed {{ \Carbon\Carbon::parse($in['date'])->format('d M Y') }}, matures {{ $result['maturity']->format('d M Y') }}
    </div>

    <table class="summary">
        <tr>
            <td><div class="l">{{ ($result['rows'][0]['step_months'] ?? 1) > 1 ? 'Installment' : 'Monthly installment' }}</div><div class="v">{{ number_format($result['installment'], 0) }}</div></td>
            <td><div class="l">Installments</div><div class="v">{{ $result['periods'] }}</div></td>
            <td><div class="l">Total interest</div><div class="v">{{ number_format($result['interest'], 0) }}</div></td>
            <td><div class="l">Total repayable</div><div class="v">{{ number_format($result['repayable'], 0) }}</div></td>
        </tr>
    </table>

    <table class="fees">
        <tr><td>Loan amount</td><td class="r">{{ number_format($in['principal'], 0) }}</td>
            <td>Management fee ({{ $in['mgmt_rate'] + 0 }}%)</td><td class="r">{{ number_format($result['fees']['management'], 0) }}</td></tr>
        <tr><td>Insurance ({{ $in['ins_rate'] + 0 }}%) + application fee</td><td class="r">{{ number_format($result['fees']['insurance'] + $result['fees']['application'], 0) }}</td>
            <td>Member receives (if fees deducted)</td><td class="r">{{ number_format($result['net'], 0) }}</td></tr>
    </table>

    <table class="data-table">
        <thead><tr><th>#</th><th>Due date</th><th class="r">Principal</th><th class="r">Interest</th><th class="r">Installment</th><th class="r">Balance after</th></tr></thead>
        <tbody>
        @foreach($result['rows'] as $r)
            <tr>
                <td>{{ $r['installment_no'] }}</td>
                <td>{{ \Carbon\Carbon::parse($r['due_date'])->format('d M Y') }}</td>
                <td class="r">{{ number_format($r['principal_due'], 0) }}</td>
                <td class="r">{{ number_format($r['interest_due'], 0) }}</td>
                <td class="r">{{ number_format($r['total_due'], 0) }}</td>
                <td class="r">{{ number_format($r['balance_after'], 0) }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot><tr><td colspan="2">Total</td><td class="r">{{ number_format($in['principal'], 0) }}</td><td class="r">{{ number_format($result['interest'], 0) }}</td><td class="r">{{ number_format($result['repayable'], 0) }}</td><td></td></tr></tfoot>
    </table>

    <div class="note">Estimate only — {{ $orgName }}. Interest on running loans is charged by days between installments, so actual figures can differ slightly from this schedule.</div>
</div>
</body>
</html>
