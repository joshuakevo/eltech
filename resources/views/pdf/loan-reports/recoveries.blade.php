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
    .summary-row { width:100%; border-collapse:collapse; margin-bottom:10px; }
    .summary-row td { border:1px solid #e5e7eb; padding:5px 8px; text-align:center; }
    .summary-row .lbl { font-size:7px; color:#6b7280; display:block; }
    .summary-row .val { font-size:10px; font-weight:bold; color:#0f2444; }
    table { width:100%; border-collapse:collapse; }
    thead th { background:#0f2444; color:#fff; padding:4px 5px; font-size:7px; text-transform:uppercase; text-align:left; }
    thead th.r { text-align:right; }
    tbody td { padding:4px 5px; border-bottom:1px solid #f3f4f6; font-size:8px; }
    tbody td.r { text-align:right; }
    tbody tr:nth-child(even) td { background:#f9fafb; }
    tfoot td { padding:5px; font-weight:bold; font-size:8px; background:#e5e7eb; border-top:2px solid #0f2444; }
    tfoot td.r { text-align:right; }
    .footer { margin-top:8px; padding-top:5px; border-top:1px solid #e5e7eb; font-size:7px; color:#9ca3af; }
</style>
</head>
<body>
<div class="header clearfix">
    <div class="header-right">
        <div>Generated: {{ now()->format('d M Y H:i') }}</div>
    </div>
    <h1>@php $_logo = \App\Models\SystemSetting::get('org_logo'); @endphp@if($_logo)<img src="{{ public_path($_logo) }}" style="height:32px;max-width:160px;object-fit:contain;vertical-align:middle">@else{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}@endif — Loan Recoveries</h1>
    <p>All active loans, with last collections comment</p>
</div>

<table class="summary-row">
    <tr>
        <td><span class="lbl">Active Loans</span><span class="val">{{ number_format($totalCount) }}</span></td>
        <td><span class="lbl">Principal Balance</span><span class="val" style="color:#d97706">{{ number_format($totalPrincipalBalance, 2) }}</span></td>
        <td><span class="lbl">Interest Balance</span><span class="val" style="color:#d97706">{{ number_format($totalInterestBalance, 2) }}</span></td>
    </tr>
</table>

<table>
    <thead>
        <tr>
            <th>Loan #</th>
            <th>Client</th>
            <th class="r">Principal Balance</th>
            <th class="r">Interest Balance</th>
            <th>RM</th>
            <th>Last Comment</th>
        </tr>
    </thead>
    <tbody>
    @forelse($loans as $loan)
    @php $lastComment = $loan->comments->first(); @endphp
    <tr>
        <td style="font-family:monospace;font-size:7px">{{ $loan->loan_number }}</td>
        <td>{{ $loan->client->name ?? '—' }}</td>
        <td class="r">{{ number_format($loan->outstanding_principal, 2) }}</td>
        <td class="r">{{ number_format($loan->outstanding_interest, 2) }}</td>
        <td style="font-size:7px">{{ $loan->client->relationshipManager->name ?? '—' }}</td>
        <td style="font-size:7px">
            @if($lastComment)
                {{ \Illuminate\Support\Str::limit($lastComment->comment, 60) }}
                <br><span style="color:#9ca3af">{{ $lastComment->created_at->format('d M Y') }}</span>
            @else
                <span style="color:#9ca3af">—</span>
            @endif
        </td>
    </tr>
    @empty
    <tr><td colspan="6" style="text-align:center;color:#9ca3af;padding:10px">No active loans found.</td></tr>
    @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="2">TOTALS ({{ number_format($totalCount) }} loans)</td>
            <td class="r">{{ number_format($totalPrincipalBalance, 2) }}</td>
            <td class="r">{{ number_format($totalInterestBalance, 2) }}</td>
            <td colspan="2"></td>
        </tr>
    </tfoot>
</table>
<div class="footer">Printed by {{ auth()->user()->name ?? 'System' }} &bull; {{ now()->format('d M Y H:i') }}</div>
</body>
</html>
