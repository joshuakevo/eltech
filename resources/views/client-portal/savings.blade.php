@extends('layouts.client-portal')
@section('title', $savingsAccount->account_number)
@section('content')

<div class="mb-3">
    <a href="{{ route('client-portal.accounts') }}" class="text-muted text-decoration-none small">
        <i class="bi bi-arrow-left me-1"></i>My Statements
    </a>
</div>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h5 class="fw-bold mb-0">{{ $savingsAccount->account_number }}</h5>
        <span class="text-muted small">{{ $savingsAccount->product->name }}</span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge badge-status-{{ $savingsAccount->status }} px-3 py-2 rounded-pill">
            {{ ucfirst($savingsAccount->status) }}
        </span>
        <a href="{{ route('client-portal.savings.pdf', $savingsAccount) }}" class="btn btn-sm btn-outline-danger" target="_blank">
            <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card text-center">
            <div class="text-muted small mb-1">Current Balance</div>
            <div class="fw-bold fs-4 text-success">{{ number_format($savingsAccount->balance, 2) }}</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card text-center">
            <div class="text-muted small mb-1">Account Opened</div>
            <div class="fw-semibold">{{ $savingsAccount->opened_date?->format('d M Y') }}</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card text-center">
            <div class="text-muted small mb-1">Interest Rate (p.a.)</div>
            <div class="fw-semibold">{{ $savingsAccount->product->interest_rate ?? '—' }}%</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white py-3">Transaction History</div>
    <div class="card-body p-0">
        @if($transactions->isEmpty())
            <p class="text-muted p-3 mb-0 small">No transactions yet.</p>
        @else
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Reference</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end">Balance After</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
            @foreach($transactions as $txn)
                <tr>
                    <td>{{ $txn->transaction_date->format('d M Y') }}</td>
                    <td>
                        @if(in_array($txn->type, ['deposit','interest']))
                            <span class="badge bg-success-subtle text-success">{{ ucfirst($txn->type) }}</span>
                        @else
                            <span class="badge bg-danger-subtle text-danger">{{ ucfirst($txn->type) }}</span>
                        @endif
                    </td>
                    <td class="text-muted small">{{ $txn->reference ?? '—' }}</td>
                    <td class="text-end fw-semibold
                        {{ in_array($txn->type, ['deposit','interest']) ? 'text-success' : 'text-danger' }}">
                        {{ in_array($txn->type, ['deposit','interest']) ? '+' : '-' }}{{ number_format(abs($txn->amount), 2) }}
                    </td>
                    <td class="text-end">{{ number_format($txn->balance_after, 2) }}</td>
                    <td class="text-muted small">{{ $txn->notes ?? '' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        <div class="px-3 py-2">
            {{ $transactions->links() }}
        </div>
        @endif
    </div>
</div>

@endsection
