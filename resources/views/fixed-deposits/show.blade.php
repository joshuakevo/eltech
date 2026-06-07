@extends('layouts.app')
@section('title', $fixedDeposit->deposit_number)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('fixed-deposits.index') }}">Fixed Deposits</a></li>
    <li class="breadcrumb-item active">{{ $fixedDeposit->deposit_number }}</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">{{ $fixedDeposit->deposit_number }}</h4>
        <span class="text-muted">{{ $fixedDeposit->client->name }} &bull; {{ $fixedDeposit->product->name }}</span>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('fixed-deposits.certificate', $fixedDeposit) }}" target="_blank" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-file-earmark-text me-1"></i>FD Certificate (PDF)
        </a>
        @can('mature fixed-deposits')
        @if($fixedDeposit->status === 'active' && !$fixedDeposit->isMatured())
            <a href="{{ route('fixed-deposits.break-form', $fixedDeposit) }}" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-scissors me-1"></i>Break FD
            </a>
        @endif
        @if(in_array($fixedDeposit->status, ['active','matured']))
            <a href="{{ route('fixed-deposits.mature-form', $fixedDeposit) }}" class="btn btn-success btn-sm">
                <i class="bi bi-check2-all me-1"></i>Process Maturity Payout
            </a>
        @endif
        @endcan
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-4 text-center">
                        <div class="text-muted small">Principal</div>
                        <div class="fw-bold fs-5">{{ number_format($fixedDeposit->principal, $dp) }}</div>
                    </div>
                    <div class="col-4 text-center">
                        <div class="text-muted small">Interest Earned</div>
                        <div class="fw-bold fs-5 text-success">{{ number_format($fixedDeposit->interest_amount, $dp) }}</div>
                    </div>
                    <div class="col-4 text-center">
                        <div class="text-muted small">Maturity Amount</div>
                        <div class="fw-bold fs-5 text-primary">{{ number_format($fixedDeposit->maturity_amount, $dp) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-6 fw-normal text-muted">Rate</dt><dd class="col-6">{{ $fixedDeposit->interest_rate }}% p.a.</dd>
                    <dt class="col-6 fw-normal text-muted">Term</dt><dd class="col-6">{{ $fixedDeposit->term_months }} months</dd>
                    <dt class="col-6 fw-normal text-muted">Start Date</dt><dd class="col-6">{{ $fixedDeposit->start_date->format('d M Y') }}</dd>
                    <dt class="col-6 fw-normal text-muted">Maturity</dt>
                    <dd class="col-6 {{ $fixedDeposit->isMatured() ? 'fw-bold text-warning' : '' }}">{{ $fixedDeposit->maturity_date->format('d M Y') }}</dd>
                    <dt class="col-6 fw-normal text-muted">Status</dt><dd class="col-6"><span class="badge badge-status-{{ $fixedDeposit->status }}">{{ ucfirst($fixedDeposit->status) }}</span></dd>
                    @if($fixedDeposit->status === 'active')
                        <dt class="col-6 fw-normal text-muted">Days Left</dt><dd class="col-6">{{ $fixedDeposit->daysToMaturity() }}</dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>
</div>

@if($fixedDeposit->isMatured() && $fixedDeposit->status === 'active')
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        This fixed deposit has matured. Process the payout to release <strong>{{ number_format($fixedDeposit->maturity_amount, $dp) }}</strong> to the client.
        @can('mature fixed-deposits')
        <a href="{{ route('fixed-deposits.mature-form', $fixedDeposit) }}" class="btn btn-warning btn-sm ms-3">Process Now</a>
        @endcan
    </div>
@endif
@if($fixedDeposit->status === 'broken')
    <div class="alert alert-danger d-flex align-items-center gap-2">
        <i class="bi bi-scissors fs-5"></i>
        <span>This fixed deposit was <strong>broken early</strong> on <strong>{{ $fixedDeposit->closed_date?->format('d M Y') }}</strong>.
        Only the principal of <strong>{{ number_format($fixedDeposit->principal, $dp) }}</strong> was returned. Interest was forfeited.</span>
    </div>
@endif
@endsection
