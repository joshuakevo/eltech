@extends('layouts.app')
@section('title', 'Break Fixed Deposit — ' . $fixedDeposit->deposit_number)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('fixed-deposits.index') }}">Fixed Deposits</a></li>
    <li class="breadcrumb-item"><a href="{{ route('fixed-deposits.show', $fixedDeposit) }}">{{ $fixedDeposit->deposit_number }}</a></li>
    <li class="breadcrumb-item active">Break Early</li>
@endsection
@section('content')
<div class="row g-3">
    <div class="col-md-5">
        <div class="alert alert-danger d-flex gap-2 align-items-start mb-3">
            <i class="bi bi-exclamation-triangle-fill fs-5 mt-1"></i>
            <div>
                <strong>Early Termination Warning</strong><br>
                Breaking this deposit before <strong>{{ $fixedDeposit->maturity_date->format('d M Y') }}</strong>
                will <strong>forfeit all interest</strong>. Only the principal of
                <strong>{{ number_format($fixedDeposit->principal, $dp) }}</strong> will be returned.
                @if($fixedDeposit->accrued_interest > 0)
                <br><span class="text-danger">Accrued interest of <strong>{{ number_format($fixedDeposit->accrued_interest, $dp) }}</strong> will be reversed from the GL.</span>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header fw-semibold small py-2">Deposit Summary</div>
            <div class="card-body py-2">
                <table class="table table-sm table-borderless mb-0 small">
                    <tr><td class="text-muted w-40">Client</td><td class="fw-semibold">{{ $fixedDeposit->client->name }}</td></tr>
                    <tr><td class="text-muted">Deposit No.</td><td class="font-monospace">{{ $fixedDeposit->deposit_number }}</td></tr>
                    <tr><td class="text-muted">Principal</td><td class="fw-semibold">{{ number_format($fixedDeposit->principal, $dp) }}</td></tr>
                    <tr><td class="text-muted">Interest Rate</td><td>{{ $fixedDeposit->interest_rate }}% p.a.</td></tr>
                    <tr><td class="text-muted">Start Date</td><td>{{ $fixedDeposit->start_date->format('d M Y') }}</td></tr>
                    <tr><td class="text-muted">Maturity Date</td><td class="text-warning fw-semibold">{{ $fixedDeposit->maturity_date->format('d M Y') }}</td></tr>
                    <tr><td class="text-muted">Interest Forfeited</td><td class="text-danger fw-semibold">{{ number_format($fixedDeposit->interest_amount, $dp) }}</td></tr>
                    <tr><td class="text-muted">Amount Returned</td><td class="text-success fw-bold">{{ number_format($fixedDeposit->principal, $dp) }}</td></tr>
                    @if($fixedDeposit->savingsAccount)
                    <tr><td class="text-muted">Return To</td><td>Savings — {{ $fixedDeposit->savingsAccount->account_number }}</td></tr>
                    @else
                    <tr><td class="text-muted">Return To</td><td>Cash</td></tr>
                    @endif
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card">
            <div class="card-header fw-semibold small py-2 text-danger"><i class="bi bi-scissors me-1"></i>Break Fixed Deposit</div>
            <div class="card-body">
                <form method="POST" action="{{ route('fixed-deposits.break', $fixedDeposit) }}">
                    @csrf
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Break Date <span class="text-danger">*</span></label>
                            <input type="date" name="break_date" class="form-control @error('break_date') is-invalid @enderror"
                                   value="{{ today()->toDateString() }}"
                                   max="{{ min(today()->toDateString(), $fixedDeposit->maturity_date->subDay()->toDateString()) }}"
                                   required>
                            <div class="form-text text-muted">Must be before {{ $fixedDeposit->maturity_date->format('d M Y') }}.</div>
                            @error('break_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Receipt / Reference <span class="text-danger">*</span></label>
                            <input type="text" name="reference" class="form-control" maxlength="100" placeholder="Voucher no., receipt no., etc." required>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('fixed-deposits.show', $fixedDeposit) }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="bi bi-scissors me-1"></i>Confirm Break — Return {{ number_format($fixedDeposit->principal, $dp) }} Only
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
