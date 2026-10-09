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
        @if($type === 'locked-up')
        <a href="{{ route('loans.import-locked-up') }}" class="btn btn-outline-secondary"><i class="bi bi-upload me-1"></i> Import Locked-Up loans</a>
        @endif
        <a href="{{ route('loans.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> New Loan</a>
        @endcan
    </div>
</div>

@include('loans._tabs', ['activeTab' => $type])

<div class="row g-3 mb-4">
    <div class="col-md-{{ $type === 'locked-up' ? '4' : '6' }}">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase">
                    {{ $type === 'locked-up' ? 'Total Principal (Locked-Up)' : ($type === 'closed' ? 'Total Principal (Closed)' : 'Total Outstanding') }}
                </div>
                <div class="fs-4 fw-bold">{{ number_format($totalOutstanding, $dp) }}</div>
            </div>
        </div>
    </div>
    @if($type === 'locked-up')
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Total Interest (Locked-Up)</div>
                <div class="fs-4 fw-bold">{{ number_format($totalInterest, $dp) }}</div>
            </div>
        </div>
    </div>
    @endif
    <div class="col-md-{{ $type === 'locked-up' ? '4' : '6' }}">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase">
                    Number of Loans{{ $type === 'locked-up' ? ' (Locked-Up)' : ($type === 'closed' ? ' (Closed)' : '') }}
                </div>
                <div class="fs-4 fw-bold">{{ number_format($totalCount) }}</div>
            </div>
        </div>
    </div>
</div>
<div class="card">
    <div class="card-body pb-0">
        <form class="row g-2 mb-3" method="GET">
            <input type="hidden" name="type" value="{{ $type }}">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Search loan # or client name..." value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <select name="segment_id" class="form-select">
                    <option value="">All Segments</option>
                    @foreach($segments as $segment)
                    <option value="{{ $segment->id }}" @selected((string)request('segment_id')===(string)$segment->id)>{{ $segment->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="relationship_manager_id" class="form-select">
                    <option value="">All Relationship Managers</option>
                    @foreach($managers as $rm)
                    <option value="{{ $rm->id }}" @selected((string)request('relationship_manager_id')===(string)$rm->id)>{{ $rm->name }}</option>
                    @endforeach
                </select>
            </div>
            @if($type !== 'locked-up')
            <div class="col-md-2">
                <select name="loan_product_id" class="form-select">
                    <option value="">All Loan Products</option>
                    @foreach($products as $product)
                    <option value="{{ $product->id }}" @selected((string)request('loan_product_id')===(string)$product->id)>{{ $product->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-auto"><button class="btn btn-outline-primary">Filter</button></div>
            <div class="col-auto"><a href="{{ route('loans.index', ['type' => $type]) }}" class="btn btn-outline-secondary">Clear</a></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th class="ps-3">Loan #</th><th>Client</th><th>Product</th>
                <th class="text-end">Principal</th>
                @if($type === 'locked-up')
                    <th class="text-end">Interest</th>
                @else
                    <th class="text-end">Outstanding</th>
                @endif
                <th>Disbursed</th>
                <th>{{ $type === 'locked-up' ? 'Last Recovery' : 'Last Repayment' }}</th>
                <th>Status</th><th class="pe-3">Actions</th>
            </tr></thead>
            <tbody>
            @forelse($loans as $loan)
                <tr>
                    <td class="ps-3 font-monospace text-nowrap">{{ $loan->loan_number }}</td>
                    <td>
                        @if($loan->client)
                            <a href="{{ route('clients.show', $loan->client) }}" class="text-decoration-none">{{ $loan->client->name }}</a>
                        @else
                            <span class="text-muted fst-italic">Deleted client</span>
                        @endif
                    </td>
                    <td class="small text-muted">{{ $loan->product->name }}</td>
                    <td class="text-end">{{ number_format($loan->principal, $dp) }}</td>
                    @if($type === 'locked-up')
                        <td class="text-end fw-semibold {{ $loan->outstanding_interest > 0 ? 'text-warning' : 'text-success' }}">
                            {{ number_format($loan->outstanding_interest, $dp) }}
                        </td>
                    @else
                        <td class="text-end fw-semibold {{ $loan->outstanding_principal > 0 ? 'text-warning' : 'text-success' }}">
                            {{ number_format($loan->outstanding_principal, $dp) }}
                        </td>
                    @endif
                    <td class="small text-muted">{{ $loan->disbursement_date ? $loan->disbursement_date->format('d M Y') : '—' }}</td>
                    <td class="small">
                        @if($loan->last_paid_date)
                            @php $lp = \Carbon\Carbon::parse($loan->last_paid_date); $ago = $lp->diffInDays(today()); @endphp
                            <span class="{{ $ago > 90 ? 'text-danger' : ($ago > 30 ? 'text-warning' : 'text-success') }}">{{ $lp->format('d M Y') }}</span>
                            <div class="text-muted" style="font-size:.72rem">{{ $ago }}d ago</div>
                        @else
                            <span class="text-muted">Never</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge badge-status-{{ $loan->status }}">{{ ucfirst($loan->status) }}</span>
                        @if($loan->status === 'active' && $loan->maturity_date && $loan->maturity_date->isPast())
                            <span class="badge bg-danger" title="Matured {{ $loan->maturity_date->format('d M Y') }}">Overdue</span>
                        @endif
                    </td>
                    <td class="pe-3 text-nowrap">
                        <a href="{{ route('loans.show', $loan) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                        @can('repay loans')
                        @if($loan->status === 'active' || ($loan->isLockedUp() && $loan->status === 'defaulted'))
                            <a href="{{ route('loans.repay-form', $loan) }}" class="btn btn-sm btn-success" title="{{ $loan->isLockedUp() ? 'Recover' : 'Repay' }}"><i class="bi bi-cash"></i></a>
                        @endif
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No loans found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $loans->withQueryString()->links() }}</div>
</div>
@endsection
