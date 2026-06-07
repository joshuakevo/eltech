@extends('layouts.app')
@section('title', 'FD Maturity Report')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
    <li class="breadcrumb-item active">FD Maturity</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Fixed Deposit Maturity Report</h4>
    <div class="dropdown">
        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-download me-1"></i>Export</button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['format'=>'pdf']) }}" target="_blank"><i class="bi bi-file-earmark-pdf me-2 text-danger"></i>Export PDF</a></li>
            <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['format'=>'excel']) }}"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Export Excel (CSV)</a></li>
        </ul>
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2" method="GET">
            <div class="col-md-3"><label class="form-label small fw-semibold">From Date</label><input type="date" name="from_date" class="form-control" value="{{ $fromDate }}"></div>
            <div class="col-md-3"><label class="form-label small fw-semibold">To Date</label><input type="date" name="to_date" class="form-control" value="{{ $toDate }}"></div>
            <div class="col-auto align-self-end"><button class="btn btn-primary">Run Report</button></div>
        </form>
    </div>
</div>
<div class="card mb-3"><div class="card-body text-center"><div class="text-muted">Total Maturity Payouts Due</div><div class="fw-bold fs-3 text-warning">{{ number_format($total, $dp) }}</div></div></div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th class="ps-3">Deposit #</th><th>Client</th><th>Product</th>
                <th class="text-end">Principal</th><th class="text-end">Interest</th>
                <th class="text-end">Maturity Amount</th><th class="pe-3">Maturity Date</th>
            </tr></thead>
            <tbody>
            @forelse($deposits as $fd)
                <tr>
                    <td class="ps-3 font-monospace"><a href="{{ route('fixed-deposits.show', $fd) }}" class="text-decoration-none">{{ $fd->deposit_number }}</a></td>
                    <td>{{ $fd->client->name }}</td>
                    <td class="small text-muted">{{ $fd->product->name }}</td>
                    <td class="text-end">{{ number_format($fd->principal, $dp) }}</td>
                    <td class="text-end text-success">{{ number_format($fd->interest_amount, $dp) }}</td>
                    <td class="text-end fw-semibold">{{ number_format($fd->maturity_amount, $dp) }}</td>
                    <td class="pe-3 {{ $fd->isMatured() ? 'fw-bold text-danger' : 'text-warning' }}">{{ $fd->maturity_date->format('d M Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No FDs maturing in this period.</td></tr>
            @endforelse
            </tbody>
            <tfoot class="table-light fw-semibold">
                <tr><td colspan="5" class="ps-3">Total</td><td class="text-end">{{ number_format($total, $dp) }}</td><td></td></tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
