@extends('layouts.app')
@section('title', 'Savings Balances')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
    <li class="breadcrumb-item active">Savings Balances</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Savings Account Balances</h4>
    <div class="dropdown">
        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-download me-1"></i>Export</button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['format'=>'pdf']) }}" target="_blank"><i class="bi bi-file-earmark-pdf me-2 text-danger"></i>Export PDF</a></li>
            <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['format'=>'excel']) }}"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Export Excel (CSV)</a></li>
        </ul>
    </div>
</div>
<div class="card mb-3"><div class="card-body text-center"><div class="text-muted">Total Savings Balance</div><div class="fw-bold fs-3 text-primary">{{ number_format($total, $dp) }}</div></div></div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th class="ps-3">Account #</th><th>Client</th><th>Product</th><th class="text-end pe-3">Balance</th><th>Status</th>
            </tr></thead>
            <tbody>
            @forelse($accounts as $acc)
                <tr>
                    <td class="ps-3 font-monospace"><a href="{{ route('savings.show', $acc) }}" class="text-decoration-none">{{ $acc->account_number }}</a></td>
                    <td>{{ $acc->client->name }}</td>
                    <td class="small text-muted">{{ $acc->product->name }}</td>
                    <td class="text-end pe-3 fw-semibold">{{ number_format($acc->balance, $dp) }}</td>
                    <td><span class="badge badge-status-{{ $acc->status }}">{{ ucfirst($acc->status) }}</span></td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No savings accounts.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
