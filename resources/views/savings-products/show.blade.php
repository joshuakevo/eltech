@extends('layouts.app')
@section('title', $savingsProduct->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('savings-products.index') }}">Savings Products</a></li>
    <li class="breadcrumb-item active">{{ $savingsProduct->name }}</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">{{ $savingsProduct->name }}</h4>
    <a href="{{ route('savings-products.edit', $savingsProduct) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="stat-card text-center"><div class="text-muted small">Total Accounts</div><div class="fw-bold fs-4">{{ $stats['total_accounts'] }}</div></div></div>
    <div class="col-md-4"><div class="stat-card text-center"><div class="text-muted small">Active Accounts</div><div class="fw-bold fs-4 text-success">{{ $stats['active_accounts'] }}</div></div></div>
    <div class="col-md-4"><div class="stat-card text-center"><div class="text-muted small">Total Balance</div><div class="fw-bold fs-5">{{ number_format($stats['total_balance'], $dp) }}</div></div></div>
</div>
<div class="card">
    <div class="card-body">
        <dl class="row">
            <dt class="col-4 fw-normal text-muted">Minimum Balance</dt><dd class="col-8">{{ number_format($savingsProduct->minimum_balance, $dp) }}</dd>
            <dt class="col-4 fw-normal text-muted">Withdrawal Fee</dt><dd class="col-8">{{ number_format($savingsProduct->withdrawal_fee, $dp) }}</dd>
            <dt class="col-4 fw-normal text-muted">Interest Method</dt>
            <dd class="col-8">
                @if($savingsProduct->interest_method === 'tiered')
                    <span class="badge bg-primary-subtle text-primary">Graduated Tiers</span>
                    <span class="text-muted small">— see <a href="{{ route('savings-interest-tiers.edit') }}">Savings Interest Tiers</a></span>
                @else
                    <span class="badge bg-secondary-subtle text-secondary">Flat Rate</span>
                    {{ $savingsProduct->interest_rate }}% p.a.
                @endif
            </dd>
            <dt class="col-4 fw-normal text-muted">Interest Frequency</dt><dd class="col-8">{{ ucfirst($savingsProduct->interest_frequency) }}</dd>
            <dt class="col-4 fw-normal text-muted">Liability Account</dt><dd class="col-8">{{ $savingsProduct->liabilityAccount?->account_code }} — {{ $savingsProduct->liabilityAccount?->account_name ?? '—' }}</dd>
            <dt class="col-4 fw-normal text-muted">Interest Expense</dt><dd class="col-8">{{ $savingsProduct->interestExpenseAccount?->account_code }} — {{ $savingsProduct->interestExpenseAccount?->account_name ?? '—' }}</dd>
        </dl>
    </div>
</div>
@endsection
