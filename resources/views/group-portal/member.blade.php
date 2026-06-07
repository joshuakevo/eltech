@extends('layouts.group-portal')
@section('title', 'My Savings')
@section('content')

<div class="mb-3">
    <h5 class="fw-bold mb-1">{{ $member->name }}</h5>
    <div class="text-muted small">{{ $group->name }} &bull; {{ $group->group_number }}</div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card text-center p-3">
            <div class="text-muted small">Your Savings Balance</div>
            <div class="fw-bold fs-3 text-primary">{{ number_format($member->balance, 0) }}</div>
            <div class="text-muted" style="font-size:.72rem">UGX</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card text-center p-3">
            <div class="text-muted small">Group</div>
            <div class="fw-bold">{{ $group->name }}</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card text-center p-3">
            <div class="text-muted small">Monthly Rate</div>
            <div class="fw-bold text-success">{{ $group->monthly_interest_rate }}%</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">My Statement</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Notes</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end">Balance After</th>
                </tr>
            </thead>
            <tbody>
            @forelse($transactions as $tx)
            <tr>
                <td>{{ $tx->transaction_date->format('d M Y') }}</td>
                <td>
                    @php $isOut = in_array($tx->type, ['withdrawal','transfer_out']); @endphp
                    <span class="badge {{ $tx->type === 'deposit' ? 'bg-success' : ($isOut ? 'bg-danger' : ($tx->type === 'transfer_out' ? 'bg-warning text-dark' : 'bg-primary')) }}">
                        {{ $tx->type === 'transfer_out' ? 'Transfer Out' : ucfirst($tx->type) }}
                    </span>
                </td>
                <td class="text-muted small">{{ $tx->notes ?? '—' }}</td>
                <td class="text-end font-monospace {{ $isOut ? 'text-danger' : 'text-success' }}">
                    {{ $isOut ? '-' : '+' }}{{ number_format($tx->amount, 0) }}
                </td>
                <td class="text-end font-monospace">{{ number_format($tx->balance_after, 0) }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-center text-muted py-3">No transactions yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($transactions->hasPages())
    <div class="card-footer">{{ $transactions->withQueryString()->links() }}</div>
    @endif
</div>

<p class="text-muted small mt-3">
    <i class="bi bi-info-circle me-1"></i>
    Contact your group leader or branch staff for any queries about your account.
</p>
@endsection
