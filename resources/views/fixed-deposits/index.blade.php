@extends('layouts.app')
@section('title', 'Fixed Deposits')
@section('breadcrumb')
    <li class="breadcrumb-item active">Fixed Deposits</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Fixed Deposits</h4>
    <div class="d-flex gap-2">
        @can('mature fixed-deposits')
        <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#bulkAccrueModal">
            <i class="bi bi-percent me-1"></i> Accrue Interest
        </button>
        @endcan
        @can('create fixed-deposits')
        <a href="{{ route('fixed-deposits.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> New Fixed Deposit</a>
        @endcan
    </div>
</div>

{{-- Bulk Accrue Interest Modal --}}
<div class="modal fade" id="bulkAccrueModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-percent me-1"></i> Accrue Interest — All Active Deposits</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('fixed-deposits.accrue-interest-bulk') }}"
                  onsubmit="return confirm('Accrue interest expense for all active fixed deposits up to the selected date?')">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info small mb-3">
                        Posts the interest expense accrued so far on <strong>every active fixed deposit</strong> —
                        the proportional share of each deposit's total interest, from its start date up to the selected date,
                        minus whatever has already been accrued. Safe to run repeatedly; it only posts the difference.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Accrue To Date <span class="text-danger">*</span></label>
                        <input type="date" name="interest_date" class="form-control"
                               value="{{ today()->toDateString() }}" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-success"><i class="bi bi-check-circle me-1"></i> Run Accrual</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="card">
    <div class="card-body pb-0">
        <form class="row g-2 mb-3" method="GET">
            <div class="col-md-4"><input type="text" name="search" class="form-control" placeholder="Deposit # or client..." value="{{ request('search') }}"></div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="active" @selected(request('status')=='active')>Active</option>
                    <option value="matured" @selected(request('status')=='matured')>Matured</option>
                    <option value="closed" @selected(request('status')=='closed')>Closed</option>
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-outline-primary">Filter</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th class="ps-3">Deposit #</th><th>Client</th><th>Product</th>
                <th class="text-end">Principal</th><th class="text-end">Interest</th>
                <th class="text-end">Maturity Amount</th><th>Maturity Date</th><th>Status</th><th class="pe-3">Actions</th>
            </tr></thead>
            <tbody>
            @forelse($deposits as $fd)
                <tr class="{{ $fd->isMatured() && $fd->status === 'active' ? 'table-warning' : '' }}">
                    <td class="ps-3 font-monospace">{{ $fd->deposit_number }}</td>
                    <td>
                        @if($fd->client)
                            <a href="{{ route('clients.show', $fd->client) }}" class="text-decoration-none">{{ $fd->client->name }}</a>
                        @else
                            <span class="text-muted fst-italic">Deleted client</span>
                        @endif
                    </td>
                    <td class="small text-muted">{{ $fd->product->name }}</td>
                    <td class="text-end">{{ number_format($fd->principal, $dp) }}</td>
                    <td class="text-end">{{ number_format($fd->interest_amount, $dp) }}</td>
                    <td class="text-end fw-semibold">{{ number_format($fd->maturity_amount, $dp) }}</td>
                    <td class="{{ $fd->isMatured() ? 'fw-semibold text-warning' : '' }}">{{ $fd->maturity_date->format('d M Y') }}</td>
                    <td><span class="badge badge-status-{{ $fd->status }}">{{ ucfirst($fd->status) }}</span></td>
                    <td class="pe-3">
                        <a href="{{ route('fixed-deposits.show', $fd) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                        @can('mature fixed-deposits')
                        @if(in_array($fd->status, ['active','matured']))
                            <a href="{{ route('fixed-deposits.mature-form', $fd) }}" class="btn btn-sm btn-success"><i class="bi bi-check2-all"></i></a>
                        @endif
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No fixed deposits found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $deposits->withQueryString()->links() }}</div>
</div>
@endsection
