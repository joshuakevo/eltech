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
    .data-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
    .data-table th { background: #0f2444; color: #fff; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.06em; padding: 6px 8px; text-align: left; }
    .data-table td { padding: 5px 8px; border-bottom: 1px solid #f3f4f6; font-size: 10px; }
    .data-table tbody tr:nth-child(even) td { background: #f9fafb; }
    .data-table tbody tr:last-child td { border-bottom: none; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .amount-credit { color: #065f46; font-weight: bold; }
    .amount-debit { color: #991b1b; font-weight: bold; }
    .footer { margin-top: 24px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #9ca3af; }
    .two-col { width: 100%; border-collapse: collapse; }
    .two-col td { width: 50%; vertical-align: top; padding-right: 20px; }
    .two-col td:last-child { padding-right: 0; }
    .date-filter-bar { background: #f0f2f5; border: 1px solid #e5e7eb; padding: 6px 12px; margin-bottom: 12px; font-size: 10px; color: #374151; }
</style>
</head>
<body>

<div class="header clearfix">
    <div class="header-right">
        <div style="font-size:10px;opacity:.7">Printed: {{ now()->format('d M Y H:i') }}</div>
        @if($fromDate || $toDate)
        <div style="font-size:10px;opacity:.7">
            Period: {{ $fromDate ? \Carbon\Carbon::parse($fromDate)->format('d M Y') : 'Beginning' }}
            — {{ $toDate ? \Carbon\Carbon::parse($toDate)->format('d M Y') : 'Today' }}
        </div>
        @endif
    </div>
    <h1>@php $_logo = \App\Models\SystemSetting::get('org_logo'); @endphp@if($_logo)<img src="{{ public_path($_logo) }}" style="height:32px;max-width:160px;object-fit:contain;vertical-align:middle">@else{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}@endif</h1>
    <p>Savings Account Statement — {{ $account->account_number }}</p>
</div>

<div style="padding: 0 24px 24px;">

<table class="two-col">
<tr>
<td>
    <div class="section-title">Client Information</div>
    <table class="info-grid">
        <tr><td class="label">Full Name</td><td class="value">{{ $account->client->name }}</td></tr>
        <tr><td class="label">Client No.</td><td class="value">{{ $account->client->client_number }}</td></tr>
        @if($account->client->phone)
        <tr><td class="label">Phone</td><td class="value">{{ $account->client->phone }}</td></tr>
        @endif
        @if($account->client->email)
        <tr><td class="label">Email</td><td class="value">{{ $account->client->email }}</td></tr>
        @endif
    </table>
</td>
<td>
    <div class="section-title">Account Information</div>
    <table class="info-grid">
        <tr><td class="label">Account Number</td><td class="value">{{ $account->account_number }}</td></tr>
        <tr><td class="label">Product</td><td class="value">{{ $account->product->name }}</td></tr>
        <tr><td class="label">Date Opened</td><td class="value">{{ $account->opened_date->format('d M Y') }}</td></tr>
        <tr><td class="label">Status</td><td class="value">{{ strtoupper($account->status) }}</td></tr>
        @if($account->product->interest_method === 'tiered')
        <tr><td class="label">Balance (excl. Interest)</td><td class="value" style="color:#065f46;font-size:13px">{{ number_format($account->balance, $dp) }}</td></tr>
        <tr><td class="label">Accrued Interest (pending)</td><td class="value" style="color:#2563eb">{{ number_format($projectedInterest, $dp) }}</td></tr>
        <tr><td class="label">Balance (incl. Interest)</td><td class="value" style="color:#065f46;font-size:13px">{{ number_format($account->balance + $projectedInterest, $dp) }}</td></tr>
        @else
        <tr><td class="label">Current Balance</td><td class="value" style="color:#065f46;font-size:13px">{{ number_format($account->balance, $dp) }}</td></tr>
        @endif
    </table>
</td>
</tr>
</table>

{{-- Summary --}}
<div class="section-title">Period Summary</div>
@php
    $totalDeposits    = $transactions->whereIn('transaction_type', ['deposit', 'interest'])->sum('amount');
    $totalWithdrawals = $transactions->whereIn('transaction_type', ['withdrawal', 'fee', 'loan_repayment'])->sum('amount');
    $transfersIn      = $transactions->where('transaction_type', 'transfer')->where('amount', '>', 0)->sum('amount');
    $transfersOut     = $transactions->where('transaction_type', 'transfer')->where('amount', '<', 0)->sum(fn($t) => abs($t->amount));
@endphp
<table class="stats-table">
<tr>
    <td><span class="stat-label">Total Deposits</span><span class="stat-value amount-credit">{{ number_format($totalDeposits, $dp) }}</span></td>
    <td><span class="stat-label">Total Withdrawals</span><span class="stat-value amount-debit">{{ number_format($totalWithdrawals, $dp) }}</span></td>
    <td><span class="stat-label">Transactions</span><span class="stat-value">{{ $transactions->count() }}</span></td>
    <td><span class="stat-label">Closing Balance{{ $account->product->interest_method === 'tiered' ? ' (excl. Interest)' : '' }}</span><span class="stat-value" style="color:#0f2444">{{ number_format($account->balance, $dp) }}</span></td>
    @if($account->product->interest_method === 'tiered')
    <td><span class="stat-label">Accrued Interest</span><span class="stat-value" style="color:#2563eb">{{ number_format($projectedInterest, $dp) }}</span></td>
    <td><span class="stat-label">Closing Bal. incl. Interest</span><span class="stat-value" style="color:#2563eb">{{ number_format($account->balance + $projectedInterest, $dp) }}</span></td>
    @endif
</tr>
</table>

{{-- Transactions --}}
<div class="section-title">Transaction History</div>
@if($transactions->isEmpty())
<p style="color:#9ca3af;font-size:10px;padding:8px 0;">No transactions in this period.</p>
@else
<table class="data-table">
    <thead>
        <tr>
            <th>#</th>
            <th>Date</th>
            <th>Type</th>
            <th>Description</th>
            <th class="text-right">Debit (−)</th>
            <th class="text-right">Credit (+)</th>
            <th class="text-right">Balance</th>
        </tr>
    </thead>
    <tbody>
    @foreach($transactions as $i => $t)
    @php
        $isCredit = in_array($t->transaction_type, ['deposit', 'interest']) ||
                    ($t->transaction_type === 'transfer' && $t->balance_after > $t->balance_before);
    @endphp
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $t->transaction_date->format('d M Y') }}</td>
            <td>{{ ucfirst(str_replace('_', ' ', $t->transaction_type)) }}</td>
            <td>{{ $t->description ?? '—' }}</td>
            <td class="text-right amount-debit">
                @if(!$isCredit) {{ number_format($t->amount, $dp) }} @else — @endif
            </td>
            <td class="text-right amount-credit">
                @if($isCredit) {{ number_format($t->amount, $dp) }} @else — @endif
            </td>
            <td class="text-right" style="font-weight:bold">{{ number_format($t->balance_after, $dp) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif

<div class="footer clearfix">
    <div style="float:right">Page 1</div>
    <div>This statement is computer generated. @php $_logo = \App\Models\SystemSetting::get('org_logo'); @endphp@if($_logo)<img src="{{ public_path($_logo) }}" style="height:32px;max-width:160px;object-fit:contain;vertical-align:middle">@else{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}@endif</div>
</div>

</div>
</body>
</html>
