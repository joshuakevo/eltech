<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Serif, serif; font-size: 12px; color: #111; padding: 40px 50px; }
    .logo { text-align: right; }
    .logo img { height: 64px; }
    .org { text-align: right; font-size: 18px; font-weight: bold; color: #0f2444; }
    .tagline { text-align: right; font-size: 9px; letter-spacing: .25em; color: #555; margin-top: 4px; }
    h1 { text-align: center; font-size: 14px; text-decoration: underline; margin: 28px 0 22px; letter-spacing: .03em; }
    .meta td { padding: 3px 0; }
    .label { font-weight: bold; }
    h2 { font-size: 12px; margin: 26px 0 4px; }
    .rule { border-top: 1px dashed #555; margin-bottom: 8px; }
    .spec td { padding: 4px 0; vertical-align: top; }
    .total { margin-top: 26px; font-size: 13px; font-weight: bold; border-top: 1.5px solid #111; padding-top: 10px; }
    .note { margin-top: 30px; font-size: 9.5px; color: #555; line-height: 1.5; }
    .sign { margin-top: 50px; width: 100%; }
    .sign td { width: 50%; padding-top: 30px; font-size: 10px; }
    .line { border-top: 1px solid #333; width: 80%; padding-top: 4px; }
</style>
</head>
<body>
@php
    $fd = $fixedDeposit;
    $p = $fd->product;
    $logo = \App\Models\SystemSetting::get('org_logo');
    $days = $fd->start_date->diffInDays($fd->maturity_date);
    $cur = \App\Models\SystemSetting::get('currency', 'UGX');
@endphp

@if($logo && file_exists(public_path($logo)))
    <div class="logo"><img src="{{ public_path($logo) }}"></div>
@else
    <div class="org">{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}</div>
@endif
<div class="tagline">INVEST. PROFIT. PROSPER</div>

<h1>INVESTMENT PLACEMENT CERTIFICATE</h1>

<table class="meta">
    <tr><td class="label">Reference:&nbsp;</td><td>{{ $fd->deposit_number }}</td></tr>
    <tr><td class="label">Date:&nbsp;</td><td>{{ $fd->start_date->format('j F, Y') }}</td></tr>
</table>

<h2>INVESTMENT SPECIFICATIONS:</h2>
<div class="rule"></div>
<table class="spec">
    <tr><td><span class="label">Investor Name:</span> {{ $fd->client->name ?? '—' }}</td></tr>
    <tr><td><span class="label">Asset Class:</span> {{ $p->asset_class ?: $p->name }}</td></tr>
    <tr><td><span class="label">Principal Investment Capital:</span> {{ $cur }} {{ number_format($fd->principal, 0) }}</td></tr>
    <tr><td><span class="label">{{ $p->isMonthlyRate() ? 'Monthly Yield Rate' : 'Yield Rate' }}:</span> {{ $p->rateLabel($fd->interest_rate) }}</td></tr>
    <tr><td><span class="label">Tenure:</span> {{ $fd->term_months }} Month{{ $fd->term_months == 1 ? '' : 's' }} ({{ $days }} Days)</td></tr>
    <tr><td><span class="label">Value Date:</span> {{ $fd->start_date->format('j F, Y') }}</td></tr>
    <tr><td><span class="label">Maturity Date:</span> {{ $fd->maturity_date->format('j F, Y') }}</td></tr>
    <tr><td><span class="label">Gross Return:</span> {{ $cur }} {{ number_format($fd->interest_amount, 0) }}</td></tr>
</table>

<div class="total">TOTAL PAYOUT AT MATURITY: {{ $cur }} {{ number_format($fd->maturity_amount, 0) }}</div>

<div class="note">
    Returns are earned monthly on the {{ $fd->start_date->format('jS') }} of each month
    ({{ $cur }} {{ number_format($fd->interest_amount / max(1, $fd->term_months), 0) }} per month) and paid together with the principal at maturity.
</div>

<table class="sign">
    <tr>
        <td><div class="line">Authorised Signatory</div></td>
        <td><div class="line">Investor</div></td>
    </tr>
</table>
</body>
</html>
