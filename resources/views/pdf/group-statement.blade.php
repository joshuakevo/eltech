<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a1a1a; }
    .header { background: #0f2444; color: #fff; padding: 16px 22px; }
    .header h1 { font-size: 17px; font-weight: bold; }
    .header p { font-size: 10px; opacity: .75; margin-top: 2px; }
    .header-right { float: right; text-align: right; font-size: 9px; opacity: .8; }
    .clearfix::after { content: ''; display: table; clear: both; }
    .wrap { padding: 14px 22px; }
    .section-title { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: .08em; color: #6b7280; border-bottom: 1px solid #e5e7eb; padding-bottom: 3px; margin: 14px 0 6px; }
    .info td { padding: 2px 10px 2px 0; }
    .info .l { color: #6b7280; }
    .info .v { font-weight: bold; }
    .stats { width: 100%; border-collapse: collapse; margin-top: 4px; }
    .stats td { border: 1px solid #e5e7eb; padding: 7px 8px; text-align: center; width: 20%; }
    .stats .sl { display: block; color: #6b7280; font-size: 8px; text-transform: uppercase; letter-spacing: .05em; }
    .stats .sv { font-size: 12px; font-weight: bold; color: #0f2444; }
    .data { width: 100%; border-collapse: collapse; }
    .data th { background: #0f2444; color: #fff; font-size: 8px; text-transform: uppercase; letter-spacing: .05em; padding: 5px 6px; text-align: left; }
    .data td { padding: 4px 6px; border-bottom: 1px solid #f1f3f6; font-size: 9px; }
    .data tbody tr:nth-child(even) td { background: #f9fafb; }
    .data tfoot td { font-weight: bold; border-top: 2px solid #0f2444; padding: 5px 6px; background: #f1f5fb; }
    .r { text-align: right; }
    .in { color: #065f46; }
    .out { color: #991b1b; }
    .muted { color: #9ca3af; }
    .badge { padding: 1px 5px; border-radius: 3px; font-size: 8px; font-weight: bold; text-transform: uppercase; }
    .b-deposit { background: #d1fae5; color: #065f46; } .b-withdrawal, .b-transfer_out { background: #fee2e2; color: #991b1b; }
    .b-interest { background: #dbeafe; color: #1e40af; } .b-other { background: #f3f4f6; color: #374151; }
    .footer { margin-top: 18px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 8px; color: #9ca3af; }
</style>
</head>
<body>
@php
    $n = fn ($v) => number_format($v, 0);
    $fd = fn ($d) => \Carbon\Carbon::parse($d)->format('d M Y');
@endphp
<div class="header clearfix">
    <div class="header-right">Printed {{ now()->format('d M Y H:i') }}<br>Period {{ $fd($s['from']) }} – {{ $fd($s['to']) }}</div>
    <h1>@php $_logo = \App\Models\SystemSetting::get('org_logo'); @endphp@if($_logo)<img src="{{ public_path($_logo) }}" style="height:30px;max-width:160px;object-fit:contain;vertical-align:middle">@else{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}@endif</h1>
    <p>Group Statement</p>
</div>

<div class="wrap">
    <table class="info">
        <tr><td class="l">Group</td><td class="v">{{ $group->name }}</td><td class="l" style="padding-left:24px">Group #</td><td class="v">{{ $group->group_number }}</td></tr>
        <tr><td class="l">Type</td><td class="v">{{ $group->isPooled() ? 'Pooled group' : 'Savings group' }}</td><td class="l" style="padding-left:24px">Interest rate</td><td class="v">{{ round((float) $group->monthly_interest_rate) }}% per month</td></tr>
        <tr><td class="l">Members</td><td class="v">{{ $s['members']->count() }}</td><td class="l" style="padding-left:24px">Registered</td><td class="v">{{ optional($group->registration_date)->format('d M Y') }}</td></tr>
    </table>

    <div class="section-title">Summary — {{ $fd($s['from']) }} to {{ $fd($s['to']) }}</div>
    <table class="stats"><tr>
        <td><span class="sl">Opening balance</span><span class="sv">{{ $n($s['opening']) }}</span></td>
        <td><span class="sl">Deposits</span><span class="sv in">{{ $n($s['deposits']) }}</span></td>
        <td><span class="sl">Interest</span><span class="sv" style="color:#1e40af">{{ $n($s['interest']) }}</span></td>
        <td><span class="sl">Withdrawals</span><span class="sv out">{{ $n($s['withdrawals']) }}</span></td>
        <td><span class="sl">Closing balance</span><span class="sv">{{ $n($s['closing']) }}</span></td>
    </tr></table>

    <div class="section-title">Members</div>
    <table class="data">
        <thead><tr><th>#</th><th>Member</th><th class="r">Opening</th><th class="r">Deposits</th><th class="r">Interest</th><th class="r">Withdrawals</th><th class="r">Closing</th></tr></thead>
        <tbody>
        @foreach($s['members'] as $i => $m)
            <tr>
                <td class="muted">{{ $i + 1 }}</td>
                <td>{{ $m['name'] }}@if($m['leader']) <span class="badge b-interest">Leader</span>@endif @if($m['status'] !== 'active')<span class="muted">({{ $m['status'] }})</span>@endif</td>
                <td class="r">{{ $n($m['opening']) }}</td>
                <td class="r in">{{ $m['deposits'] ? $n($m['deposits']) : '—' }}</td>
                <td class="r">{{ $m['interest'] ? $n($m['interest']) : '—' }}</td>
                <td class="r out">{{ $m['withdrawals'] ? $n($m['withdrawals']) : '—' }}</td>
                <td class="r"><b>{{ $n($m['closing']) }}</b></td>
            </tr>
        @endforeach
        </tbody>
        <tfoot><tr>
            <td></td><td>Total{{ $group->isPooled() ? ' contributed' : '' }}</td>
            <td class="r">{{ $n($s['members']->sum('opening')) }}</td><td class="r">{{ $n($s['members']->sum('deposits')) }}</td>
            <td class="r">{{ $n($s['members']->sum('interest')) }}</td><td class="r">{{ $n($s['members']->sum('withdrawals')) }}</td>
            <td class="r">{{ $n($s['members']->sum('closing')) }}</td>
        </tr></tfoot>
    </table>
    @if($group->isPooled())
        <div class="muted" style="margin-top:4px">Pooled group: member figures are contributions; the summary above is the group pool (pool payouts reduce the pool, not members).</div>
    @endif

    <div class="section-title">Transactions ({{ $s['transactions']->count() }})</div>
    <table class="data">
        <thead><tr><th>Date</th><th>Member</th><th>Type</th><th>Reference / notes</th><th class="r">Money in</th><th class="r">Money out</th></tr></thead>
        <tbody>
        @forelse($s['transactions'] as $t)
            <tr>
                <td style="white-space:nowrap">{{ $fd($t['date']) }}</td>
                <td>{{ $t['member'] }}</td>
                <td><span class="badge b-{{ in_array($t['type'], ['deposit','withdrawal','interest','transfer_out']) ? $t['type'] : 'other' }}">{{ str_replace('_', ' ', $t['type']) }}</span></td>
                <td>{{ $t['reference'] }}@if($t['reference'] && $t['notes']) · @endif<span class="muted">{{ \Illuminate\Support\Str::limit($t['notes'], 60) }}</span></td>
                <td class="r in">{{ $t['in'] ? $n($t['in']) : '' }}</td>
                <td class="r out">{{ $t['out'] ? $n($t['out']) : '' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted" style="text-align:center;padding:10px">No transactions in this period.</td></tr>
        @endforelse
        </tbody>
        @if($s['transactions']->count())
        <tfoot><tr><td colspan="4">Totals</td><td class="r">{{ $n($s['transactions']->sum('in')) }}</td><td class="r">{{ $n($s['transactions']->sum('out')) }}</td></tr></tfoot>
        @endif
    </table>

    <div class="footer">{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }} · Group statement for {{ $group->name }} · Opening {{ $n($s['opening']) }} + in {{ $n($s['deposits'] + $s['interest']) }} − out {{ $n($s['withdrawals']) }} = closing {{ $n($s['closing']) }}</div>
</div>
</body>
</html>
