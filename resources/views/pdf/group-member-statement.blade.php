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
    .stats-table td { padding: 8px 12px; border: 1px solid #e5e7eb; text-align: center; }
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
    .badge { display: inline-block; padding: 2px 7px; border-radius: 4px; font-size: 9px; font-weight: bold; text-transform: uppercase; }
    .badge-deposit { background: #d1fae5; color: #065f46; }
    .badge-withdrawal { background: #fee2e2; color: #991b1b; }
    .badge-transfer { background: #fef3c7; color: #92400e; }
    .badge-interest { background: #dbeafe; color: #1e40af; }
    .footer { margin-top: 24px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #9ca3af; }
</style>
</head>
<body>

<div class="header clearfix">
    <div class="header-right">
        <div style="font-size:10px;opacity:.7">Printed: {{ now()->format('d M Y H:i') }}</div>
    </div>
    <h1>@php $_logo = \App\Models\SystemSetting::get('org_logo'); @endphp@if($_logo)<img src="{{ public_path('storage/'.$_logo) }}" style="height:32px;max-width:160px;object-fit:contain;vertical-align:middle">@else{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}@endif</h1>
    <p>Group Member Statement</p>
</div>

<div class="section-title">Member Information</div>
<table class="info-grid">
    <tr>
        <td class="label">Member Name</td>
        <td class="value">{{ $member->name }}</td>
        <td class="label">Group</td>
        <td class="value">{{ $group->name }} ({{ $group->group_number }})</td>
    </tr>
    <tr>
        <td class="label">Role</td>
        <td class="value">{{ $member->is_leader ? 'Group Leader' : 'Member' }}</td>
        <td class="label">Phone</td>
        <td class="value">{{ $member->phone ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Status</td>
        <td class="value">{{ ucfirst($member->status) }}</td>
        <td class="label">Current Balance</td>
        <td class="value" style="color:#0f2444">UGX {{ number_format($member->balance, 0) }}</td>
    </tr>
</table>

@php
    $totalDeposits    = $transactions->where('type', 'deposit')->sum('amount');
    $totalWithdrawals = $transactions->whereIn('type', ['withdrawal', 'transfer_out'])->sum('amount');
    $totalInterest    = $transactions->where('type', 'interest')->sum('amount');
@endphp

<div class="section-title">Summary</div>
<table class="stats-table">
    <tr>
        <td><span class="stat-label">Total Deposits</span><span class="stat-value amount-credit">UGX {{ number_format($totalDeposits, 0) }}</span></td>
        <td><span class="stat-label">Total Withdrawals</span><span class="stat-value amount-debit">UGX {{ number_format($totalWithdrawals, 0) }}</span></td>
        <td><span class="stat-label">Interest Earned</span><span class="stat-value" style="color:#1e40af">UGX {{ number_format($totalInterest, 0) }}</span></td>
        <td><span class="stat-label">Current Balance</span><span class="stat-value">UGX {{ number_format($member->balance, 0) }}</span></td>
    </tr>
</table>

<div class="section-title">Transaction History</div>
<table class="data-table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Notes</th>
            <th class="text-right">Amount</th>
            <th class="text-right">Balance After</th>
        </tr>
    </thead>
    <tbody>
    @forelse($transactions as $tx)
        @php
            $isOut    = in_array($tx->type, ['withdrawal', 'transfer_out']);
            $badgeCls = match($tx->type) {
                'deposit'      => 'badge-deposit',
                'withdrawal'   => 'badge-withdrawal',
                'transfer_out' => 'badge-transfer',
                'interest'     => 'badge-interest',
                default        => '',
            };
            $label = $tx->type === 'transfer_out' ? 'Transfer Out' : ucfirst($tx->type);
        @endphp
        <tr>
            <td>{{ $tx->transaction_date->format('d M Y') }}</td>
            <td><span class="badge {{ $badgeCls }}">{{ $label }}</span></td>
            <td>{{ $tx->notes ?? '—' }}</td>
            <td class="text-right {{ $isOut ? 'amount-debit' : 'amount-credit' }}">
                {{ $isOut ? '-' : '+' }}{{ number_format($tx->amount, 0) }}
            </td>
            <td class="text-right">{{ number_format($tx->balance_after, 0) }}</td>
        </tr>
    @empty
        <tr><td colspan="5" class="text-center" style="padding:16px;color:#9ca3af">No transactions recorded.</td></tr>
    @endforelse
    </tbody>
</table>

<div class="footer">
    Generated by {{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }} &bull; {{ now()->format('d M Y H:i') }}
</div>

</body>
</html>
