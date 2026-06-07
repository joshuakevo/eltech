@extends('layouts.app')
@section('title', 'Share Statement — ' . $client->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('shares.index') }}">Member Shares</a></li>
    <li class="breadcrumb-item"><a href="{{ route('clients.show', $client) }}">{{ $client->name }}</a></li>
    <li class="breadcrumb-item active">Share Statement</li>
@endsection
@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold mb-0">Share Statement</h5>
        <div class="text-muted small">
            <span class="font-monospace">{{ $client->client_number }}</span> — {{ $client->name }}
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('clients.shares.statement-pdf', $client) }}" class="btn btn-sm btn-outline-danger" target="_blank">
            <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
        </a>
        <a href="{{ route('clients.show', $client) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Profile
        </a>
    </div>
</div>

{{-- Summary --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="text-muted small">Active Shares</div>
            <div class="fw-bold fs-4 text-primary">{{ $shares->whereNotIn('status', ['liquidated'])->count() }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="text-muted small">Subscribed Value</div>
            <div class="fw-bold fs-5">{{ number_format($totals['share_value'], 0) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="text-muted small">Amount Paid</div>
            <div class="fw-bold fs-5 text-success">{{ number_format($totals['amount_paid'], 0) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="text-muted small">Outstanding</div>
            <div class="fw-bold fs-5 text-danger">{{ number_format($totals['outstanding'], 0) }}</div>
        </div>
    </div>
</div>

@if($totals['liquidated'] > 0)
<div class="alert alert-secondary d-flex align-items-center gap-2 py-2 small mb-4">
    <i class="bi bi-info-circle"></i>
    <span>Total liquidated payout: <strong>UGX {{ number_format($totals['liquidated'], 0) }}</strong></span>
</div>
@endif

@forelse($shares as $share)
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <span class="font-monospace fw-semibold">{{ $share->share_number }}</span>
            <span class="badge badge-membership-{{ $share->status }}">{{ ucfirst($share->status) }}</span>
            @if($share->isLiquidated())
            <span class="text-muted small">Liquidated {{ $share->liquidated_at?->format('d M Y') }}</span>
            @endif
        </div>
        <div class="text-muted small">
            Subscribed: <strong>UGX {{ number_format($share->share_value, 0) }}</strong>
            &nbsp;|&nbsp;
            Paid: <strong class="text-success">UGX {{ number_format($share->amount_paid, 0) }}</strong>
            @if(!$share->isLiquidated())
            &nbsp;|&nbsp;
            Balance: <strong class="{{ $share->balance > 0 ? 'text-danger' : 'text-success' }}">UGX {{ number_format($share->balance, 0) }}</strong>
            @endif
        </div>
    </div>

    @if($share->transactions->count())
    <div class="table-responsive">
        <table class="table table-sm mb-0 small">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Date</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end">Old Value</th>
                    <th class="text-end">New Value</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th class="pe-3">By</th>
                </tr>
            </thead>
            <tbody>
            @foreach($share->transactions as $txn)
            <tr>
                <td class="ps-3">{{ $txn->transaction_date->format('d M Y') }}</td>
                <td>
                    @if($txn->type === 'payment')
                        <span class="badge bg-success-subtle text-success">Payment</span>
                    @elseif($txn->type === 'revaluation')
                        <span class="badge bg-info-subtle text-info">Revaluation</span>
                    @else
                        <span class="badge bg-secondary-subtle text-secondary">Liquidation</span>
                    @endif
                </td>
                <td>
                    @if($txn->type === 'revaluation')
                        {{ number_format($txn->old_value, 0) }} → {{ number_format($txn->new_value, 0) }}
                    @elseif($txn->type === 'liquidation')
                        Payout to member
                    @else
                        Share payment
                    @endif
                    @if($txn->notes)
                    <div class="text-muted">{{ $txn->notes }}</div>
                    @endif
                </td>
                <td class="text-end fw-semibold">{{ number_format($txn->amount, 0) }}</td>
                <td class="text-end text-muted">{{ $txn->old_value !== null ? number_format($txn->old_value, 0) : '—' }}</td>
                <td class="text-end text-muted">{{ $txn->new_value !== null ? number_format($txn->new_value, 0) : '—' }}</td>
                <td>{{ $txn->payment_method ? ucfirst($txn->payment_method) : '—' }}</td>
                <td class="font-monospace text-muted small">{{ $txn->reference ?? '—' }}</td>
                <td class="pe-3 text-muted small">{{ $txn->createdBy->name ?? '—' }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="card-body text-muted small">No transactions recorded for this share.</div>
    @endif

    @if($share->isLiquidated() && $share->liquidation_notes)
    <div class="card-footer text-muted small">
        <i class="bi bi-chat-left-text me-1"></i>Liquidation notes: {{ $share->liquidation_notes }}
    </div>
    @endif
</div>
@empty
<div class="card">
    <div class="card-body text-center text-muted py-5">
        <i class="bi bi-pie-chart display-4 d-block mb-2"></i>
        No shares recorded for this member.
    </div>
</div>
@endforelse

@endsection
