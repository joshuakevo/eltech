@extends('layouts.app')
@section('title', 'Member Shares')
@section('breadcrumb')
    <li class="breadcrumb-item active">Member Shares</li>
@endsection
@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold mb-0"><i class="bi bi-pie-chart-fill me-2 text-primary"></i>Member Shares</h5>
    @can('manage shares')
    <button class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#bulkRevalueModal">
        <i class="bi bi-arrow-repeat me-1"></i>Bulk Revalue All
    </button>
    @endcan
</div>

{{-- Summary Stats --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="text-muted small">Active Shares</div>
            <div class="fw-bold fs-4 text-primary">{{ number_format($totals->total_shares ?? 0) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="text-muted small">Total Subscribed Value</div>
            <div class="fw-bold fs-5">{{ number_format($totals->total_value ?? 0) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="text-muted small">Total Paid In</div>
            <div class="fw-bold fs-5 text-success">{{ number_format($totals->total_paid ?? 0) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="text-muted small">Total Outstanding</div>
            <div class="fw-bold fs-5 text-danger">{{ number_format($totals->total_outstanding ?? 0) }}</div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search share # or member name…" value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="unpaid"    {{ request('status')=='unpaid'    ? 'selected':'' }}>Unpaid</option>
                    <option value="partial"   {{ request('status')=='partial'   ? 'selected':'' }}>Partial</option>
                    <option value="paid"      {{ request('status')=='paid'      ? 'selected':'' }}>Paid</option>
                    <option value="liquidated"{{ request('status')=='liquidated'? 'selected':'' }}>Liquidated</option>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary"><i class="bi bi-search"></i> Filter</button>
                <a href="{{ route('shares.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

{{-- Shares Table --}}
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Share #</th>
                    <th>Member</th>
                    <th class="text-end">Subscribed</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Balance</th>
                    <th>Status</th>
                    <th class="pe-3">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($shares as $share)
            <tr>
                <td class="ps-3 font-monospace small">{{ $share->share_number }}</td>
                <td>
                    @if($share->client)
                        <a href="{{ route('clients.show', $share->client) }}" class="text-decoration-none fw-semibold">{{ $share->client->name }}</a>
                        <div class="text-muted small font-monospace">{{ $share->client->client_number }}</div>
                    @else
                        <span class="text-muted fst-italic">Deleted client</span>
                    @endif
                </td>
                <td class="text-end">{{ number_format($share->share_value, 0) }}</td>
                <td class="text-end text-success">{{ number_format($share->amount_paid, 0) }}</td>
                <td class="text-end {{ $share->balance > 0 ? 'text-danger' : 'text-success' }}">
                    {{ number_format($share->balance, 0) }}
                </td>
                <td>
                    <span class="badge badge-membership-{{ $share->status }}">{{ ucfirst($share->status) }}</span>
                    @if($share->isLiquidated())
                    <div class="text-muted small">{{ $share->liquidated_at?->format('d M Y') }}</div>
                    @endif
                </td>
                <td class="pe-3">
                    @if($share->client)
                    <a href="{{ route('clients.shares.statement', $share->client) }}" class="btn btn-sm btn-outline-secondary py-0" title="Statement">
                        <i class="bi bi-file-text"></i>
                    </a>
                    <a href="{{ route('clients.show', $share->client) }}" class="btn btn-sm btn-outline-primary py-0" title="Member profile">
                        <i class="bi bi-person"></i>
                    </a>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-center text-muted py-4">No shares found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($shares->hasPages())
    <div class="card-footer">{{ $shares->withQueryString()->links() }}</div>
    @endif
</div>

{{-- Bulk Revalue Modal --}}
@can('manage shares')
<div class="modal fade" id="bulkRevalueModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-arrow-repeat me-1"></i>Bulk Revalue All Active Shares</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('shares.revalue-all') }}">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-warning py-2 small">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        This will update the subscribed value for <strong>all non-liquidated shares</strong> and post journal entries for each one.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Share Value (UGX) <span class="text-danger">*</span></label>
                        <input type="number" name="new_share_value" class="form-control" step="1000" min="1" required placeholder="e.g. 150000">
                        <div class="form-text">Enter the new subscribed value per share.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Effective Date <span class="text-danger">*</span></label>
                        <input type="date" name="effective_date" class="form-control" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Reason for revaluation…">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-sm btn-warning">Apply Revaluation</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

@endsection
