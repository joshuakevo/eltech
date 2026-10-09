@extends('layouts.app')

@section('title', 'Dashboard')

@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
@php
    $loanBook  = $standardPrincipal + $lockedUp['principal'];
    $parColor  = $par30 <= 5 ? 'success' : ($par30 <= 15 ? 'warning' : 'danger');
    $ltdColor  = $loanToDeposit <= 80 ? 'success' : ($loanToDeposit <= 100 ? 'warning' : 'danger');
    $netFlow   = $month['deposits'] - $month['withdrawals'];
@endphp
<style>
    .kpi { background:#fff; border:1px solid #e8ecf1; border-radius:.75rem; padding:1rem 1.1rem; height:100%; position:relative; overflow:hidden; }
    .kpi::before { content:''; position:absolute; left:0; top:0; bottom:0; width:4px; background:var(--accent); }
    .kpi .kpi-label { font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; color:#6b7280; font-weight:600; }
    .kpi .kpi-value { font-size:1.45rem; font-weight:700; color:#0f2444; line-height:1.2; margin-top:.2rem; }
    .kpi .kpi-sub { font-size:.75rem; color:#6b7280; margin-top:.15rem; }
    .kpi .kpi-icon { position:absolute; right:1rem; top:1rem; font-size:1.4rem; color:var(--accent); opacity:.85; }
    .panel-title { font-size:.8rem; text-transform:uppercase; letter-spacing:.05em; font-weight:700; color:#0f2444; }
    .mix-row { display:flex; align-items:center; gap:.65rem; padding:.55rem 0; border-bottom:1px solid #f1f3f6; text-decoration:none; color:inherit; }
    .mix-row:last-child { border-bottom:0; }
    .mix-row:hover { background:#fafbfc; }
    .mix-dot { width:.7rem; height:.7rem; border-radius:50%; flex-shrink:0; }
    .mix-bar { height:4px; background:#eef1f5; border-radius:2px; overflow:hidden; margin-top:.3rem; }
    .mix-bar > span { display:block; height:100%; }
    .loan-block { border:1px solid #e8ecf1; border-radius:.6rem; padding:.85rem 1rem; }
    .loan-block.std { border-left:4px solid #2563eb; }
    .loan-block.lu { border-left:4px solid #b91c1c; }
    .mini-stat { background:#f8fafc; border-radius:.5rem; padding:.6rem .75rem; height:100%; }
    .mini-stat .l { font-size:.7rem; color:#6b7280; text-transform:uppercase; letter-spacing:.03em; }
    .mini-stat .v { font-weight:700; color:#0f2444; }
</style>

<div class="d-flex justify-content-between align-items-end mb-3">
    <div>
        <h4 class="fw-bold mb-0">Dashboard</h4>
        <div class="text-muted small">Position as at {{ now()->format('d M Y') }}</div>
    </div>
</div>

{{-- ── Headline ─────────────────────────────────────────────────────────── --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3">
        <div class="kpi" style="--accent:#0d9488">
            <i class="bi bi-wallet2 kpi-icon"></i>
            <div class="kpi-label">Member Deposits</div>
            <div class="kpi-value">{{ number_format($totalDeposits, 0) }}</div>
            <div class="kpi-sub">Savings, fixed &amp; group deposits</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi" style="--accent:#2563eb">
            <i class="bi bi-bank kpi-icon"></i>
            <div class="kpi-label">Loan Book (Principal)</div>
            <div class="kpi-value">{{ number_format($loanBook, 0) }}</div>
            <div class="kpi-sub">Standard {{ number_format($standardPrincipal, 0) }} · Locked-Up {{ number_format($lockedUp['principal'], 0) }}</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi" style="--accent:var(--bs-{{ $ltdColor }})">
            <i class="bi bi-speedometer2 kpi-icon"></i>
            <div class="kpi-label">Loans to Deposits</div>
            <div class="kpi-value text-{{ $ltdColor }}">{{ $loanToDeposit }}%</div>
            <div class="kpi-sub">Loan book as a share of member deposits</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi" style="--accent:#7c3aed">
            <i class="bi bi-people kpi-icon"></i>
            <div class="kpi-label">Active Members</div>
            <div class="kpi-value">{{ number_format($activeMembers) }}</div>
            <div class="kpi-sub">{{ number_format($savers) }} saving · {{ number_format($borrowers) }} borrowing</div>
        </div>
    </div>
</div>

{{-- ── Deposits mix + Loan portfolio ────────────────────────────────────── --}}
<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center bg-white">
                <span class="panel-title"><i class="bi bi-piggy-bank me-2 text-success"></i>Member Deposits</span>
                @can('view savings reports')<a href="{{ route('reports.savings-balances') }}" class="small text-decoration-none">Savings report <i class="bi bi-arrow-right"></i></a>@endcan
            </div>
            <div class="card-body">
                <div class="row g-3 align-items-center">
                    <div class="col-sm-5 text-center">
                        <div style="position:relative;max-width:210px;margin:auto">
                            <canvas id="depositMixChart"></canvas>
                            <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;pointer-events:none">
                                <div class="text-muted" style="font-size:.7rem">TOTAL</div>
                                <div class="fw-bold" style="color:#0f2444;font-size:.95rem">{{ number_format($totalDeposits / 1e6, 1) }}M</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-7">
                        @foreach($deposits as $d)
                            @php $share = $totalDeposits > 0 ? max(0, $d['amount']) / $totalDeposits * 100 : 0; @endphp
                            <a href="{{ $d['url'] }}" class="mix-row">
                                <span class="mix-dot" style="background:{{ $d['color'] }}"></span>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between">
                                        <span class="small fw-semibold">{{ $d['label'] }}</span>
                                        <span class="small fw-bold">{{ number_format($d['amount'], 0) }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between" style="font-size:.7rem;color:#6b7280">
                                        <span>{{ number_format($d['count']) }} {{ $d['count_label'] ?? 'accounts' }}@if($d['overdrawn'] < 0) · <span class="text-danger">overdrawn {{ number_format($d['overdrawn'], 0) }}</span>@endif</span>
                                        <span>{{ number_format($share, 1) }}%</span>
                                    </div>
                                    <div class="mix-bar"><span style="width:{{ $share }}%;background:{{ $d['color'] }}"></span></div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center bg-white">
                <span class="panel-title"><i class="bi bi-cash-stack me-2 text-primary"></i>Loan Portfolio</span>
                @can('view loans')<a href="{{ route('loans.index') }}" class="small text-decoration-none">All loans <i class="bi bi-arrow-right"></i></a>@endcan
            </div>
            <div class="card-body">
                <a href="{{ route('loans.index', ['type' => 'normal']) }}" class="loan-block std d-block text-decoration-none text-reset mb-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="small fw-bold" style="color:#2563eb">Standard Loans</div>
                            <div class="text-muted" style="font-size:.72rem">{{ number_format($standard->sum('count')) }} running loans</div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold fs-5" style="color:#0f2444">{{ number_format($standardPrincipal, 0) }}</div>
                            <div class="text-muted" style="font-size:.72rem">+ interest owed {{ number_format($standard->sum('interest'), 0) }}</div>
                        </div>
                    </div>
                    @if($standard->count() > 1)
                    <div class="mt-2 pt-2 border-top">
                        @foreach($standard as $s)
                            <div class="d-flex justify-content-between" style="font-size:.75rem">
                                <span class="text-muted">{{ $s['label'] }} <span style="font-size:.68rem">({{ $s['count'] }})</span></span>
                                <span>{{ number_format($s['principal'], 0) }}</span>
                            </div>
                        @endforeach
                    </div>
                    @endif
                </a>
                <a href="{{ route('loans.index', ['type' => 'locked-up']) }}" class="loan-block lu d-block text-decoration-none text-reset mb-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="small fw-bold" style="color:#b91c1c"><i class="bi bi-lock me-1"></i>Locked-Up Loans</div>
                            <div class="text-muted" style="font-size:.72rem">{{ number_format($lockedUp['count']) }} loans under recovery</div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold fs-5" style="color:#0f2444">{{ number_format($lockedUp['principal'], 0) }}</div>
                            <div class="text-muted" style="font-size:.72rem">+ interest owed {{ number_format($lockedUp['interest'], 0) }}</div>
                        </div>
                    </div>
                </a>
                <div class="row g-2">
                    <div class="col-4">
                        <div class="mini-stat">
                            <div class="l">PAR 30</div>
                            <div class="v text-{{ $parColor }}">{{ $par30 }}%</div>
                            <div style="font-size:.68rem;color:#6b7280">{{ number_format($par30Amount, 0) }}</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <a href="{{ route('reports.loan-aging') }}" class="text-decoration-none text-reset">
                        <div class="mini-stat">
                            <div class="l">In arrears</div>
                            <div class="v {{ $overdueCount ? 'text-danger' : '' }}">{{ number_format($overdueCount) }}</div>
                            <div style="font-size:.68rem;color:#6b7280">{{ number_format($maturedCount) }} past maturity</div>
                        </div>
                        </a>
                    </div>
                    <div class="col-4">
                        <a href="{{ route('loans.index', ['status' => 'pending']) }}" class="text-decoration-none text-reset">
                        <div class="mini-stat">
                            <div class="l">Pending</div>
                            <div class="v">{{ number_format($pendingLoans) }}</div>
                            <div style="font-size:.68rem;color:#6b7280">awaiting disbursement</div>
                        </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── This month + trend ──────────────────────────────────────────────── --}}
<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header bg-white"><span class="panel-title"><i class="bi bi-calendar3 me-2 text-info"></i>{{ now()->format('F Y') }}</span></div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-6"><div class="mini-stat"><div class="l">Deposits</div><div class="v text-success">{{ number_format($month['deposits'], 0) }}</div></div></div>
                    <div class="col-6"><div class="mini-stat"><div class="l">Withdrawals</div><div class="v text-danger">{{ number_format($month['withdrawals'], 0) }}</div></div></div>
                    <div class="col-12"><div class="mini-stat d-flex justify-content-between align-items-center"><div class="l mb-0">Net savings flow</div><div class="v {{ $netFlow >= 0 ? 'text-success' : 'text-danger' }}">{{ $netFlow >= 0 ? '+' : '' }}{{ number_format($netFlow, 0) }}</div></div></div>
                    <div class="col-6"><div class="mini-stat"><div class="l">Loans disbursed</div><div class="v">{{ number_format($month['disbursed'], 0) }}</div></div></div>
                    <div class="col-6"><div class="mini-stat"><div class="l">Loan recoveries</div><div class="v">{{ number_format($month['recovered'], 0) }}</div></div></div>
                    <div class="col-6"><div class="mini-stat"><div class="l">New members</div><div class="v">{{ number_format($month['new_members']) }}</div></div></div>
                    <div class="col-6"><div class="mini-stat"><div class="l">New accounts</div><div class="v">{{ number_format($month['new_accounts']) }}</div></div></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center bg-white">
                <span class="panel-title"><i class="bi bi-graph-up me-2 text-primary"></i>Activity — last 6 months</span>
                <span class="text-muted" style="font-size:.72rem">Opening balances from previous systems excluded</span>
            </div>
            <div class="card-body"><canvas id="activityChart" height="120"></canvas></div>
        </div>
    </div>
</div>

{{-- ── Other + FD maturities ───────────────────────────────────────────── --}}
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-white"><span class="panel-title"><i class="bi bi-grid me-2 text-secondary"></i>Other</span></div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-6"><div class="mini-stat"><div class="l">Share capital</div><div class="v">{{ number_format($other['share_capital'], 0) }}</div><div style="font-size:.68rem;color:#6b7280">{{ number_format($other['shareholders']) }} shareholders</div></div></div>
                    <div class="col-6"><div class="mini-stat"><div class="l">Savings groups</div><div class="v">{{ number_format($other['groups']) }}</div><div style="font-size:.68rem;color:#6b7280">{{ number_format($other['group_members']) }} group members</div></div></div>
                    <div class="col-6"><div class="mini-stat"><div class="l">Overdrawn accounts</div><div class="v {{ $other['overdrawn_count'] ? 'text-danger' : '' }}">{{ number_format($other['overdrawn_count']) }}</div><div style="font-size:.68rem;color:#6b7280">{{ number_format($other['overdrawn_amount'], 0) }}</div></div></div>
                    <div class="col-6"><div class="mini-stat"><div class="l">Dormant accounts</div><div class="v {{ $other['dormant'] ? 'text-warning' : '' }}">{{ number_format($other['dormant']) }}</div><div style="font-size:.68rem;color:#6b7280">no activity in 6 months</div></div></div>
                    <div class="col-12"><div class="mini-stat d-flex justify-content-between align-items-center"><div class="l mb-0">Members with unpaid membership fee</div><div class="v">{{ number_format($other['fees_unpaid']) }}</div></div></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center bg-white">
                <span class="panel-title"><i class="bi bi-calendar-event me-2 text-warning"></i>Fixed deposits maturing</span>
                <span class="text-muted small">Due or within 30 days</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light"><tr><th class="ps-3">Deposit</th><th>Member</th><th>Maturity</th><th class="text-end pe-3">Amount</th></tr></thead>
                    <tbody>
                    @forelse($upcomingMaturities as $fd)
                        <tr>
                            <td class="ps-3 font-monospace small"><a href="{{ route('fixed-deposits.show', $fd) }}" class="text-decoration-none">{{ $fd->deposit_number }}</a></td>
                            <td class="small">{{ $fd->client->name ?? '—' }}</td>
                            <td class="small {{ $fd->maturity_date->lte(today()) ? 'text-danger fw-semibold' : 'text-warning' }}">{{ $fd->maturity_date->format('d M Y') }}</td>
                            <td class="text-end pe-3 small">{{ number_format($fd->maturity_amount ?: $fd->principal, 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3 small">No fixed deposits maturing in the next 30 days.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const font = { family: "'Segoe UI', system-ui, sans-serif", size: 11 };
const short = v => Math.abs(v) >= 1e6 ? (v / 1e6).toFixed(1) + 'M' : Math.abs(v) >= 1e3 ? (v / 1e3).toFixed(0) + 'k' : v;

new Chart(document.getElementById('depositMixChart'), {
    type: 'doughnut',
    data: {
        labels: @json($deposits->pluck('label')),
        datasets: [{ data: @json($deposits->map(fn ($d) => max(0, $d['amount']))), backgroundColor: @json($deposits->pluck('color')), borderWidth: 2, borderColor: '#fff' }]
    },
    options: {
        cutout: '72%',
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => c.label + ': ' + c.raw.toLocaleString() } } }
    }
});

new Chart(document.getElementById('activityChart'), {
    type: 'bar',
    data: {
        labels: @json($trend['labels']),
        datasets: [
            { label: 'Savings deposits',    data: @json($trend['deposits']),    backgroundColor: '#0d9488', borderRadius: 3 },
            { label: 'Savings withdrawals', data: @json($trend['withdrawals']), backgroundColor: '#f87171', borderRadius: 3 },
            { label: 'Loans disbursed',     data: @json($trend['disbursed']),   backgroundColor: '#2563eb', borderRadius: 3 },
            { label: 'Loan recoveries',     data: @json($trend['recovered']),   backgroundColor: '#f59e0b', borderRadius: 3 },
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom', labels: { font, boxWidth: 12 } }, tooltip: { callbacks: { label: c => c.dataset.label + ': ' + c.raw.toLocaleString() } } },
        scales: {
            x: { grid: { display: false }, ticks: { font } },
            y: { grid: { color: 'rgba(0,0,0,.05)' }, ticks: { font, callback: short } }
        }
    }
});
</script>
@endpush
@endsection
