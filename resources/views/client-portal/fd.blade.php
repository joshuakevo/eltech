@extends('layouts.client-portal')
@section('title', $fixedDeposit->deposit_number)
@section('content')

<div class="mb-3">
    <a href="{{ route('client-portal.accounts') }}" class="text-muted text-decoration-none small">
        <i class="bi bi-arrow-left me-1"></i>My Statements
    </a>
</div>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h5 class="fw-bold mb-0">{{ $fixedDeposit->deposit_number }}</h5>
        <span class="text-muted small">{{ $fixedDeposit->product->name }}</span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge badge-status-{{ $fixedDeposit->status }} px-3 py-2 rounded-pill">
            {{ ucfirst($fixedDeposit->status) }}
        </span>
        <a href="{{ route('client-portal.fd.pdf', $fixedDeposit) }}" class="btn btn-sm btn-outline-danger" target="_blank">
            <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-3">
        <div class="stat-card text-center">
            <div class="text-muted small mb-1">Principal</div>
            <div class="fw-bold fs-5">{{ number_format($fixedDeposit->principal, 2) }}</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card text-center">
            <div class="text-muted small mb-1">Interest Rate (p.a.)</div>
            <div class="fw-bold fs-5">{{ $fixedDeposit->interest_rate }}%</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card text-center">
            <div class="text-muted small mb-1">Interest Earned</div>
            <div class="fw-bold fs-5 text-success">{{ number_format($fixedDeposit->interest_amount, 2) }}</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="stat-card text-center">
            <div class="text-muted small mb-1">Maturity Amount</div>
            <div class="fw-bold fs-5 text-primary">{{ number_format($fixedDeposit->maturity_amount, 2) }}</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white py-3">Deposit Details</div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-4 text-muted fw-normal">Deposit Number</dt>
            <dd class="col-sm-8 fw-semibold">{{ $fixedDeposit->deposit_number }}</dd>

            <dt class="col-sm-4 text-muted fw-normal">Term</dt>
            <dd class="col-sm-8">{{ $fixedDeposit->term_months }} month(s)</dd>

            <dt class="col-sm-4 text-muted fw-normal">Start Date</dt>
            <dd class="col-sm-8">{{ $fixedDeposit->start_date->format('d M Y') }}</dd>

            <dt class="col-sm-4 text-muted fw-normal">Maturity Date</dt>
            <dd class="col-sm-8">{{ $fixedDeposit->maturity_date->format('d M Y') }}</dd>

            @if($fixedDeposit->status !== 'active')
            <dt class="col-sm-4 text-muted fw-normal">Closed Date</dt>
            <dd class="col-sm-8">{{ $fixedDeposit->closed_date?->format('d M Y') ?? '—' }}</dd>
            @endif

            <dt class="col-sm-4 text-muted fw-normal">Accrued Interest</dt>
            <dd class="col-sm-8">{{ number_format($fixedDeposit->accrued_interest, 2) }}</dd>

            <dt class="col-sm-4 text-muted fw-normal">Interest Calculation</dt>
            <dd class="col-sm-8 small text-muted">
                Simple interest: {{ number_format($fixedDeposit->principal, 2) }}
                × {{ $fixedDeposit->interest_rate }}%
                × {{ $fixedDeposit->term_months }}/12
                = {{ number_format($fixedDeposit->interest_amount, 2) }}
            </dd>
        </dl>
    </div>
</div>

@endsection
