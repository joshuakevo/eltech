@extends('layouts.group-portal')
@section('title', 'Dashboard')
@section('content')

<div class="mb-4">
    <h5 class="fw-bold mb-0">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ explode(' ', $member->name)[0] }}</h5>
    <div class="text-muted small">{{ $group->name }} &bull; {{ now()->format('l, d F Y') }}</div>
</div>

{{-- Stat cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card text-center p-3 h-100">
            <div class="text-muted small mb-1">Current Balance</div>
            <div class="fw-bold fs-4 text-primary font-monospace">{{ number_format($member->balance, 0) }}</div>
            <div class="text-muted" style="font-size:.7rem">UGX</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center p-3 h-100">
            <div class="text-muted small mb-1">Total Deposited</div>
            <div class="fw-bold fs-5 text-success font-monospace">{{ number_format($totalDeposited, 0) }}</div>
            <div class="text-muted" style="font-size:.7rem">UGX</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center p-3 h-100">
            <div class="text-muted small mb-1">Total Withdrawn</div>
            <div class="fw-bold fs-5 text-danger font-monospace">{{ number_format($totalWithdrawn, 0) }}</div>
            <div class="text-muted" style="font-size:.7rem">UGX</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center p-3 h-100">
            <div class="text-muted small mb-1">Last Deposit</div>
            <div class="fw-bold" style="font-size:.9rem">
                {{ $lastDeposit ? \Carbon\Carbon::parse($lastDeposit)->format('d M Y') : '—' }}
            </div>
            @if($lastDeposit)
            <div class="text-muted" style="font-size:.7rem">{{ \Carbon\Carbon::parse($lastDeposit)->diffForHumans() }}</div>
            @endif
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    {{-- Monthly trend chart --}}
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-bar-chart-fill text-primary"></i> My Monthly Deposits
            </div>
            <div class="card-body">
                <canvas id="trendChart" height="140"></canvas>
            </div>
        </div>
    </div>

    {{-- Group info --}}
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-people-fill text-primary"></i> Group Info
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between py-2">
                    <span class="text-muted small">Group</span>
                    <span class="fw-semibold small">{{ $group->name }}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between py-2">
                    <span class="text-muted small">Number</span>
                    <span class="small font-monospace">{{ $group->group_number }}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between py-2">
                    <span class="text-muted small">Monthly Rate</span>
                    <span class="fw-semibold small text-success">{{ $group->monthly_interest_rate }}%</span>
                </li>
                <li class="list-group-item d-flex justify-content-between py-2">
                    <span class="text-muted small">Your Role</span>
                    <span class="badge {{ $member->is_leader ? 'bg-primary' : 'bg-light text-dark border' }}">
                        {{ $member->is_leader ? 'Leader' : 'Member' }}
                    </span>
                </li>
                <li class="list-group-item d-flex justify-content-between py-2">
                    <span class="text-muted small">Status</span>
                    <span class="badge {{ $member->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($member->status) }}</span>
                </li>
            </ul>
        </div>
    </div>
</div>

{{-- Transfer to Savings --}}
@if((float)$member->balance > 0)
<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-arrow-right-square text-primary"></i>
        <span>Transfer to Savings Account</span>
    </div>
    <div class="card-body">
        @if(session('success'))
        <div class="alert alert-success py-2 small mb-3">{{ session('success') }}</div>
        @endif
        @if(session('error'))
        <div class="alert alert-danger py-2 small mb-3">{{ session('error') }}</div>
        @endif
        <form method="POST" action="{{ route('group-portal.member.transfer-to-savings') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Target Savings Account <span class="text-danger">*</span></label>
                    <select name="savings_account_id" class="form-select form-select-sm" required>
                        <option value="">— Select —</option>
                        @foreach($savingsAccounts as $sa)
                        <option value="{{ $sa->id }}">{{ $sa->account_number }} — {{ $sa->client->name ?? '?' }} — {{ $sa->product->name }} ({{ number_format($sa->balance, 0) }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Amount <span class="text-danger">*</span></label>
                    <input type="number" name="amount" class="form-control form-control-sm" step="0.01" min="0.01" max="{{ $member->balance }}" required placeholder="0.00">
                    <div class="form-text" style="font-size:.68rem">Max: {{ number_format($member->balance, 2) }}</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Date <span class="text-danger">*</span></label>
                    <input type="date" name="transfer_date" class="form-control form-control-sm" value="{{ today()->toDateString() }}" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-arrow-right-square me-1"></i>Transfer
                    </button>
                </div>
                <div class="col-12">
                    <input type="text" name="notes" class="form-control form-control-sm" placeholder="Notes (optional)">
                </div>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Recent transactions --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history me-1 text-muted"></i> Recent Transactions</span>
        <a href="{{ route('group-portal.member.statement') }}" class="btn btn-sm btn-outline-primary">View all</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th class="ps-3">Date</th>
                    <th>Type</th>
                    <th>Notes</th>
                    <th class="text-end pe-3">Amount</th>
                </tr>
            </thead>
            <tbody>
            @forelse($recentTx as $tx)
            <tr>
                <td class="ps-3 small">{{ $tx->transaction_date->format('d M Y') }}</td>
                <td>
                    @php $cls = match($tx->type) { 'deposit'=>'bg-success','withdrawal'=>'bg-danger','interest'=>'bg-primary','transfer_out'=>'bg-warning text-dark',default=>'bg-secondary' }; @endphp
                    <span class="badge {{ $cls }}">{{ $tx->type === 'transfer_out' ? 'Transfer Out' : ucfirst($tx->type) }}</span>
                </td>
                <td class="text-muted small">{{ $tx->notes ?? '—' }}</td>
                @php $isOut = in_array($tx->type, ['withdrawal','transfer_out']); @endphp
                <td class="text-end pe-3 fw-semibold font-monospace {{ $isOut ? 'text-danger' : 'text-success' }}">
                    {{ $isOut ? '-' : '+' }}{{ number_format($tx->amount, 0) }}
                </td>
            </tr>
            @empty
            <tr><td colspan="4" class="text-center text-muted py-4">No transactions yet.</td></tr>
            @endforelse
            </tbody>
        </table>
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
            label: 'Deposits',
            data: {!! json_encode(array_column($trend, 'amount')) !!},
            backgroundColor: 'rgba(37,99,235,.75)',
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
