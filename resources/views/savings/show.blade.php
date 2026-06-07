@extends('layouts.app')
@section('title', $saving->account_number)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('savings.index') }}">Savings</a></li>
    <li class="breadcrumb-item active">{{ $saving->account_number }}</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">{{ $saving->account_number }}</h4>
        <span class="text-muted">{{ $saving->client->name }} &bull; {{ $saving->product->name }}</span>
    </div>
    <div class="d-flex gap-2">
        @if($saving->status === 'active')
        @can('deposit savings')
        <a href="{{ route('savings.deposit-form', $saving) }}" class="btn btn-success btn-sm"><i class="bi bi-plus-circle"></i> Deposit</a>
        @endcan
        @can('withdraw savings')
        <a href="{{ route('savings.withdraw-form', $saving) }}" class="btn btn-warning btn-sm"><i class="bi bi-dash-circle"></i> Withdraw</a>
        @endcan
        @can('transfer savings')
        <a href="{{ route('savings.transfer-form', $saving) }}" class="btn btn-info btn-sm text-white"><i class="bi bi-arrow-left-right"></i> Transfer</a>
        @endcan
        @endif
        <a href="{{ route('savings.statement-pdf', $saving) }}" target="_blank" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-download me-1"></i> Export Statement
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="text-muted small">Current Balance</div>
            <div class="fw-bold fs-4 text-primary">{{ number_format($saving->balance, $dp) }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="text-muted small">Min Balance</div>
            <div class="fw-bold fs-5">{{ number_format($saving->product->minimum_balance, $dp) }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="text-muted small">Interest Rate</div>
            <div class="fw-bold fs-5">{{ $saving->product->interest_rate }}% / yr</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="text-muted small">Status</div>
            <div class="fw-bold fs-5"><span class="badge badge-status-{{ $saving->status }}">{{ ucfirst($saving->status) }}</span></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">Transaction History</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th class="ps-3">Date</th><th>Type</th><th>Receipt / Ref</th><th>Description</th>
                <th class="text-end">Amount</th><th class="text-end pe-3">Balance</th>
            </tr></thead>
            <tbody>
            @forelse($saving->transactions as $txn)
                <tr>
                    <td class="ps-3">{{ $txn->transaction_date->format('d M Y') }}</td>
                    <td>
                        @php $types = ['deposit'=>'success','withdrawal'=>'danger','interest'=>'info','fee'=>'warning','loan_repayment'=>'secondary','transfer'=>'primary']; @endphp
                        <span class="badge bg-{{ $types[$txn->transaction_type] ?? 'secondary' }} bg-opacity-10 text-{{ $types[$txn->transaction_type] ?? 'secondary' }}">
                            {{ ucfirst(str_replace('_',' ',$txn->transaction_type)) }}
                        </span>
                    </td>
                    <td class="font-monospace small">{{ $txn->reference ?? '—' }}</td>
                    <td class="small">{{ $txn->description }}</td>
                    <td class="text-end fw-semibold {{ in_array($txn->transaction_type, ['deposit','interest']) ? 'text-success' : 'text-danger' }}">
                        {{ in_array($txn->transaction_type, ['deposit','interest']) ? '+' : '-' }}{{ number_format($txn->amount, $dp) }}
                    </td>
                    <td class="text-end pe-3">{{ number_format($txn->balance_after, $dp) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No transactions.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
