@extends('layouts.group-portal')
@section('title', $member->name . ' — Statement')
@section('content')

<div class="mb-3">
    <a href="{{ route('group-portal.leader.members') }}" class="text-muted text-decoration-none small">
        <i class="bi bi-arrow-left me-1"></i>All Members
    </a>
</div>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h5 class="fw-bold mb-0">{{ $member->name }}</h5>
        <div class="text-muted small">{{ $group->name }} &bull; {{ $member->is_leader ? 'Leader' : 'Member' }}</div>
    </div>
    <div class="d-flex align-items-center gap-3">
        <div class="text-center">
            <div class="text-muted small">Current Balance</div>
            <div class="fw-bold fs-5 text-primary font-monospace">{{ number_format($member->balance, 0) }}</div>
        </div>
        @if($member->phone)
        <div class="text-center">
            <div class="text-muted small">Phone</div>
            <div class="fw-semibold">{{ $member->phone }}</div>
        </div>
        @endif
        <a href="{{ route('group-portal.leader.member-statement.pdf', $member) }}" class="btn btn-sm btn-outline-danger" target="_blank">
            <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">Transaction History</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th class="ps-3">Date</th>
                    <th>Type</th>
                    <th>Notes</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end pe-3">Balance After</th>
                </tr>
            </thead>
            <tbody>
            @forelse($transactions as $tx)
            <tr>
                <td class="ps-3">{{ $tx->transaction_date->format('d M Y') }}</td>
                <td>
                    @php $cls = match($tx->type) { 'deposit' => 'bg-success', 'withdrawal' => 'bg-danger', 'transfer_out' => 'bg-warning text-dark', 'interest' => 'bg-primary', default => 'bg-secondary' }; @endphp
                    <span class="badge {{ $cls }}">{{ $tx->type === 'transfer_out' ? 'Transfer Out' : ucfirst($tx->type) }}</span>
                </td>
                <td class="text-muted small">{{ $tx->notes ?? '—' }}</td>
                @php $isOut = in_array($tx->type, ['withdrawal','transfer_out']); @endphp
                <td class="text-end font-monospace fw-semibold {{ $isOut ? 'text-danger' : 'text-success' }}">
                    {{ $isOut ? '-' : '+' }}{{ number_format($tx->amount, 0) }}
                </td>
                <td class="text-end font-monospace pe-3">{{ number_format($tx->balance_after, 0) }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-center text-muted py-4">No transactions yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($transactions->hasPages())
    <div class="card-footer">{{ $transactions->withQueryString()->links() }}</div>
    @endif
</div>
@endsection
