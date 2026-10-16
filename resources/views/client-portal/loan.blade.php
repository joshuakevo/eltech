@extends('layouts.client-portal')
@section('title', $loan->loan_number)
@section('content')

<div class="mb-3">
    <a href="{{ route('client-portal.accounts') }}" class="text-muted text-decoration-none small">
        <i class="bi bi-arrow-left me-1"></i>My Statements
    </a>
</div>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h5 class="fw-bold mb-0">{{ $loan->loan_number }}</h5>
        <span class="text-muted small">{{ $loan->product->name }}</span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge badge-status-{{ $loan->status }} px-3 py-2 rounded-pill">
            {{ ucfirst($loan->status) }}
        </span>
        <a href="{{ route('client-portal.loan.pdf', $loan) }}" class="btn btn-sm btn-outline-danger" target="_blank">
            <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
        </a>
    </div>
</div>

@include('loans._statement-body')
@endsection
