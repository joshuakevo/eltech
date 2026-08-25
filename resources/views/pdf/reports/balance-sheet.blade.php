<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size:9px; color:#1a1a1a; }
    .header { background:#0f2444; color:#fff; padding:12px 18px; margin-bottom:12px; }
    .header h1 { font-size:14px; font-weight:bold; }
    .header p  { font-size:8px; opacity:0.7; margin-top:2px; }
    .header-right { float:right; text-align:right; font-size:8px; }
    .clearfix::after { content:''; display:table; clear:both; }
    .col-half { width:48%; float:left; }
    .col-half + .col-half { margin-left:4%; }
    table { width:100%; border-collapse:collapse; margin-bottom:10px; }
    .section-head { background:#0f2444; color:#fff; padding:5px 6px; font-size:9px; font-weight:bold; }
    tbody td { padding:5px 6px; border-bottom:1px solid #f3f4f6; font-size:9px; }
    tbody td.r { text-align:right; }
    tbody tr:nth-child(even) td { background:#f9fafb; }
    tfoot td { padding:6px; font-weight:bold; font-size:9px; background:#e5e7eb; border-top:2px solid #0f2444; }
    tfoot td.r { text-align:right; }
    .text-primary { color:#2563eb; }
    .text-danger  { color:#dc2626; }
    .text-success { color:#059669; }
    .balance-check { text-align:center; padding:8px; border:2px solid #0f2444; margin-top:10px; font-size:9px; }
    .footer { margin-top:10px; padding-top:6px; border-top:1px solid #e5e7eb; font-size:7px; color:#9ca3af; clear:both; }
</style>
</head>
<body>
<div class="header clearfix">
    <div class="header-right">
        <div>Generated: {{ now()->format('d M Y H:i') }}</div>
        <div>As of {{ $asOf }}</div>
    </div>
    <h1>@php $_logo = \App\Models\SystemSetting::get('org_logo'); @endphp@if($_logo)<img src="{{ public_path($_logo) }}" style="height:32px;max-width:160px;object-fit:contain;vertical-align:middle">@else{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}@endif — Balance Sheet</h1>
    <p>Financial position as of the selected date{{ $segment ? ' — Segment: ' . $segment->name : '' }}</p>
</div>

<div class="clearfix">
<div class="col-half">
    <table>
        <thead><tr><th colspan="2" class="section-head">Assets</th></tr></thead>
        <tbody>
        @foreach($data['asset']['rows'] as $row)
        <tr>
            <td>{{ $row['account']->account_code }} — {{ $row['account']->account_name }}</td>
            <td class="r">{{ number_format($row['balance'], 2) }}</td>
        </tr>
        @endforeach
        </tbody>
        <tfoot><tr><td>Total Assets</td><td class="r text-primary">{{ number_format($data['asset']['total'], 2) }}</td></tr></tfoot>
    </table>
</div>
<div class="col-half">
    <table>
        <thead><tr><th colspan="2" class="section-head">Liabilities</th></tr></thead>
        <tbody>
        @foreach($data['liability']['rows'] as $row)
        <tr>
            <td>{{ $row['account']->account_code }} — {{ $row['account']->account_name }}</td>
            <td class="r">{{ number_format($row['balance'], 2) }}</td>
        </tr>
        @endforeach
        </tbody>
        <tfoot><tr><td>Total Liabilities</td><td class="r text-danger">{{ number_format($data['liability']['total'], 2) }}</td></tr></tfoot>
    </table>

    <table>
        <thead><tr><th colspan="2" class="section-head">Equity</th></tr></thead>
        <tbody>
        @foreach($data['equity']['rows'] as $row)
        <tr>
            <td>{{ $row['account']->account_code }} — {{ $row['account']->account_name }}</td>
            <td class="r">{{ number_format($row['balance'], 2) }}</td>
        </tr>
        @endforeach
        </tbody>
        <tfoot><tr><td>Total Equity</td><td class="r text-success">{{ number_format($data['equity']['total'], 2) }}</td></tr></tfoot>
    </table>

    <div class="balance-check">
        <strong>Liabilities + Equity = {{ number_format($data['liability']['total'] + $data['equity']['total'], 2) }}</strong><br>
        @if($segment)
            <span style="color:#6b7280">Balance check skipped for segment view — cash/institutional accounts aren't attributable to a segment.</span>
        @elseif(abs($data['asset']['total'] - ($data['liability']['total'] + $data['equity']['total'])) < 0.01)
            <span style="color:#059669">✓ Balance sheet is balanced.</span>
        @else
            <span style="color:#dc2626">✗ Balance sheet is NOT balanced.</span>
        @endif
    </div>
</div>
</div>

<div class="footer">Printed by {{ auth()->user()->name ?? 'System' }} &bull; {{ now()->format('d M Y H:i') }}</div>
</body>
</html>
