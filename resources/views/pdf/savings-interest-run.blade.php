<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; }
    h2 { margin: 0 0 2px; font-size: 14px; color: #0f2444; }
    .muted { color: #6b7280; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th { background: #0f2444; color: #fff; text-align: left; padding: 4px; font-size: 8px; text-transform: uppercase; }
    td { padding: 3px 4px; border-bottom: 1px solid #e5e7eb; }
    .r { text-align: right; }
    tfoot td { font-weight: bold; background: #f3f4f6; }
</style>
</head>
<body>
    @php $dp = (int) \App\Models\SystemSetting::get('decimal_places', 2); @endphp
    <h2>{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }} — {{ $run->product->name }} interest</h2>
    <div class="muted">Interest up to {{ $run->period_end->format('d M Y') }} · Run #{{ $run->id }} by {{ $run->createdBy->name ?? '—' }} on {{ $run->created_at->format('d M Y H:i') }} · {{ $run->accounts_count }} accounts · Total {{ number_format($run->total_interest, $dp) }}</div>
    <table>
        <thead><tr><th>#</th><th>Member No.</th><th>Member</th><th>Account</th><th>Period</th><th class="r">Days</th><th class="r">Avg balance</th><th class="r">Closing balance</th><th>Rate</th><th class="r">Interest</th></tr></thead>
        <tbody>
        @foreach($lines as $i => $l)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $l->client->client_number ?? '' }}</td>
                <td>{{ $l->client->name ?? '' }}</td>
                <td>{{ $l->account->account_number ?? '' }}</td>
                <td>{{ $l->period_start->format('d M Y') }} – {{ $l->period_end->format('d M Y') }}</td>
                <td class="r">{{ $l->days }}</td>
                <td class="r">{{ number_format($l->average_balance, $dp) }}</td>
                <td class="r">{{ number_format($l->closing_balance, $dp) }}</td>
                <td>{{ $l->rate_label }}</td>
                <td class="r">{{ number_format($l->interest, $dp) }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot><tr><td colspan="9">Total</td><td class="r">{{ number_format($lines->sum('interest'), $dp) }}</td></tr></tfoot>
    </table>
    <p class="muted">Generated {{ now()->format('d M Y H:i') }}</p>
</body>
</html>
