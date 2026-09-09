@extends('layouts.app')
@section('title', 'CRM Dashboard')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('crm.clients.index') }}">CRM</a></li>
    <li class="breadcrumb-item active">Dashboard</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">CRM Dashboard</h4>
    <a href="{{ route('crm.clients.index') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-people me-1"></i>View Clients</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-people"></i></div>
                <div>
                    <div class="text-muted small">Total Clients</div>
                    <div class="fw-bold fs-5">{{ number_format($totalClients) }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-person-check"></i></div>
                <div>
                    <div class="text-muted small">Active Clients</div>
                    <div class="fw-bold fs-5">{{ number_format($activeCount) }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-person-plus"></i></div>
                <div>
                    <div class="text-muted small">New Clients <span class="d-block" style="font-size:.65rem">(this month)</span></div>
                    <div class="fw-bold fs-5">{{ number_format($newThisMonth) }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon {{ $growthPct >= 0 ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger' }}">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div>
                    <div class="text-muted small">Customer Growth <span class="d-block" style="font-size:.65rem">(vs last month)</span></div>
                    <div class="fw-bold fs-5 {{ $growthPct >= 0 ? 'text-success' : 'text-danger' }}">{{ $growthPct >= 0 ? '+' : '' }}{{ $growthPct }}%</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <a href="{{ route('crm.clients.index', ['health' => 'Healthy']) }}" class="text-decoration-none text-reset d-block">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-emoji-smile"></i></div>
                <div>
                    <div class="text-muted small">🟢 Healthy</div>
                    <div class="fw-bold fs-5">{{ number_format($healthyCount) }}</div>
                </div>
            </div>
        </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="{{ route('crm.clients.index', ['health' => 'Needs Attention']) }}" class="text-decoration-none text-reset d-block">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-emoji-neutral"></i></div>
                <div>
                    <div class="text-muted small">🟡 Needs Attention</div>
                    <div class="fw-bold fs-5">{{ number_format($attentionCount) }}</div>
                </div>
            </div>
        </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="{{ route('crm.clients.index', ['health' => 'At Risk']) }}" class="text-decoration-none text-reset d-block">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-emoji-frown"></i></div>
                <div>
                    <div class="text-muted small">🔴 At Risk</div>
                    <div class="fw-bold fs-5 {{ $atRiskCount > 0 ? 'text-danger' : '' }}">{{ number_format($atRiskCount) }}</div>
                </div>
            </div>
        </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-grid-3x3-gap"></i></div>
                <div>
                    <div class="text-muted small">Avg Products <span class="d-block" style="font-size:.65rem">per active client</span></div>
                    <div class="fw-bold fs-5">{{ $avgProducts }} <span class="fs-6 text-muted">/ 4</span></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <div class="text-muted small">Total Customer Value <span class="d-block" style="font-size:.65rem">(assets)</span></div>
                    <div class="fw-bold fs-5 text-success">{{ number_format($totalCustomerValue, 0) }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-secondary bg-opacity-10 text-secondary"><i class="bi bi-exclamation-circle"></i></div>
                <div>
                    <div class="text-muted small">Total Outstanding <span class="d-block" style="font-size:.65rem">(liabilities)</span></div>
                    <div class="fw-bold fs-5">{{ number_format($totalOutstanding, 0) }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-shield-exclamation"></i></div>
                <div>
                    <div class="text-muted small">Portfolio at Risk <span class="d-block" style="font-size:.65rem">outstanding held by At-Risk clients</span></div>
                    <div class="fw-bold fs-5 {{ $portfolioAtRiskPct > 15 ? 'text-danger' : '' }}">{{ $portfolioAtRiskPct }}%</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-pie-chart"></i></div>
                <div>
                    <div class="text-muted small">Value Concentration <span class="d-block" style="font-size:.65rem">held by top 10 customers</span></div>
                    <div class="fw-bold fs-5">{{ $concentrationPct }}%</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-header">Customer Growth <span class="text-muted small">— new clients per month, last 12 months</span></div>
            <div class="card-body"><canvas id="growthChart" height="90"></canvas></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header">Client Health <span class="text-muted small">— see Customer Health Score</span></div>
            <div class="card-body"><canvas id="healthChart" height="180"></canvas></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">Product Adoption <span class="text-muted small">— clients holding each product</span></div>
            <div class="card-body"><canvas id="adoptionChart" height="140"></canvas></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">Clients by Number of Products</div>
            <div class="card-body"><canvas id="distributionChart" height="140"></canvas></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-7">
        <div class="card h-100">
            <div class="card-header">Transaction Activity <span class="text-muted small">— savings transactions &amp; loan repayments, last 6 months</span></div>
            <div class="card-body"><canvas id="activityChart" height="150"></canvas></div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card h-100">
            <div class="card-header">Top Customers by Value</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th class="ps-3">Client</th><th class="text-end pe-3">Value</th></tr></thead>
                    <tbody>
                    @forelse($topCustomers as $row)
                        <tr>
                            <td class="ps-3">
                                <a href="{{ route('crm.clients.show', $row->client) }}" class="text-decoration-none">{{ $row->client->name }}</a>
                            </td>
                            <td class="text-end pe-3 fw-semibold text-success">{{ number_format($row->value, 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="text-center text-muted py-3">No financially active clients yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-{{ $showBranchChart ? '6' : '12' }}">
        <div class="card h-100">
            <div class="card-header">Clients by Segment</div>
            <div class="card-body"><canvas id="segmentChart" height="{{ $showBranchChart ? 140 : 90 }}"></canvas></div>
        </div>
    </div>
    @if($showBranchChart)
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">Clients by Branch</div>
            <div class="card-body"><canvas id="branchChart" height="140"></canvas></div>
        </div>
    </div>
    @endif
</div>

@endsection
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const font = { family: "'Segoe UI', system-ui, sans-serif", size: 11 };
const gridColor = 'rgba(0,0,0,.05)';

new Chart(document.getElementById('growthChart'), {
    type: 'line',
    data: {
        labels: @json($monthLabels),
        datasets: [{
            label: 'New Clients', data: @json($growthSeries),
            borderColor: 'rgba(37,99,235,.9)', backgroundColor: 'rgba(37,99,235,.15)',
            tension: 0.35, fill: true, pointRadius: 3,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { color: gridColor }, ticks: { font } },
            y: { grid: { color: gridColor }, ticks: { font, precision: 0 }, beginAtZero: true }
        }
    }
});

new Chart(document.getElementById('healthChart'), {
    type: 'doughnut',
    data: {
        labels: ['🟢 Healthy', '🟡 Needs Attention', '🔴 At Risk'],
        datasets: [{
            data: [{{ $healthyCount }}, {{ $attentionCount }}, {{ $atRiskCount }}],
            backgroundColor: ['rgba(34,197,94,.75)', 'rgba(234,179,8,.75)', 'rgba(239,68,68,.75)'],
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: true,
        plugins: { legend: { position: 'bottom', labels: { font, boxWidth: 12 } } },
    }
});

new Chart(document.getElementById('adoptionChart'), {
    type: 'bar',
    data: {
        labels: ['Savings', 'Loans', 'Fixed Deposits', 'Shares'],
        datasets: [{
            label: 'Clients',
            data: [{{ $savingsSet->count() }}, {{ $loanSet->count() }}, {{ $fdSet->count() }}, {{ $shareSet->count() }}],
            backgroundColor: 'rgba(37,99,235,.7)', borderRadius: 4,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false }, ticks: { font } },
            y: { grid: { color: gridColor }, ticks: { font, precision: 0 }, beginAtZero: true }
        }
    }
});

new Chart(document.getElementById('distributionChart'), {
    type: 'bar',
    data: {
        labels: ['0 products', '1 product', '2 products', '3 products', '4 products'],
        datasets: [{
            label: 'Clients',
            data: @json(array_values($distribution)),
            backgroundColor: 'rgba(234,179,8,.75)', borderRadius: 4,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false }, ticks: { font } },
            y: { grid: { color: gridColor }, ticks: { font, precision: 0 }, beginAtZero: true }
        }
    }
});

new Chart(document.getElementById('activityChart'), {
    type: 'bar',
    data: {
        labels: @json($activityLabels),
        datasets: [
            { label: 'Savings Transactions', data: @json($savingsTxnCounts), backgroundColor: 'rgba(34,197,94,.7)', borderRadius: 4 },
            { label: 'Loan Repayments', data: @json($repaymentCounts), backgroundColor: 'rgba(37,99,235,.7)', borderRadius: 4 },
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: true,
        plugins: { legend: { labels: { font } } },
        scales: {
            x: { grid: { color: gridColor }, ticks: { font } },
            y: { grid: { color: gridColor }, ticks: { font, precision: 0 }, beginAtZero: true }
        }
    }
});

new Chart(document.getElementById('segmentChart'), {
    type: 'bar',
    data: {
        labels: @json($segmentLabels),
        datasets: [{
            label: 'Clients',
            data: @json($segmentData),
            backgroundColor: 'rgba(147,51,234,.7)', borderRadius: 4,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: true,
        indexAxis: {{ $showBranchChart ? "'x'" : "'y'" }},
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false }, ticks: { font } },
            y: { grid: { color: gridColor }, ticks: { font, precision: 0 }, beginAtZero: true }
        }
    }
});

@if($showBranchChart)
new Chart(document.getElementById('branchChart'), {
    type: 'bar',
    data: {
        labels: @json($branchLabels),
        datasets: [{
            label: 'Clients',
            data: @json($branchData),
            backgroundColor: 'rgba(16,185,129,.7)', borderRadius: 4,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false }, ticks: { font } },
            y: { grid: { color: gridColor }, ticks: { font, precision: 0 }, beginAtZero: true }
        }
    }
});
@endif
</script>
@endpush
