<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 0; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1f2937; }

    /* Navy header band: the logo has white lettering, so it needs a dark background */
    .band { background: #0f2444; padding: 26px 46px 22px; }
    .band table { width: 100%; }
    .band img { height: 54px; }
    .band .org { font-size: 20px; font-weight: bold; color: #fff; letter-spacing: .04em; }
    .band .tagline { color: #c9a227; font-size: 8.5px; letter-spacing: .35em; margin-top: 6px; }
    .band .ref { text-align: right; color: #cbd5e1; font-size: 8.5px; line-height: 1.6; }
    .band .ref b { color: #fff; font-size: 10px; letter-spacing: .03em; }
    .gold { height: 4px; background: #c9a227; }

    .page { padding: 30px 46px 0; }
    .title { text-align: center; margin-bottom: 4px; }
    .title h1 { font-size: 19px; letter-spacing: .16em; color: #0f2444; }
    .subtitle { text-align: center; font-size: 9px; color: #6b7280; letter-spacing: .08em; margin-bottom: 22px; }
    .rule { width: 70px; height: 2px; background: #c9a227; margin: 10px auto 8px; }

    .intro { font-size: 10.5px; line-height: 1.6; color: #374151; margin-bottom: 18px; }
    .intro b { color: #0f2444; }

    .section { font-size: 8.5px; font-weight: bold; letter-spacing: .14em; color: #0f2444; text-transform: uppercase;
               border-bottom: 1px solid #e5e7eb; padding-bottom: 5px; margin-bottom: 0; }
    .spec { width: 100%; border-collapse: collapse; }
    .spec td { padding: 8px 10px; border-bottom: 1px solid #f1f3f6; vertical-align: top; }
    .spec tr:nth-child(odd) td { background: #f8fafc; }
    .spec td.l { width: 38%; color: #6b7280; font-size: 9.5px; }
    .spec td.v { font-weight: bold; color: #111827; }

    .payout { margin-top: 20px; border: 1.5px solid #0f2444; }
    .payout table { width: 100%; border-collapse: collapse; }
    .payout td { padding: 12px 16px; }
    .payout .lbl { background: #0f2444; color: #fff; font-size: 9px; letter-spacing: .14em; font-weight: bold; width: 46%; }
    .payout .amt { font-size: 18px; font-weight: bold; color: #0f2444; text-align: right; }
    .breakdown { font-size: 8.5px; color: #6b7280; text-align: right; padding: 6px 2px 0; }

    .note { margin-top: 18px; font-size: 8.5px; color: #6b7280; line-height: 1.6; border-left: 3px solid #c9a227; padding: 6px 10px; background: #fffbeb; }

    .sign { width: 100%; margin-top: 46px; }
    .sign td { width: 50%; padding-right: 30px; font-size: 9px; color: #374151; vertical-align: bottom; }
    .sign .line { border-top: 1px solid #374151; padding-top: 5px; }
    .sign .muted { color: #9ca3af; font-size: 8px; }

    .footer { position: fixed; bottom: 0; left: 0; right: 0; background: #0f2444; color: #cbd5e1; font-size: 8px; padding: 10px 46px; }
    .footer table { width: 100%; }
    .footer .gold-text { color: #c9a227; letter-spacing: .25em; }
</style>
</head>
<body>
@php
    $fd = $fixedDeposit;
    $p = $fd->product;
    $logo = \App\Models\SystemSetting::get('org_logo');
    $org = \App\Models\SystemSetting::get('org_name', 'ElTech Finance');
    $days = $fd->start_date->diffInDays($fd->maturity_date);
    $cur = \App\Models\SystemSetting::get('currency', 'UGX');
    $money = fn ($v) => $cur . ' ' . number_format($v, fmod((float) $v, 1) ? 2 : 0);
    $reference = $fd->certificate_reference ?: $fd->deposit_number;
@endphp

<div class="band">
    <table><tr>
        <td>
            @if($logo && file_exists(public_path($logo)))
                <img src="{{ public_path($logo) }}">
            @else
                <div class="org">{{ $org }}</div>
            @endif
            <div class="tagline">INVEST. PROFIT. PROSPER</div>
        </td>
        <td class="ref">Reference<br><b>{{ $reference }}</b><br>Issued {{ $fd->start_date->format('j F Y') }}</td>
    </tr></table>
</div>
<div class="gold"></div>

<div class="page">
    <div class="title"><h1>INVESTMENT PLACEMENT CERTIFICATE</h1></div>
    <div class="rule"></div>
    <div class="subtitle">{{ strtoupper($fd->asset_class ?: ($p->asset_class ?: $p->name)) }}</div>

    <div class="intro">
        This certifies that <b>{{ $fd->client->name ?? '—' }}</b> has placed <b>{{ $money($fd->principal) }}</b>
        with {{ $org }} on the terms set out below.
    </div>

    <div class="section">Investment specifications</div>
    <table class="spec">
        <tr><td class="l">Investor name</td><td class="v">{{ $fd->client->name ?? '—' }}</td></tr>
        <tr><td class="l">Asset class</td><td class="v">{{ $fd->asset_class ?: ($p->asset_class ?: $p->name) }}</td></tr>
        <tr><td class="l">Principal investment capital</td><td class="v">{{ $money($fd->principal) }}</td></tr>
        <tr><td class="l">{{ $p->isMonthlyRate() ? 'Monthly yield rate' : 'Yield rate' }}</td><td class="v">{{ $p->rateLabel($fd->interest_rate) }}{{ $fd->isCompounding() ? ', compounded monthly' : '' }}</td></tr>
        <tr><td class="l">Tenure</td><td class="v">{{ $fd->term_months }} month{{ $fd->term_months == 1 ? '' : 's' }} ({{ $days }} days)</td></tr>
        <tr><td class="l">Value date</td><td class="v">{{ $fd->start_date->format('j F Y') }}</td></tr>
        <tr><td class="l">Maturity date</td><td class="v">{{ $fd->maturity_date->format('j F Y') }}</td></tr>
        <tr><td class="l">Gross return</td><td class="v">{{ $money($fd->interest_amount) }}</td></tr>
    </table>

    <div class="payout"><table><tr>
        <td class="lbl">TOTAL PAYOUT AT MATURITY</td>
        <td class="amt">{{ $money($fd->maturity_amount) }}</td>
    </tr></table></div>
    <div class="breakdown">Principal {{ $money($fd->principal) }} + gross return {{ $money($fd->interest_amount) }}</div>

    <div class="note">
        Returns are earned on the {{ $fd->start_date->format('jS') }} of each month
        @if($fd->isCompounding()) and compounded — each month's return also earns in the following months —
        @else ({{ $money(round($fd->interest_amount / max(1, $fd->term_months), 2)) }} per month) @endif
        and are paid together with the principal on the maturity date.
    </div>

    <table class="sign">
        <tr>
            <td><div class="line">For and on behalf of {{ $org }}<br><span class="muted">Authorised signatory &amp; stamp</span></div></td>
            <td><div class="line">{{ $fd->client->name ?? 'Investor' }}<br><span class="muted">Investor signature &amp; date</span></div></td>
        </tr>
    </table>
</div>

<div class="footer"><table><tr>
    <td>{{ $org }} · Certificate {{ $reference }} · {{ $fd->deposit_number }}</td>
    <td style="text-align:right" class="gold-text">INVEST. PROFIT. PROSPER</td>
</tr></table></div>
</body>
</html>
