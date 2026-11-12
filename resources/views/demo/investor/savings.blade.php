@extends('demo.investor.layout')
@section('title', $savings->account_number)
@section('content')
<div class="mb-3">
    <a href="{{ route('demo.investor.statements') }}" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>My Statements</a>
</div>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h5 class="fw-bold mb-0">{{ $savings->account_number }}</h5>
        <span class="text-muted small">{{ $savings->product->name }}</span>
    </div>
    <span class="badge badge-status-active px-3 py-2 rounded-pill">Active</span>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-4"><div class="stat-card text-center">
        <div class="text-muted small mb-1">Current Balance</div>
        <div class="fw-bold fs-4 text-success">{{ number_format($savings->balance, 2) }}</div>
    </div></div>
    <div class="col-sm-4"><div class="stat-card text-center">
        <div class="text-muted small mb-1">Account Opened</div>
        <div class="fw-semibold">{{ $savings->opened_date->format('d M Y') }}</div>
    </div></div>
    <div class="col-sm-4"><div class="stat-card text-center">
        <div class="text-muted small mb-1">Interest Rate (p.a.)</div>
        <div class="fw-semibold">{{ $savings->product->interest_rate }}%</div>
    </div></div>
</div>

@include('savings._transactions', ['saving' => $savings, 'dp' => 2])
@endsection
