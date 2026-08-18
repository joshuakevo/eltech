@extends('layouts.app')
@section('title', 'Loans')
@section('breadcrumb')
    <li class="breadcrumb-item active">Loans</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Loans</h4>
    <div class="d-flex gap-2">
        <div class="dropdown">
            <button class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-download me-1"></i> Export
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['format' => 'excel']) }}"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Excel (CSV)</a></li>
                <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['format' => 'pdf']) }}" target="_blank"><i class="bi bi-file-earmark-pdf me-2 text-danger"></i>PDF</a></li>
            </ul>
        </div>
        @can('create loans')
        <a href="{{ route('loans.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> New Loan</a>
        @endcan
    </div>
</div>

<ul class="nav nav-pills mb-4">
    <li class="nav-item">
        <a class="nav-link {{ $type === 'normal' ? 'active' : '' }}" href="{{ route('loans.index', ['type' => 'normal']) }}">Normal Loans</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $type === 'locked-up' ? 'active' : '' }}" href="{{ route('loans.index', ['type' => 'locked-up']) }}">Locked-Up Loans</a>
    </li>
</ul>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Total Outstanding{{ $type === 'locked-up' ? ' (Locked-Up)' : '' }}</div>
                <div class="fs-4 fw-bold">{{ number_format($totalOutstanding, $dp) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Number of Loans{{ $type === 'locked-up' ? ' (Locked-Up)' : '' }}</div>
                <div class="fs-4 fw-bold">{{ number_format($totalCount) }}</div>
            </div>
        </div>
    </div>
</div>
<div class="card">
    <div class="card-body pb-0">
        <form class="row g-2 mb-3" method="GET">
            <input type="hidden" name="type" value="{{ $type }}">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Search loan # or client name..." value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="pending" @selected(request('status')=='pending')>Pending</option>
                    <option value="active" @selected(request('status')=='active')>Active</option>
                    <option value="closed" @selected(request('status')=='closed')>Closed</option>
                    <option value="defaulted" @selected(request('status')=='defaulted')>Defaulted</option>
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-outline-primary">Filter</button></div>
            <div class="col-auto"><a href="{{ route('loans.index', ['type' => $type]) }}" class="btn btn-outline-secondary">Clear</a></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th class="ps-3">Loan #</th><th>Client</th><th>Product</th>
                <th class="text-end">Principal</th><th class="text-end">Outstanding</th>
                <th>Status</th><th class="pe-3">Actions</th>
            </tr></thead>
            <tbody>
            @forelse($loans as $loan)
                <tr>
                    <td class="ps-3 font-monospace">{{ $loan->loan_number }}</td>
                    <td>
                        @if($loan->client)
                            <a href="{{ route('clients.show', $loan->client) }}" class="text-decoration-none">{{ $loan->client->name }}</a>
                        @else
                            <span class="text-muted fst-italic">Deleted client</span>
                        @endif
                    </td>
                    <td class="small text-muted">{{ $loan->product->name }}</td>
                    <td class="text-end">{{ number_format($loan->principal, $dp) }}</td>
                    <td class="text-end fw-semibold {{ $loan->outstanding_principal > 0 ? 'text-warning' : 'text-success' }}">
                        {{ number_format($loan->outstanding_principal, $dp) }}
                    </td>
                    <td>
                        <span class="badge badge-status-{{ $loan->status }}">{{ ucfirst($loan->status) }}</span>
                        @if($loan->status === 'active' && $loan->maturity_date && $loan->maturity_date->isPast())
                            <span class="badge bg-danger" title="Matured {{ $loan->maturity_date->format('d M Y') }}">Overdue</span>
                        @endif
                    </td>
                    <td class="pe-3">
                        <a href="{{ route('loans.show', $loan) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                        @can('repay loans')
                        @if($loan->status === 'active')
                            <a href="{{ route('loans.repay-form', $loan) }}" class="btn btn-sm btn-success"><i class="bi bi-cash"></i></a>
                        @endif
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No loans found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $loans->withQueryString()->links() }}</div>
</div>
@endsection
