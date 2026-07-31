@extends('layouts.app')
@section('title', 'Mobile Money')
@section('breadcrumb')
    <li class="breadcrumb-item active">Mobile Money</li>
@endsection
@section('content')
<div class="mb-4">
    <h4 class="fw-bold mb-0">Mobile Money</h4>
    <p class="text-muted small mb-0">Client-initiated mobile money deposits and withdrawals from the Client Portal. Withdrawals require your approval before any payout is sent.</p>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2" method="GET">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Type</label>
                <select name="type" class="form-select">
                    <option value="">All</option>
                    <option value="deposit" @selected(request('type')=='deposit')>Deposit</option>
                    <option value="withdrawal" @selected(request('type')=='withdrawal')>Withdrawal</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="pending_approval" @selected(request('status')=='pending_approval')>Pending Approval</option>
                    <option value="pending" @selected(request('status')=='pending')>Pending</option>
                    <option value="processing" @selected(request('status')=='processing')>Processing</option>
                    <option value="successful" @selected(request('status')=='successful')>Successful</option>
                    <option value="failed" @selected(request('status')=='failed')>Failed</option>
                    <option value="cancelled" @selected(request('status')=='cancelled')>Cancelled</option>
                </select>
            </div>
            <div class="col-auto align-self-end"><button class="btn btn-outline-primary">Filter</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Date</th>
                    <th>Client</th>
                    <th>Account</th>
                    <th>Type</th>
                    <th class="text-end">Amount</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th class="pe-3">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($transactions as $mm)
                @php $isPending = in_array($mm->status, ['pending', 'processing'], true); @endphp
                <tr @if($isPending) data-mm-poll-row data-mm-refresh-url="{{ route('mobile-money.refresh', $mm) }}" @endif>
                    <td class="ps-3 small text-muted">{{ $mm->created_at->format('d M Y H:i') }}</td>
                    <td>
                        @if($mm->client)
                        <a href="{{ route('clients.show', $mm->client) }}" class="text-decoration-none">{{ $mm->client->name }}</a>
                        @endif
                    </td>
                    <td class="font-monospace small">{{ $mm->savingsAccount->account_number ?? '—' }}</td>
                    <td>
                        <span class="badge bg-{{ $mm->type === 'deposit' ? 'success' : 'danger' }} bg-opacity-10 text-{{ $mm->type === 'deposit' ? 'success' : 'danger' }}">
                            {{ ucfirst($mm->type) }}
                        </span>
                    </td>
                    <td class="text-end fw-semibold">{{ number_format($mm->amount, 2) }}</td>
                    <td class="small">{{ $mm->phone_number }}</td>
                    <td>
                        @php
                        $statusColors = [
                            'pending_approval' => 'warning', 'pending' => 'secondary', 'processing' => 'info',
                            'successful' => 'success', 'failed' => 'danger', 'cancelled' => 'secondary',
                        ];
                        @endphp
                        <span class="badge bg-{{ $statusColors[$mm->status] ?? 'secondary' }} bg-opacity-10 text-{{ $statusColors[$mm->status] ?? 'secondary' }}">
                            @if($isPending)<span class="spinner-border spinner-border-sm me-1" style="width:.7rem;height:.7rem;"></span>@endif
                            {{ ucfirst(str_replace('_',' ',$mm->status)) }}
                        </span>
                        @if($mm->failure_reason)
                        <div class="text-muted small mt-1" style="max-width:220px">{{ $mm->failure_reason }}</div>
                        @endif
                    </td>
                    <td class="pe-3">
                        @if($mm->status === 'pending_approval')
                        <form method="POST" action="{{ route('mobile-money.approve', $mm) }}" class="d-inline"
                              onsubmit="return confirm('Approve this withdrawal? This will debit the member\'s account and attempt a real payout of {{ number_format($mm->amount, 2) }} to {{ $mm->phone_number }}.')">
                            @csrf
                            <button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> Approve</button>
                        </form>
                        <form method="POST" action="{{ route('mobile-money.reject', $mm) }}" class="d-inline"
                              onsubmit="return confirm('Reject this withdrawal request?')">
                            @csrf
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i> Reject</button>
                        </form>
                        @elseif(in_array($mm->status, ['pending','processing']))
                        <form method="POST" action="{{ route('mobile-money.refresh', $mm) }}" class="d-inline">
                            @csrf
                            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-clockwise"></i> Refresh Status</button>
                        </form>
                        @else
                        <span class="text-muted small">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No mobile money transactions found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($transactions->hasPages())
    <div class="card-footer">{{ $transactions->withQueryString()->links() }}</div>
    @endif
</div>

@push('scripts')
<script>
(function() {
    var rows = document.querySelectorAll('[data-mm-poll-row]');
    if (!rows.length) return;

    var token = document.querySelector('meta[name="csrf-token"]');
    token = token ? token.getAttribute('content') : null;
    if (!token) return;

    var pending = Array.prototype.slice.call(rows);
    var attempts = 0;
    var maxAttempts = 40; // ~2 minutes at 3s intervals

    function poll() {
        attempts++;
        var stillPending = [];

        var checks = pending.map(function(row) {
            return fetch(row.getAttribute('data-mm-refresh-url'), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.status === 'pending' || data.status === 'processing') {
                        stillPending.push(row);
                        return;
                    }
                    window.location.reload();
                })
                .catch(function() { stillPending.push(row); });
        });

        Promise.all(checks).then(function() {
            pending = stillPending;
            if (pending.length && attempts < maxAttempts) {
                setTimeout(poll, 3000);
            }
        });
    }

    setTimeout(poll, 3000);
})();
</script>
@endpush
@endsection
