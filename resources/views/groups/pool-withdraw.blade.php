@extends('layouts.app')
@section('title', 'Pool Withdrawal')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('groups.index') }}">Groups</a></li>
    <li class="breadcrumb-item"><a href="{{ route('groups.show', $group) }}">{{ $group->name }}</a></li>
    <li class="breadcrumb-item active">Pool Withdrawal</li>
@endsection
@section('content')
<div class="row g-3">
<div class="col-md-7">

<div class="alert alert-warning d-flex gap-3 align-items-center mb-3">
    <i class="bi bi-safe2-fill fs-4"></i>
    <div>
        <div class="fw-semibold">Group Pool Balance</div>
        <div class="fs-4 fw-bold text-success">{{ number_format($group->pool_balance, 0) }}</div>
    </div>
</div>

<div class="card">
    <div class="card-header fw-semibold"><i class="bi bi-dash-circle text-danger me-2"></i>Pool Withdrawal — {{ $group->name }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('groups.pool-withdraw.store', $group) }}">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text">{{ \App\Models\SystemSetting::get('currency','KES') }}</span>
                    <input type="number" name="amount" class="form-control @error('amount') is-invalid @enderror"
                           step="0.01" min="0.01" max="{{ $group->pool_balance }}"
                           value="{{ old('amount') }}" required>
                </div>
                @error('amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="form-text">Available pool balance: {{ number_format($group->pool_balance, 0) }}</div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Purpose / Narration <span class="text-danger">*</span></label>
                <input type="text" name="purpose" class="form-control @error('purpose') is-invalid @enderror"
                       value="{{ old('purpose') }}" placeholder="e.g. Emergency fund payout, loan to member, etc." required>
                @error('purpose')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                    <input type="date" name="transaction_date" class="form-control @error('transaction_date') is-invalid @enderror"
                           value="{{ old('transaction_date', today()->toDateString()) }}"
                           max="{{ today()->toDateString() }}" required>
                    @error('transaction_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Receipt / Reference <span class="text-danger">*</span></label>
                    <input type="text" name="reference" class="form-control" value="{{ old('reference') }}" placeholder="Voucher no., etc." required>
                </div>
            </div>

            <div class="alert alert-secondary small mb-3">
                <i class="bi bi-info-circle me-1"></i>
                This will debit the group pool balance and post a cash payout journal entry. It is not deducted from any individual member's contributions.
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-danger px-4"><i class="bi bi-check-circle me-1"></i>Post Withdrawal</button>
                <a href="{{ route('groups.show', $group) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

</div>
</div>
@endsection
