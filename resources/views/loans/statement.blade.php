@extends('layouts.app')
@section('title', 'Loan Statement — ' . $loan->loan_number)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('loans.index') }}">Loans</a></li>
    <li class="breadcrumb-item"><a href="{{ route('loans.show', $loan) }}">{{ $loan->loan_number }}</a></li>
    <li class="breadcrumb-item active">Statement</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-semibold mb-0">Loan Statement — <span class="font-monospace">{{ $loan->loan_number }}</span></h6>
    <a href="{{ route('loans.statement-pdf', $loan) }}" class="btn btn-danger btn-sm" target="_blank">
        <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
    </a>
</div>

@include('loans._statement-body')
@endsection
