@extends('layouts.app')
@section('title', $loanProduct->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('loan-products.index') }}">Loan Products</a></li>
    <li class="breadcrumb-item active">{{ $loanProduct->name }}</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">{{ $loanProduct->name }}</h4>
    <a href="{{ route('loan-products.edit', $loanProduct) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="stat-card text-center"><div class="text-muted small">Total Loans</div><div class="fw-bold fs-4">{{ $stats['total_loans'] }}</div></div></div>
    <div class="col-md-3"><div class="stat-card text-center"><div class="text-muted small">Active Loans</div><div class="fw-bold fs-4 text-success">{{ $stats['active_loans'] }}</div></div></div>
    <div class="col-md-3"><div class="stat-card text-center"><div class="text-muted small">Total Disbursed</div><div class="fw-bold fs-5">{{ number_format($stats['total_disbursed'], $dp) }}</div></div></div>
    <div class="col-md-3"><div class="stat-card text-center"><div class="text-muted small">Outstanding</div><div class="fw-bold fs-5 text-warning">{{ number_format($stats['total_outstanding'], $dp) }}</div></div></div>
</div>
<div class="card">
    <div class="card-body">
        <dl class="row">
            <dt class="col-4 fw-normal text-muted">Interest Rate</dt><dd class="col-8">{{ $loanProduct->interest_rate }}% per annum</dd>
            <dt class="col-4 fw-normal text-muted">Interest Method</dt><dd class="col-8">{{ ucfirst($loanProduct->interest_method) }}</dd>
            <dt class="col-4 fw-normal text-muted">Default Term</dt><dd class="col-8">{{ $loanProduct->term_months }} months</dd>
            <dt class="col-4 fw-normal text-muted">Penalty Rate</dt><dd class="col-8">{{ $loanProduct->penalty_rate }}% per day <span class="text-muted small">(legacy, unused &mdash; see <a href="{{ route('loan-penalty-tiers.edit') }}">Loan Penalty Tiers</a>)</span></dd>
            <dt class="col-4 fw-normal text-muted">Loan Receivable</dt><dd class="col-8">{{ $loanProduct->receivableAccount?->account_code }} — {{ $loanProduct->receivableAccount?->account_name ?? '—' }}</dd>
            <dt class="col-4 fw-normal text-muted">Interest Income</dt><dd class="col-8">{{ $loanProduct->interestIncomeAccount?->account_code }} — {{ $loanProduct->interestIncomeAccount?->account_name ?? '—' }}</dd>
            <dt class="col-4 fw-normal text-muted">Penalty Income</dt><dd class="col-8">{{ $loanProduct->penaltyIncomeAccount?->account_code }} — {{ $loanProduct->penaltyIncomeAccount?->account_name ?? '—' }}</dd>
            <dt class="col-4 fw-normal text-muted">Disbursement</dt><dd class="col-8">{{ $loanProduct->disbursementAccount?->account_code }} — {{ $loanProduct->disbursementAccount?->account_name ?? '—' }}</dd>
        </dl>
    </div>
</div>
@endsection
