@extends('layouts.app')
@section('title', 'Repayment Schedule Report')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
    <li class="breadcrumb-item active">Repayment Schedule</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Repayment Schedule Report</h4>
    @if($loan)
    <div class="dropdown">
        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-download me-1"></i>Export</button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['format'=>'pdf']) }}" target="_blank"><i class="bi bi-file-earmark-pdf me-2 text-danger"></i>Export PDF</a></li>
            <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['format'=>'excel']) }}"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Export Excel (CSV)</a></li>
        </ul>
    </div>
    @endif
</div>
<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2" method="GET">
            <div class="col-md-5">
                <label class="form-label small fw-semibold">Select Loan <span class="text-danger">*</span></label>
                <select name="loan_id" class="form-select" required>
                    <option value="">Select active loan...</option>
                    @foreach($loans as $l)
                        <option value="{{ $l->id }}" @selected(isset($loan) && $loan->id==$l->id)>{{ $l->loan_number }} — {{ $l->client->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto align-self-end"><button class="btn btn-primary">View Schedule</button></div>
        </form>
    </div>
</div>
@if($loan)
    @include('loans.schedule', ['loan' => $loan])
@endif
@endsection
