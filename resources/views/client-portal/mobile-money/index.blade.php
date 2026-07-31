@extends('layouts.client-portal')
@section('title', 'Mobile Money')
@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold mb-0">Mobile Money</h5>
    <div class="d-flex gap-2">
        <a href="{{ route('client-portal.mobile-money.deposit-form') }}" class="btn btn-success btn-sm">
            <i class="bi bi-plus-circle me-1"></i> Deposit
        </a>
        <a href="{{ route('client-portal.mobile-money.withdraw-form') }}" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-dash-circle me-1"></i> Withdraw
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header py-2">Recent Requests</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Date</th>
                    <th>Account</th>
                    <th>Type</th>
                    <th class="text-end">Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            @forelse($transactions as $mm)
                @php
                $statusColors = [
                    'pending_approval' => 'warning', 'pending' => 'secondary', 'processing' => 'info',
                    'successful' => 'success', 'failed' => 'danger', 'cancelled' => 'secondary',
                ];
                $isPending = in_array($mm->status, ['pending', 'processing'], true);
                @endphp
                <tr @if($isPending) data-mm-poll-row data-mm-id="{{ $mm->id }}" data-mm-status-url="{{ route('client-portal.mobile-money.status', $mm) }}" @endif>
                    <td class="ps-3 small text-muted">{{ $mm->created_at->format('d M Y H:i') }}</td>
                    <td class="font-monospace small">{{ $mm->savingsAccount->account_number ?? '—' }}</td>
                    <td>
                        <span class="badge bg-{{ $mm->type === 'deposit' ? 'success' : 'danger' }} bg-opacity-10 text-{{ $mm->type === 'deposit' ? 'success' : 'danger' }}">
                            {{ ucfirst($mm->type) }}
                        </span>
                    </td>
                    <td class="text-end fw-semibold">{{ number_format($mm->amount, 2) }}</td>
                    <td data-mm-status-cell>
                        <span class="badge bg-{{ $statusColors[$mm->status] ?? 'secondary' }} bg-opacity-10 text-{{ $statusColors[$mm->status] ?? 'secondary' }}">
                            @if($isPending)<span class="spinner-border spinner-border-sm me-1" style="width:.7rem;height:.7rem;"></span>@endif
                            {{ ucfirst(str_replace('_',' ',$mm->status)) }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4 small">No mobile money requests yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($transactions->hasPages())
    <div class="card-footer">{{ $transactions->links() }}</div>
    @endif
</div>

@push('scripts')
<script>
(function() {
    var statusColors = {
        pending_approval: 'warning', pending: 'secondary', processing: 'info',
        successful: 'success', failed: 'danger', cancelled: 'secondary',
    };
    var rows = document.querySelectorAll('[data-mm-poll-row]');
    if (!rows.length) return;

    var pending = Array.prototype.slice.call(rows);
    var attempts = 0;
    var maxAttempts = 40; // ~2 minutes at 3s intervals

    function poll() {
        attempts++;
        var stillPending = [];

        var checks = pending.map(function(row) {
            return fetch(row.getAttribute('data-mm-status-url'), { headers: { 'Accept': 'application/json' } })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.status === 'pending' || data.status === 'processing') {
                        stillPending.push(row);
                        return;
                    }
                    // Reached a final state -- reload so balances/statements reflect it everywhere.
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
