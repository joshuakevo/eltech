<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a1a1a; }
    .header { background: #0f2444; color: #fff; padding: 16px 22px; }
    .header h1 { font-size: 16px; font-weight: bold; }
    .header p { font-size: 10px; opacity: .75; margin-top: 2px; }
    .header-right { float: right; text-align: right; font-size: 9px; opacity: .8; }
    .clearfix::after { content: ''; display: table; clear: both; }
    .wrap { padding: 14px 22px; }
    .section-title { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: .08em; color: #6b7280; border-bottom: 1px solid #e5e7eb; padding-bottom: 3px; margin: 14px 0 6px; }
    .info td { padding: 2px 14px 2px 0; }
    .info .l { color: #6b7280; }
    .info .v { font-weight: bold; }
    .stats { width: 100%; border-collapse: collapse; }
    .stats td { border: 1px solid #e5e7eb; padding: 7px 8px; text-align: center; width: 20%; }
    .stats .sl { display: block; color: #6b7280; font-size: 8px; text-transform: uppercase; }
    .stats .sv { font-size: 12px; font-weight: bold; color: #0f2444; }
    .data { width: 100%; border-collapse: collapse; }
    .data th { background: #0f2444; color: #fff; font-size: 8px; text-transform: uppercase; padding: 5px 6px; text-align: left; }
    .data td { padding: 5px 6px; border-bottom: 1px solid #f1f3f6; font-size: 9.5px; }
    .data tfoot td { font-weight: bold; border-top: 2px solid #0f2444; background: #f1f5fb; }
    .r { text-align: right; }
    .earned { color: #065f46; font-weight: bold; }
    .muted { color: #9ca3af; }
    .footer { margin-top: 18px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 8px; color: #9ca3af; }
</style>
</head>
<body>
@php
    $fd = $fixedDeposit; $p = $fd->product; $n = fn ($v) => number_format($v, 0);
    $earned = collect($schedule)->where('earned', true);
    $logo = \App\Models\SystemSetting::get('org_logo');
@endphp
<div class="header clearfix">
    <div class="header-right">Printed {{ now()->format('d M Y H:i') }}<br>{{ $fd->deposit_number }}</div>
    <h1>@if($logo && file_exists(public_path($logo)))<img src="{{ public_path($logo) }}" style="height:28px;vertical-align:middle">@else{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}@endif</h1>
    <p>Investment Statement — interest earned monthly</p>
</div>
<div class="wrap">
    <table class="info">
        <tr><td class="l">Investor</td><td class="v">{{ $fd->client->name ?? '—' }} ({{ $fd->client->client_number ?? '' }})</td><td class="l">Product</td><td class="v">{{ $p->name ?? '' }}</td></tr>
        <tr><td class="l">Principal</td><td class="v">{{ $n($fd->principal) }}</td><td class="l">Rate</td><td class="v">{{ $p ? $p->rateLabel($fd->interest_rate) : $fd->interest_rate . '% p.a.' }}{{ $fd->isCompounding() ? ' · compounded monthly' : '' }}</td></tr>
        <tr><td class="l">Value date</td><td class="v">{{ $fd->start_date->format('d M Y') }}</td><td class="l">Maturity date</td><td class="v">{{ $fd->maturity_date->format('d M Y') }} ({{ $fd->term_months }} months)</td></tr>
    </table>

    <div class="section-title">Position as at {{ now()->format('d M Y') }}</div>
    <table class="stats"><tr>
        <td><span class="sl">Principal</span><span class="sv">{{ $n($fd->principal) }}</span></td>
        <td><span class="sl">Interest earned so far</span><span class="sv earned">{{ $n($earned->last()['cumulative'] ?? 0) }}</span></td>
        <td><span class="sl">Current value</span><span class="sv">{{ $n($fd->principal + ($earned->last()['cumulative'] ?? 0)) }}</span></td>
        <td><span class="sl">Gross return</span><span class="sv">{{ $n($fd->interest_amount) }}</span></td>
        <td><span class="sl">Payout at maturity</span><span class="sv">{{ $n($fd->maturity_amount) }}</span></td>
    </tr></table>

    <div class="section-title">Monthly statement</div>
    <table class="data">
        <thead><tr><th>Month</th><th>Anniversary date</th><th class="r">Interest earned</th><th class="r">Total interest</th><th class="r">Investment value</th><th>Status</th></tr></thead>
        <tbody>
            <tr><td class="muted">—</td><td>{{ $fd->start_date->format('d M Y') }}</td><td class="r muted">Principal placed</td><td class="r">0</td><td class="r">{{ $n($fd->principal) }}</td><td class="muted">Value date</td></tr>
        @foreach($schedule as $row)
            <tr>
                <td>{{ $row['month'] }}</td>
                <td>{{ $row['date']->format('d M Y') }}</td>
                <td class="r {{ $row['earned'] ? 'earned' : 'muted' }}">{{ $n($row['interest']) }}</td>
                <td class="r">{{ $n($row['cumulative']) }}</td>
                <td class="r"><b>{{ $n($row['value']) }}</b></td>
                <td>{{ $row['earned'] ? 'Earned' : 'Upcoming' }}{{ $loop->last ? ' · maturity' : '' }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot><tr><td colspan="2">Total payout at maturity</td><td class="r">{{ $n($fd->interest_amount) }}</td><td></td><td class="r">{{ $n($fd->maturity_amount) }}</td><td></td></tr></tfoot>
    </table>
    <div class="footer">{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }} · Interest is earned on the {{ $fd->start_date->format('jS') }} of each month and paid with the principal at maturity. This statement is computer generated.</div>
</div>
</body>
</html>
