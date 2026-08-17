@extends('layouts.app')
@section('title', 'Savings Accounts')
@section('breadcrumb')
    <li class="breadcrumb-item active">Savings Accounts</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Savings Accounts</h4>
    <div class="d-flex gap-2">
        @can('deposit savings')
        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#reverseInterestModal">
            <i class="bi bi-arrow-counterclockwise me-1"></i> Reverse Interest
        </button>
        <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#bulkInterestModal">
            <i class="bi bi-percent me-1"></i> Post Interest
        </button>
        @endcan
        <div class="dropdown">
            <button class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-download me-1"></i> Export
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['format' => 'excel']) }}"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Excel (CSV)</a></li>
                <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['format' => 'pdf']) }}" target="_blank"><i class="bi bi-file-earmark-pdf me-2 text-danger"></i>PDF</a></li>
            </ul>
        </div>
        @can('create savings')
        <a href="{{ route('savings.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Open Account</a>
        @endcan
    </div>
</div>

{{-- Bulk Reverse Interest Modal --}}
<div class="modal fade" id="reverseInterestModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger-subtle">
                <h6 class="modal-title"><i class="bi bi-arrow-counterclockwise me-1"></i> Reverse Interest — All Accounts</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('savings.reverse-interest-bulk') }}"
                  onsubmit="return confirm('This will reverse ALL interest postings on the selected date for every account. Continue?')">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-warning small mb-3">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Reverses <strong>all interest credit entries</strong> posted on the selected date —
                        account balances will be rolled back and <strong>last_interest_date</strong> reset
                        so interest can be re-posted.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Interest Posting Date <span class="text-danger">*</span></label>
                        <input type="date" name="interest_date" class="form-control"
                               value="{{ today()->toDateString() }}" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-danger"><i class="bi bi-arrow-counterclockwise me-1"></i> Reverse Interest</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Bulk Post Interest Modal --}}
<div class="modal fade" id="bulkInterestModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-percent me-1"></i> Post Interest — All Accounts</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('savings.post-interest-bulk') }}"
                  onsubmit="return confirm('Post interest to all eligible savings accounts for the selected date?')">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info small mb-3">
                        Posts interest to <strong>all active savings accounts</strong> on a flat-rate product (rate &gt; 0%) or on a graduated-tiers product.<br>
                        Interest is the <strong>sum of daily accruals</strong> from the last posting date up to the selected date —
                        deposits and withdrawals during the period are correctly reflected.<br>
                        Flat-rate products are also credited automatically on the 1st of every month. <strong>Tiered products are never credited automatically</strong> —
                        they only accrue silently (see each account's "Balance incl. Interest") until you run this.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Posting Date <span class="text-danger">*</span></label>
                        <input type="date" name="interest_date" class="form-control"
                               value="{{ today()->toDateString() }}" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-success"><i class="bi bi-check-circle me-1"></i> Run Interest Posting</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Total Savings</div>
                <div class="fs-4 fw-bold">{{ number_format($totalBalance, $dp) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Number of Savers</div>
                <div class="fs-4 fw-bold">{{ number_format($totalSavers) }}</div>
            </div>
        </div>
    </div>
</div>
<div class="card">
    <div class="card-body pb-0">
        <form class="row g-2 mb-3" method="GET">
            <div class="col-md-4"><input type="text" name="search" class="form-control" placeholder="Account # or client..." value="{{ request('search') }}"></div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="active" @selected(request('status')=='active')>Active</option>
                    <option value="dormant" @selected(request('status')=='dormant')>Dormant</option>
                    <option value="closed" @selected(request('status')=='closed')>Closed</option>
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-outline-primary">Filter</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th class="ps-3">Account #</th><th>Client</th><th>Product</th>
                <th class="text-end">Balance</th><th>Status</th><th class="pe-3">Actions</th>
            </tr></thead>
            <tbody>
            @forelse($accounts as $acc)
                <tr>
                    <td class="ps-3 font-monospace">{{ $acc->account_number }}</td>
                    <td>
                        @if($acc->client)
                            <a href="{{ route('clients.show', $acc->client) }}" class="text-decoration-none">{{ $acc->client->name }}</a>
                        @else
                            <span class="text-muted fst-italic">Deleted client</span>
                        @endif
                    </td>
                    <td class="small text-muted">{{ $acc->product->name }}</td>
                    <td class="text-end fw-semibold">{{ number_format($acc->balance, $dp) }}</td>
                    <td><span class="badge badge-status-{{ $acc->status }}">{{ ucfirst($acc->status) }}</span></td>
                    <td class="pe-3">
                        <a href="{{ route('savings.show', $acc) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                        @can('deposit savings')
                        <a href="{{ route('savings.deposit-form', $acc) }}" class="btn btn-sm btn-success"><i class="bi bi-plus-circle"></i></a>
                        @endcan
                        @can('withdraw savings')
                        <a href="{{ route('savings.withdraw-form', $acc) }}" class="btn btn-sm btn-warning"><i class="bi bi-dash-circle"></i></a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No savings accounts found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $accounts->withQueryString()->links() }}</div>
</div>
@endsection
