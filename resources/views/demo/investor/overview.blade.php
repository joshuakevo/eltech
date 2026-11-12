@extends('demo.investor.layout')
@section('title', 'Dashboard')
@section('content')
@php
    $totalSavings = $savings->balance;
    $totalFdValue = $investments->sum('principal');
    $net = $totalSavings + $totalFdValue;
@endphp

<div class="mb-4">
    <h5 class="fw-bold mb-0">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, Demo</h5>
    <div class="text-muted small">{{ now()->format('l, d F Y') }}</div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="stat-card h-100"><div class="d-flex align-items-start justify-content-between"><div>
        <div class="sc-label">Total Savings</div>
        <div class="sc-value text-success">{{ number_format($totalSavings, 0) }}</div>
        <div class="sc-sub">1 account</div>
    </div><div class="sc-icon bg-success bg-opacity-10 text-success"><i class="bi bi-piggy-bank-fill"></i></div></div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card h-100"><div class="d-flex align-items-start justify-content-between"><div>
        <div class="sc-label">Investments</div>
        <div class="sc-value text-warning">{{ number_format($totalFdValue, 0) }}</div>
        <div class="sc-sub">{{ $investments->count() }} running</div>
    </div><div class="sc-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-safe-fill"></i></div></div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card h-100"><div class="d-flex align-items-start justify-content-between"><div>
        @php $earnedAll = $investments->sum(fn ($fd) => collect($fd->monthlySchedule())->where('earned', true)->last()['cumulative'] ?? 0); @endphp
        <div class="sc-label">Returns earned</div>
        <div class="sc-value text-success">{{ number_format($earnedAll, 0) }}</div>
        <div class="sc-sub">paid with principal at maturity</div>
    </div><div class="sc-icon bg-success bg-opacity-10 text-success"><i class="bi bi-graph-up-arrow"></i></div></div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card h-100"><div class="d-flex align-items-start justify-content-between"><div>
        <div class="sc-label">Net Position</div>
        <div class="sc-value text-primary">{{ number_format($net, 0) }}</div>
        <div class="sc-sub">Savings + investments</div>
    </div><div class="sc-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-wallet2"></i></div></div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-7">
        <div class="card h-100">
            <div class="card-header py-3 d-flex align-items-center gap-2"><i class="bi bi-safe-fill text-warning"></i><span>My Investments</span></div>
            <div class="card-body">
                @foreach($investments as $fd)
                @php $earned = collect($fd->monthlySchedule())->where('earned', true)->last()['cumulative'] ?? 0; @endphp
                <a href="{{ route('demo.investor.investment', $fd->id) }}" class="d-flex justify-content-between align-items-center small text-decoration-none text-reset {{ !$loop->last ? 'mb-2 pb-2 border-bottom' : '' }}">
                    <div>
                        <div class="fw-semibold">{{ $fd->product->name }}</div>
                        <div class="text-muted" style="font-size:.72rem">{{ $fd->certificate_reference ?: $fd->deposit_number }} · earned so far <b class="text-success">{{ number_format($earned, 0) }}</b></div>
                        <div class="text-muted" style="font-size:.72rem">Matures {{ $fd->maturity_date->format('d M Y') }}</div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold text-warning">{{ number_format($fd->principal, 0) }}</div>
                        <div class="text-muted" style="font-size:.7rem">{{ $fd->product->rateLabel($fd->interest_rate) }}</div>
                    </div>
                </a>
                @endforeach
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card h-100">
            <div class="card-header py-3 d-flex align-items-center gap-2"><i class="bi bi-calendar3 text-primary"></i><span>This Month's Activity</span></div>
            <div class="card-body">
                @php $maxFlow = max($depositsThisMonth, $withdrawalsThisMonth, 1); $netFlow = $depositsThisMonth - $withdrawalsThisMonth; @endphp
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1"><span class="small text-muted">Deposits</span><span class="fw-bold text-success">+{{ number_format($depositsThisMonth, 0) }}</span></div>
                    <div class="progress" style="height:6px"><div class="progress-bar bg-success" style="width:{{ round($depositsThisMonth / $maxFlow * 100) }}%"></div></div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1"><span class="small text-muted">Withdrawals</span><span class="fw-bold text-danger">-{{ number_format($withdrawalsThisMonth, 0) }}</span></div>
                    <div class="progress" style="height:6px"><div class="progress-bar bg-danger" style="width:{{ round($withdrawalsThisMonth / $maxFlow * 100) }}%"></div></div>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between"><span class="small fw-semibold">Net Cash Flow</span>
                    <span class="fw-bold {{ $netFlow >= 0 ? 'text-success' : 'text-danger' }}">{{ $netFlow >= 0 ? '+' : '' }}{{ number_format($netFlow, 0) }}</span></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-5">
        <div class="card h-100">
            <div class="card-header py-3 d-flex align-items-center justify-content-between">
                <span class="d-flex align-items-center gap-2"><i class="bi bi-piggy-bank-fill text-success"></i> Savings Accounts</span>
                <a href="{{ route('demo.investor.statements') }}" class="btn btn-sm btn-outline-primary" style="font-size:.75rem">View Statements</a>
            </div>
            <div class="card-body p-0">
                <a href="{{ route('demo.investor.savings') }}" class="d-flex align-items-center justify-content-between px-3 py-2 text-decoration-none text-reset">
                    <div><div class="fw-semibold small">{{ $savings->account_number }}</div><div class="text-muted" style="font-size:.72rem">{{ $savings->product->name }}</div></div>
                    <div class="text-end"><div class="fw-bold text-success small">{{ number_format($savings->balance, 0) }}</div><span class="badge bg-success" style="font-size:.6rem">Active</span></div>
                </a>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card h-100">
            <div class="card-header py-3 d-flex align-items-center gap-2"><i class="bi bi-bar-chart-fill text-primary"></i> 6-Month Savings Trend</div>
            <div class="card-body"><div style="position:relative;height:200px"><canvas id="trendChart"></canvas></div></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('trendChart'), {
    type: 'bar',
    data: { labels: {!! json_encode(array_column($trend, 'label')) !!},
        datasets: [{ label: 'Net Savings', data: {!! json_encode(array_column($trend, 'amount')) !!},
            backgroundColor: ctx => ctx.dataset.data[ctx.dataIndex] >= 0 ? 'rgba(34,197,94,.75)' : 'rgba(239,68,68,.7)', borderRadius: 6 }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
        scales: { y: { ticks: { callback: v => v.toLocaleString() }, grid: { color: '#f3f4f6' } }, x: { grid: { display: false } } } }
});
</script>
@endpush
