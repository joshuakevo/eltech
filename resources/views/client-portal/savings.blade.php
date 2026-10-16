@extends('layouts.client-portal')
@section('title', $savingsAccount->account_number)
@section('content')

<div class="mb-3">
    <a href="{{ route('client-portal.accounts') }}" class="text-muted text-decoration-none small">
        <i class="bi bi-arrow-left me-1"></i>My Statements
    </a>
</div>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h5 class="fw-bold mb-0">{{ $savingsAccount->account_number }}</h5>
        <span class="text-muted small">{{ $savingsAccount->product->name }}</span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge badge-status-{{ $savingsAccount->status }} px-3 py-2 rounded-pill">
            {{ ucfirst($savingsAccount->status) }}
        </span>
        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#savingsStatementModal">
            <i class="bi bi-file-earmark-text me-1"></i>Export statement
        </button>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card text-center">
            <div class="text-muted small mb-1">Current Balance</div>
            <div class="fw-bold fs-4 text-success">{{ number_format($savingsAccount->balance, 2) }}</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card text-center">
            <div class="text-muted small mb-1">Account Opened</div>
            <div class="fw-semibold">{{ $savingsAccount->opened_date?->format('d M Y') }}</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card text-center">
            <div class="text-muted small mb-1">Interest Rate (p.a.)</div>
            <div class="fw-semibold">{{ $savingsAccount->product->interest_rate ?? '—' }}%</div>
        </div>
    </div>
</div>

@include('savings._transactions', ['saving' => $savingsAccount])

@include('savings._statement-modal', ['saving' => $savingsAccount, 'statementUrl' => route('client-portal.savings.pdf', $savingsAccount)])
@endsection
