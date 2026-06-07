<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; background: #fff; }
    .header { background: #0f2444; color: #fff; padding: 18px 24px; margin-bottom: 20px; }
    .header h1 { font-size: 18px; font-weight: bold; margin-bottom: 2px; }
    .header p { font-size: 10px; opacity: 0.7; }
    .header-right { float: right; text-align: right; }
    .clearfix::after { content: ''; display: table; clear: both; }
    .section-title { font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.08em; color: #6b7280; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; margin: 16px 0 8px; }
    .info-grid { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    .info-grid td { padding: 3px 8px 3px 0; font-size: 11px; vertical-align: top; }
    .info-grid td.label { color: #6b7280; width: 38%; }
    .info-grid td.value { font-weight: 600; }
    .stats-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    .stats-table td { padding: 8px 12px; border: 1px solid #e5e7eb; }
    .stats-table .stat-label { color: #6b7280; font-size: 10px; display: block; }
    .stats-table .stat-value { font-size: 13px; font-weight: bold; color: #0f2444; }
    .data-table { width: 100%; border-collapse: collapse; margin-top: 4px; margin-bottom: 12px; }
    .data-table th { background: #0f2444; color: #fff; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.06em; padding: 6px 8px; text-align: left; }
    .data-table td { padding: 5px 8px; border-bottom: 1px solid #f3f4f6; font-size: 10px; }
    .data-table tbody tr:nth-child(even) td { background: #f9fafb; }
    .data-table tbody tr:last-child td { border-bottom: none; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .amount-positive { color: #065f46; font-weight: bold; }
    .amount-negative { color: #991b1b; font-weight: bold; }
    .two-col { width: 100%; border-collapse: collapse; }
    .two-col td { width: 50%; vertical-align: top; padding-right: 20px; }
    .two-col td:last-child { padding-right: 0; }
    .share-header { background: #f0f4f8; border: 1px solid #dce3ea; padding: 8px 12px; margin: 16px 0 0; font-weight: bold; font-size: 11px; }
    .share-header .share-meta { float: right; font-weight: normal; font-size: 10px; color: #6b7280; }
    .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 9px; font-weight: bold; text-transform: uppercase; }
    .badge-paid { background: #d1fae5; color: #065f46; }
    .badge-partial { background: #fef3c7; color: #92400e; }
    .badge-unpaid { background: #fee2e2; color: #991b1b; }
    .badge-liquidated { background: #e5e7eb; color: #374151; }
    .footer { margin-top: 24px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #9ca3af; }
    .no-txn { font-size: 10px; color: #9ca3af; padding: 8px 12px; background: #fafafa; border: 1px solid #f3f4f6; margin-bottom: 12px; }
    .liq-note { font-size: 10px; color: #6b7280; padding: 6px 12px; border-top: 1px solid #e5e7eb; background: #f9fafb; margin-bottom: 12px; }
    .page-break { page-break-before: always; }
</style>
</head>
<body>

<div class="header clearfix">
    <div class="header-right">
        <div style="font-size:10px;opacity:.7">Printed: {{ now()->format('d M Y H:i') }}</div>
    </div>
    @php $_logo = \App\Models\SystemSetting::get('org_logo'); @endphp
    <h1>@if($_logo)<img src="{{ public_path('storage/'.$_logo) }}" style="height:32px;max-width:160px;object-fit:contain;vertical-align:middle">@else{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}@endif</h1>
    <p>Member Share Statement — {{ $client->client_number }}</p>
</div>

<div style="padding: 0 24px 24px;">

{{-- Client info + summary --}}
<table class="two-col">
<tr>
<td>
    <div class="section-title">Member Information</div>
    <table class="info-grid">
        <tr><td class="label">Full Name</td><td class="value">{{ $client->name }}</td></tr>
        <tr><td class="label">Client No.</td><td class="value">{{ $client->client_number }}</td></tr>
        @if($client->phone)
        <tr><td class="label">Phone</td><td class="value">{{ $client->phone }}</td></tr>
        @endif
        @if($client->email)
        <tr><td class="label">Email</td><td class="value">{{ $client->email }}</td></tr>
        @endif
    </table>
</td>
<td>
    <div class="section-title">Portfolio Summary</div>
    <table class="info-grid">
        <tr><td class="label">Active Shares</td><td class="value">{{ $shares->whereNotIn('status', ['liquidated'])->count() }}</td></tr>
        <tr><td class="label">Subscribed Value</td><td class="value">{{ number_format($totals['share_value'], 0) }}</td></tr>
        <tr><td class="label">Amount Paid</td><td class="value amount-positive">{{ number_format($totals['amount_paid'], 0) }}</td></tr>
        <tr><td class="label">Outstanding</td><td class="value amount-negative">{{ number_format($totals['outstanding'], 0) }}</td></tr>
        @if($totals['liquidated'] > 0)
        <tr><td class="label">Total Liquidated</td><td class="value" style="color:#374151">{{ number_format($totals['liquidated'], 0) }}</td></tr>
        @endif
    </table>
</td>
</tr>
</table>

{{-- Per-share sections --}}
@forelse($shares as $share)

<div class="share-header clearfix">
    <span class="share-meta">
        Subscribed: {{ number_format($share->share_value, 0) }}
        &nbsp;|&nbsp;
        Paid: {{ number_format($share->amount_paid, 0) }}
        @if(!$share->isLiquidated())
        &nbsp;|&nbsp;
        Balance: {{ number_format($share->balance, 0) }}
        @endif
    </span>
    {{ $share->share_number }}
    &nbsp;
    <span class="badge badge-{{ $share->status }}">{{ ucfirst($share->status) }}</span>
    @if($share->isLiquidated() && $share->liquidated_at)
    &nbsp;<span style="font-size:9px;font-weight:normal;color:#6b7280">Liquidated {{ $share->liquidated_at->format('d M Y') }}</span>
    @endif
</div>

@if($share->transactions->count())
<table class="data-table">
    <thead>
        <tr>
            <th>#</th>
            <th>Date</th>
            <th>Type</th>
            <th>Description</th>
            <th class="text-right">Amount</th>
            <th class="text-right">Old Value</th>
            <th class="text-right">New Value</th>
            <th>Method</th>
            <th>Reference</th>
        </tr>
    </thead>
    <tbody>
    @foreach($share->transactions as $i => $txn)
    <tr>
        <td>{{ $i + 1 }}</td>
        <td>{{ $txn->transaction_date->format('d M Y') }}</td>
        <td>
            @if($txn->type === 'payment') Payment
            @elseif($txn->type === 'revaluation') Revaluation
            @elseif($txn->type === 'refund') Refund
            @else Liquidation
            @endif
        </td>
        <td>
            @if($txn->type === 'revaluation')
                {{ number_format($txn->old_value, 0) }} → {{ number_format($txn->new_value, 0) }}
            @elseif($txn->type === 'liquidation')
                Payout to member
            @elseif($txn->type === 'refund')
                Share refund
            @else
                Share payment
            @endif
            @if($txn->notes) — {{ $txn->notes }}@endif
        </td>
        <td class="text-right {{ in_array($txn->type, ['payment']) ? 'amount-positive' : (in_array($txn->type, ['liquidation','refund']) ? 'amount-negative' : '') }}">
            {{ number_format($txn->amount, 0) }}
        </td>
        <td class="text-right" style="color:#6b7280">{{ $txn->old_value !== null ? number_format($txn->old_value, 0) : '—' }}</td>
        <td class="text-right" style="color:#6b7280">{{ $txn->new_value !== null ? number_format($txn->new_value, 0) : '—' }}</td>
        <td>{{ $txn->payment_method ? ucfirst($txn->payment_method) : '—' }}</td>
        <td style="font-size:9px;color:#6b7280">{{ $txn->reference ?? '—' }}</td>
    </tr>
    @endforeach
    </tbody>
</table>
@else
<div class="no-txn">No transactions recorded for this share.</div>
@endif

@if($share->isLiquidated() && $share->liquidation_notes)
<div class="liq-note"><strong>Liquidation notes:</strong> {{ $share->liquidation_notes }}</div>
@endif

@empty
<p style="color:#9ca3af;font-size:10px;padding:16px 0;">No shares recorded for this member.</p>
@endforelse

<div class="footer clearfix">
    <div style="float:right">{{ now()->format('d M Y') }}</div>
    <div>This statement is computer generated and does not require a signature. &copy; {{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}</div>
</div>

</div>
</body>
</html>
