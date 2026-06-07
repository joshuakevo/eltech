@extends('layouts.app')
@section('title', $member->name . ' — Statement')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('groups.index') }}">Groups</a></li>
    <li class="breadcrumb-item"><a href="{{ route('groups.show', $group) }}">{{ $group->name }}</a></li>
    <li class="breadcrumb-item active">{{ $member->name }}</li>
@endsection
@section('content')

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-0">{{ $member->name }}</h4>
        <div class="text-muted small">
            {{ $group->name }} &bull; {{ $group->group_number }}
            &bull; <span class="badge {{ $member->is_leader ? 'bg-primary' : 'bg-secondary' }}">{{ $member->is_leader ? 'Leader' : 'Member' }}</span>
        </div>
    </div>
    <a href="{{ route('groups.show', $group) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Group
    </a>
</div>

{{-- Summary cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="text-muted small">Current Balance</div>
            <div class="fw-bold fs-5 text-primary font-monospace">{{ number_format($member->balance, 0) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="text-muted small">Total Deposited</div>
            <div class="fw-bold fs-5 text-success font-monospace">{{ number_format($totalDeposited, 0) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="text-muted small">Total Withdrawn / Transferred</div>
            <div class="fw-bold fs-5 text-danger font-monospace">{{ number_format($totalWithdrawn, 0) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="text-muted small">Interest Earned</div>
            <div class="fw-bold fs-5 text-info font-monospace">{{ number_format($totalInterest, 0) }}</div>
        </div>
    </div>
</div>

{{-- Member details --}}
<div class="card mb-4">
    <div class="card-header fw-semibold py-2 small">Member Details</div>
    <div class="card-body py-2">
        <div class="row g-2 small">
            @if($member->phone)
            <div class="col-md-3"><span class="text-muted">Phone:</span> {{ $member->phone }}</div>
            @endif
            @if($member->email)
            <div class="col-md-3"><span class="text-muted">Email:</span> {{ $member->email }}</div>
            @endif
            @if($member->national_id)
            <div class="col-md-3"><span class="text-muted">National ID:</span> {{ $member->national_id }}</div>
            @endif
            <div class="col-md-3"><span class="text-muted">Status:</span>
                <span class="badge {{ $member->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($member->status) }}</span>
            </div>
            <div class="col-md-3"><span class="text-muted">Portal:</span>
                @if($member->user_id)
                <span class="badge bg-success-subtle text-success">Active — {{ $member->user->email ?? '—' }}</span>
                @else
                <span class="text-muted">No portal account</span>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Transaction history --}}
<div class="card">
    <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history me-1 text-muted"></i>Transaction History</span>
        <span class="text-muted small fw-normal">{{ $transactions->total() }} records</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th class="ps-3">Date</th>
                    <th>Type</th>
                    <th>Posting</th>
                    <th>Notes</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end pe-3">Balance After</th>
                </tr>
            </thead>
            <tbody>
            @forelse($transactions as $tx)
            @php
                $isOut = in_array($tx->type, ['withdrawal', 'transfer_out']);
                $cls = match($tx->type) {
                    'deposit'      => 'bg-success',
                    'withdrawal'   => 'bg-danger',
                    'transfer_out' => 'bg-warning text-dark',
                    'interest'     => 'bg-primary',
                    default        => 'bg-secondary',
                };
            @endphp
            <tr>
                <td class="ps-3">{{ $tx->transaction_date->format('d M Y') }}</td>
                <td><span class="badge {{ $cls }}">{{ $tx->type === 'transfer_out' ? 'Transfer Out' : ucfirst($tx->type) }}</span></td>
                <td class="text-muted small">{{ ucfirst(str_replace('_', ' ', $tx->posting_type ?? '—')) }}</td>
                <td class="text-muted small">{{ $tx->notes ?? '—' }}</td>
                <td class="text-end fw-semibold font-monospace {{ $isOut ? 'text-danger' : 'text-success' }}">
                    {{ $isOut ? '-' : '+' }}{{ number_format($tx->amount, 0) }}
                </td>
                <td class="text-end font-monospace pe-3">{{ number_format($tx->balance_after, 0) }}</td>
            </tr>
            @empty
            <tr><td colspan="6" class="text-center text-muted py-4">No transactions yet.</td></tr>
            @endforelse
            </tbody>
            @if($transactions->isNotEmpty())
            <tfoot class="table-light fw-semibold small">
                <tr>
                    <td colspan="4" class="ps-3">Closing Balance</td>
                    <td></td>
                    <td class="text-end pe-3 text-primary font-monospace">{{ number_format($member->balance, 0) }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
    @if($transactions->hasPages())
    <div class="card-footer">{{ $transactions->withQueryString()->links() }}</div>
    @endif
</div>

@endsection
