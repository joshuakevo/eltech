@extends('layouts.app')
@section('title', 'Interest Income')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
    <li class="breadcrumb-item active">Interest Income</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Interest Income Report</h4>
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
<div class="card mb-3">
    <div class="card-body text-center">
        <div class="text-muted">Total Interest & Revenue Income</div>
        <div class="fw-bold fs-3 text-success">{{ number_format($total, $dp) }}</div>
    </div>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th class="ps-3">Account Code</th><th>Account Name</th><th class="text-end pe-3">Amount</th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    <td class="ps-3 font-monospace">{{ $row['account']->account_code }}</td>
                    <td>{{ $row['account']->account_name }}</td>
                    <td class="text-end pe-3 fw-semibold text-success">{{ number_format($row['balance'], $dp) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-center text-muted py-4">No income records found.</td></tr>
            @endforelse
            </tbody>
            <tfoot class="table-light fw-semibold"><tr><td colspan="2" class="ps-3">Total</td><td class="text-end pe-3 text-success">{{ number_format($total, $dp) }}</td></tr></tfoot>
        </table>
    </div>
</div>
@endsection
