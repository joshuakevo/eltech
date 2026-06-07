@extends('layouts.client-portal')
@section('title', 'Dashboard')
@section('content')

@php $net = $totalSavings + $totalFdValue - $totalLoanDebt; @endphp

{{-- Greeting --}}
<div class="mb-4">
    <h5 class="fw-bold mb-0">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ explode(' ', $client->name)[0] }}</h5>
    <div class="text-muted small">{{ now()->format('l, d F Y') }}</div>
</div>

{{-- ── Stat Cards ──────────────────────────────────────────────────── --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card h-100">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="sc-label">Total Savings</div>
                    <div class="sc-value text-success">{{ number_format($totalSavings, 0) }}</div>
                    <div class="sc-sub">{{ $savingsAccounts->count() }} {{ Str::plural('account', $savingsAccounts->count()) }}</div>
                </div>
                <div class="sc-icon bg-success bg-opacity-10 text-success"><i class="bi bi-piggy-bank-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card h-100">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="sc-label">Loan Outstanding</div>
                    <div class="sc-value {{ $totalLoanDebt > 0 ? 'text-danger' : 'text-muted' }}">{{ number_format($totalLoanDebt, 0) }}</div>
                    <div class="sc-sub">{{ $activeLoans }} active {{ Str::plural('loan', $activeLoans) }}
                        @if($overdueCount > 0)<span class="text-danger fw-semibold">&bull; {{ $overdueCount }} overdue</span>@endif
                    </div>
                </div>
                <div class="sc-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-cash-stack"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card h-100">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="sc-label">Fixed Deposits</div>
                    <div class="sc-value text-warning">{{ number_format($totalFdValue, 0) }}</div>
                    <div class="sc-sub">{{ $activeFds->count() }} active</div>
                </div>
                <div class="sc-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-safe-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card h-100">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="sc-label">Net Position</div>
                    <div class="sc-value {{ $net >= 0 ? 'text-primary' : 'text-danger' }}">{{ number_format($net, 0) }}</div>
                    <div class="sc-sub">Savings + FD − Loans</div>
                </div>
                <div class="sc-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-graph-up-arrow"></i></div>
            </div>
        </div>
    </div>
</div>

{{-- ── Row 2: Cashflow + Loan Status ──────────────────────────────── --}}
<div class="row g-3 mb-4">

    {{-- This month cashflow --}}
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header py-3 d-flex align-items-center gap-2">
                <i class="bi bi-calendar3 text-primary"></i>
                <span>This Month's Activity</span>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small text-muted">Deposits</span>
                        <span class="fw-bold text-success">+{{ number_format($depositsThisMonth, 0) }}</span>
                    </div>
                    @php $maxFlow = max($depositsThisMonth, $withdrawalsThisMonth, 1); @endphp
                    <div class="progress" style="height:6px">
                        <div class="progress-bar bg-success" style="width:{{ min(100, round($depositsThisMonth/$maxFlow*100)) }}%"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small text-muted">Withdrawals</span>
                        <span class="fw-bold text-danger">-{{ number_format($withdrawalsThisMonth, 0) }}</span>
                    </div>
                    <div class="progress" style="height:6px">
                        <div class="progress-bar bg-danger" style="width:{{ min(100, round($withdrawalsThisMonth/$maxFlow*100)) }}%"></div>
                    </div>
                </div>
                <hr class="my-2">
                @php $netFlow = $depositsThisMonth - $withdrawalsThisMonth; @endphp
                <div class="d-flex justify-content-between align-items-center">
                    <span class="small fw-semibold">Net Cash Flow</span>
                    <span class="fw-bold {{ $netFlow >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $netFlow >= 0 ? '+' : '' }}{{ number_format($netFlow, 0) }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Loan repayment status --}}
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header py-3 d-flex align-items-center gap-2">
                <i class="bi bi-cash-coin text-warning"></i>
                <span>Loan Repayments</span>
            </div>
            <div class="card-body">
                @if($activeLoansData->isEmpty())
                    <div class="text-center text-muted py-3 small">
                        <i class="bi bi-check-circle-fill text-success fs-4 d-block mb-2"></i>
                        No active loans
                    </div>
                @else
                    @if($overdueCount > 0)
                    <div class="alert alert-danger py-2 small mb-3">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        <strong>{{ $overdueCount }} overdue</strong> installment{{ $overdueCount > 1 ? 's' : '' }} — please repay urgently.
                    </div>
                    @endif
                    @if($nextDue)
                    <div class="mb-3 p-2 rounded bg-light">
                        <div class="small text-muted mb-1">Next installment due</div>
                        <div class="fw-bold">{{ number_format($nextDue->total_due ?? ($nextDue->principal_due + $nextDue->interest_due), 0) }}</div>
                        @php $daysLeft = now()->diffInDays($nextDue->due_date, false); @endphp
                        <div class="small {{ $daysLeft <= 7 ? 'text-danger' : 'text-muted' }}">
                            {{ $nextDue->due_date->format('d M Y') }}
                            &bull; {{ $daysLeft < 0 ? abs($daysLeft).' days overdue' : $daysLeft.' days left' }}
                        </div>
                    </div>
                    @endif
                    @foreach($activeLoansData as $loan)
                    <div class="d-flex justify-content-between align-items-center small {{ !$loop->last ? 'mb-2 pb-2 border-bottom' : '' }}">
                        <div>
                            <div class="fw-semibold">{{ $loan->loan_number }}</div>
                            <div class="text-muted" style="font-size:.72rem">{{ $loan->product->name }}</div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold text-danger">{{ number_format($loan->outstanding_principal, 0) }}</div>
                            <span class="badge badge-status-{{ $loan->status }}" style="font-size:.65rem">{{ ucfirst($loan->status) }}</span>
                        </div>
                    </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>

    {{-- Fixed deposits & maturing --}}
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header py-3 d-flex align-items-center gap-2">
                <i class="bi bi-safe-fill text-warning"></i>
                <span>Fixed Deposits</span>
            </div>
            <div class="card-body">
                @if($activeFds->isEmpty())
                    <div class="text-center text-muted py-3 small">
                        <i class="bi bi-safe fs-4 d-block mb-2 opacity-50"></i>
                        No active fixed deposits
                    </div>
                @else
                    @foreach($activeFds as $fd)
                    @php $daysToMaturity = now()->diffInDays($fd->maturity_date, false); @endphp
                    <div class="d-flex justify-content-between align-items-center small {{ !$loop->last ? 'mb-2 pb-2 border-bottom' : '' }}">
                        <div>
                            <div class="fw-semibold">{{ $fd->deposit_number }}</div>
                            <div class="text-muted" style="font-size:.72rem">
                                Matures {{ $fd->maturity_date->format('d M Y') }}
                            </div>
                            @if($daysToMaturity <= 30)
                            <span class="badge bg-warning text-dark" style="font-size:.6rem">{{ $daysToMaturity }} days left</span>
                            @endif
                        </div>
                        <div class="text-end">
                            <div class="fw-bold text-warning">{{ number_format($fd->principal, 0) }}</div>
                            <div class="text-muted" style="font-size:.7rem">{{ $fd->interest_rate }}% p.a.</div>
                        </div>
                    </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ── Row 3: Savings Accounts + Trend Chart ──────────────────────── --}}
<div class="row g-3">
    {{-- Savings accounts list --}}
    <div class="col-md-5">
        <div class="card h-100">
            <div class="card-header py-3 d-flex align-items-center justify-content-between">
                <span class="d-flex align-items-center gap-2"><i class="bi bi-piggy-bank-fill text-success"></i> Savings Accounts</span>
                <a href="{{ route('client-portal.accounts') }}" class="btn btn-sm btn-outline-primary" style="font-size:.75rem">View Statements</a>
            </div>
            <div class="card-body p-0">
                @forelse($savingsAccounts as $sa)
                <div class="d-flex align-items-center justify-content-between px-3 py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                    <div>
                        <div class="fw-semibold small">{{ $sa->account_number }}</div>
                        <div class="text-muted" style="font-size:.72rem">{{ $sa->product->name }}</div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold text-success small">{{ number_format($sa->balance, 0) }}</div>
                        <span class="badge {{ $sa->status === 'active' ? 'bg-success' : 'bg-secondary' }}" style="font-size:.6rem">{{ ucfirst($sa->status) }}</span>
                    </div>
                </div>
                @empty
                <div class="text-center text-muted py-4 small">No savings accounts yet.</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Monthly savings trend --}}
    <div class="col-md-7">
        <div class="card h-100">
            <div class="card-header py-3 d-flex align-items-center gap-2">
                <i class="bi bi-bar-chart-fill text-primary"></i> 6-Month Savings Trend
            </div>
            <div class="card-body">
                <canvas id="trendChart" height="170"></canvas>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('trendChart'), {
    type: 'bar',
    data: {
        labels: {!! json_encode(array_column($trend, 'label')) !!},
        datasets: [{
            label: 'Net Savings',
            data: {!! json_encode(array_column($trend, 'amount')) !!},
            backgroundColor: ctx => ctx.dataset.data[ctx.dataIndex] >= 0
                ? 'rgba(34,197,94,.75)' : 'rgba(239,68,68,.7)',
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { ticks: { callback: v => v.toLocaleString() }, grid: { color: '#f3f4f6' } },
            x: { grid: { display: false } }
        }
    }
});
</script>
@endpush
