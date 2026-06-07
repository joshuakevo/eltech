@extends('layouts.group-portal')
@section('title', 'Dashboard')
@section('content')

<div class="mb-4">
    <h5 class="fw-bold mb-0">Dashboard</h5>
    <div class="text-muted small">Overview for {{ now()->format('F Y') }}</div>
</div>

{{-- ── Stat cards ───────────────────────────────────────────────────── --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3 d-flex">
        <div class="card text-center p-3 w-100 d-flex justify-content-center">
            <div class="text-muted small mb-1">{{ $group->isPooled() ? 'Pool Balance' : 'Total Balance' }}</div>
            <div class="fw-bold fs-5 text-primary">
                {{ $group->isPooled() ? number_format($group->pool_balance, 0) : number_format($members->sum('balance'), 0) }}
            </div>
            <div class="text-muted" style="font-size:.68rem">UGX</div>
        </div>
    </div>
    <div class="col-6 col-md-3 d-flex">
        <div class="card text-center p-3 w-100 d-flex justify-content-center">
            <div class="text-muted small mb-1">Active Members</div>
            <div class="fw-bold fs-5">{{ $members->count() }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3 d-flex">
        <div class="card text-center p-3 w-100 d-flex justify-content-center">
            <div class="text-muted small mb-1">Deposits This Month</div>
            <div class="fw-bold fs-5 text-success">{{ number_format($monthlyDeposits, 0) }}</div>
            <div class="text-muted" style="font-size:.68rem">UGX</div>
        </div>
    </div>
    <div class="col-6 col-md-3 d-flex">
        <div class="card text-center p-3 w-100 d-flex justify-content-center">
            <div class="text-muted small mb-1">Last Deposit</div>
            @if($lastDeposit)
            <div class="fw-bold" style="font-size:.9rem">{{ \Carbon\Carbon::parse($lastDeposit)->format('d M Y') }}</div>
            <div class="text-muted" style="font-size:.68rem">{{ \Carbon\Carbon::parse($lastDeposit)->diffForHumans() }}</div>
            @else
            <div class="text-muted fw-semibold">None yet</div>
            @endif
        </div>
    </div>
</div>

{{-- ── Trend chart ──────────────────────────────────────────────────── --}}
<div class="card mb-4">
    <div class="card-header">Monthly Deposit Trend (last 6 months)</div>
    <div class="card-body">
        <div style="position:relative;height:220px;width:100%">
            <canvas id="trendChart"></canvas>
        </div>
    </div>
</div>

{{-- ── Performers ───────────────────────────────────────────────────── --}}
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-trophy-fill text-warning"></i> Top Savers
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <tbody>
                    @forelse($topMembers as $i => $m)
                    <tr>
                        <td class="ps-3" style="width:36px">
                            @if($i === 0)<span class="badge" style="background:#f59e0b">1st</span>
                            @elseif($i === 1)<span class="badge bg-secondary">2nd</span>
                            @else<span class="badge bg-light text-dark border">3rd</span>@endif
                        </td>
                        <td class="fw-semibold">{{ $m->name }}</td>
                        <td class="text-end pe-3 fw-bold text-primary font-monospace">{{ number_format($m->balance, 0) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="text-muted small py-3 text-center">No data</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-arrow-down-circle-fill text-danger"></i> Lowest Balances
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <tbody>
                    @forelse($bottomMembers as $i => $m)
                    <tr>
                        <td class="ps-3 text-muted" style="width:28px">{{ $i + 1 }}</td>
                        <td class="fw-semibold">{{ $m->name }}</td>
                        <td class="text-end pe-3 fw-bold font-monospace {{ $m->balance <= 0 ? 'text-danger' : 'text-warning' }}">
                            {{ number_format($m->balance, 0) }}
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="text-muted small py-3 text-center">No data</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ── Watch list ───────────────────────────────────────────────────── --}}
@php $watchList = $zeroBalance->merge($inactiveDepositors)->unique('id'); @endphp
@if($watchList->isNotEmpty())
<div class="card" style="border-color:#fbbf24">
    <div class="card-header d-flex align-items-center gap-2" style="background:#fffbeb;border-color:#fbbf24">
        <i class="bi bi-exclamation-triangle-fill text-warning"></i>
        <span>Members to Watch</span>
        <span class="badge bg-warning text-dark ms-auto">{{ $watchList->count() }}</span>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0 small">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Member</th>
                    <th class="text-end">Balance</th>
                    <th>Last Deposit</th>
                    <th>Flag</th>
                </tr>
            </thead>
            <tbody>
            @foreach($watchList as $m)
            <tr>
                <td class="ps-3 fw-semibold">{{ $m->name }}
                    @if($m->phone)<div class="text-muted" style="font-size:.7rem">{{ $m->phone }}</div>@endif
                </td>
                <td class="text-end font-monospace {{ $m->balance <= 0 ? 'text-danger' : '' }}">{{ number_format($m->balance, 0) }}</td>
                <td>
                    @if(isset($lastDepositByMember[$m->id]))
                        {{ \Carbon\Carbon::parse($lastDepositByMember[$m->id])->format('d M Y') }}
                        <div class="text-muted" style="font-size:.68rem">{{ \Carbon\Carbon::parse($lastDepositByMember[$m->id])->diffForHumans() }}</div>
                    @else<span class="text-muted">Never</span>@endif
                </td>
                <td>
                    @if($m->balance <= 0)<span class="badge bg-danger-subtle text-danger">Zero balance</span> @endif
                    @if(!isset($lastDepositByMember[$m->id]) || \Carbon\Carbon::parse($lastDepositByMember[$m->id])->lt(now()->subDays(60)))
                        <span class="badge bg-warning-subtle text-warning">60+ days inactive</span>
                    @endif
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const labels  = @json(array_column($trend, 'label'));
const amounts = @json(array_column($trend, 'amount'));

new Chart(document.getElementById('trendChart'), {
    type: 'bar',
    data: {
        labels,
        datasets: [{
            label: 'Deposits (UGX)',
            data: amounts,
            backgroundColor: 'rgba(37,99,235,.15)',
            borderColor: '#2563eb',
            borderWidth: 2,
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => ' UGX ' + ctx.parsed.y.toLocaleString()
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: v => v.toLocaleString()
                },
                grid: { color: '#f3f4f6' }
            },
            x: { grid: { display: false } }
        }
    }
});
</script>
@endpush
@endsection
