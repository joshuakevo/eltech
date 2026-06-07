@extends('layouts.app')
@section('title', $fdProduct->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('fd-products.index') }}">FD Products</a></li>
    <li class="breadcrumb-item active">{{ $fdProduct->name }}</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">{{ $fdProduct->name }}</h4>
    <a href="{{ route('fd-products.edit', $fdProduct) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="stat-card text-center"><div class="text-muted small">Total Deposits</div><div class="fw-bold fs-4">{{ $stats['total'] }}</div></div></div>
    <div class="col-md-4"><div class="stat-card text-center"><div class="text-muted small">Active</div><div class="fw-bold fs-4 text-success">{{ $stats['active'] }}</div></div></div>
    <div class="col-md-4"><div class="stat-card text-center"><div class="text-muted small">Active Principal</div><div class="fw-bold fs-5">{{ number_format($stats['total_principal'], $dp) }}</div></div></div>
</div>
<div class="card">
    <div class="card-body">
        <dl class="row">
            <dt class="col-4 fw-normal text-muted">Interest Rate</dt><dd class="col-8">{{ $fdProduct->interest_rate }}% p.a.</dd>
            <dt class="col-4 fw-normal text-muted">Term</dt><dd class="col-8">{{ $fdProduct->term_months }} months</dd>
            <dt class="col-4 fw-normal text-muted">Min Amount</dt><dd class="col-8">{{ number_format($fdProduct->min_amount, $dp) }}</dd>
            <dt class="col-4 fw-normal text-muted">FD Liability</dt><dd class="col-8">{{ $fdProduct->liabilityAccount?->account_code }} — {{ $fdProduct->liabilityAccount?->account_name ?? '—' }}</dd>
            <dt class="col-4 fw-normal text-muted">Interest Expense</dt><dd class="col-8">{{ $fdProduct->interestExpenseAccount?->account_code }} — {{ $fdProduct->interestExpenseAccount?->account_name ?? '—' }}</dd>
        </dl>
    </div>
</div>
@endsection
