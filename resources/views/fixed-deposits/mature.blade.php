@extends('layouts.app')
@section('title', 'Process Maturity')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('fixed-deposits.index') }}">Fixed Deposits</a></li>
    <li class="breadcrumb-item"><a href="{{ route('fixed-deposits.show', $fixedDeposit) }}">{{ $fixedDeposit->deposit_number }}</a></li>
    <li class="breadcrumb-item active">Process Maturity</li>
@endsection
@section('content')
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="alert alert-info h-100 mb-0">
            <div class="row text-center">
                <div class="col-4"><div class="text-muted small">Principal</div><div class="fw-bold">{{ number_format($fixedDeposit->principal, $dp) }}</div></div>
                <div class="col-4"><div class="text-muted small">Interest</div><div class="fw-bold text-success">{{ number_format($fixedDeposit->interest_amount, $dp) }}</div></div>
                <div class="col-4"><div class="text-muted small">Total Payout</div><div class="fw-bold fs-5 text-primary">{{ number_format($fixedDeposit->maturity_amount, $dp) }}</div></div>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        @if($fixedDeposit->savingsAccount)
        <div class="alert alert-success h-100 mb-0">
            <i class="bi bi-piggy-bank me-1"></i>
            Maturity amount of <strong>{{ number_format($fixedDeposit->maturity_amount, $dp) }}</strong> will be credited to savings account
            <strong>{{ $fixedDeposit->savingsAccount->account_number }}</strong>
            (current balance: {{ number_format($fixedDeposit->savingsAccount->balance, $dp) }}
            → new balance: {{ number_format($fixedDeposit->savingsAccount->balance + $fixedDeposit->maturity_amount, $dp) }})
        </div>
        @else
        <div class="alert alert-secondary small h-100 mb-0">
            <i class="bi bi-cash me-1"></i>
            Maturity amount will be paid out in <strong>cash</strong>.
        </div>
        @endif
    </div>
</div>
<div class="card">
    <div class="card-header">Process Maturity Payout — {{ $fixedDeposit->deposit_number }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('fixed-deposits.mature', $fixedDeposit) }}">
            @csrf
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Payout Date <span class="text-danger">*</span></label>
                    <input type="date" name="payout_date" class="form-control" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Receipt / Reference <span class="text-danger">*</span></label>
                    <input type="text" name="reference" class="form-control" maxlength="100" placeholder="Voucher no., receipt no., etc." required>
                </div>
            </div>
            <div class="alert alert-warning small">
                <i class="bi bi-exclamation-triangle me-1"></i>
                This will post the maturity journal entries and close the fixed deposit. This action cannot be undone.
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-success">Confirm Payout</button>
                <a href="{{ route('fixed-deposits.show', $fixedDeposit) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
