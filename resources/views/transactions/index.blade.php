@extends('layouts.app')
@section('title', 'Journal Entries')
@section('breadcrumb')
    <li class="breadcrumb-item active">Journal Entries</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Journal Entries</h4>
    <a href="{{ route('transactions.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> New Entry</a>
</div>
<div class="card">
    <div class="card-body pb-0">
        <form class="row g-2 mb-3" method="GET">
            <div class="col-md-3">
                <input type="text" name="reference" class="form-control" placeholder="Reference..." value="{{ request('reference') }}">
            </div>
            <div class="col-md-3">
                <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}">
            </div>
            <div class="col-md-3">
                <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">
            </div>
            <div class="col-auto"><button class="btn btn-outline-primary">Filter</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th class="ps-3">Reference</th><th>Date</th><th>Description</th><th>Module</th><th class="text-end">Amount</th><th class="pe-3">Created By</th>
            </tr></thead>
            <tbody>
            @forelse($transactions as $txn)
                <tr>
                    <td class="ps-3"><a href="{{ route('transactions.show', $txn) }}" class="font-monospace text-decoration-none">{{ $txn->reference }}</a></td>
                    <td>{{ $txn->date->format('d M Y') }}</td>
                    <td>{{ Str::limit($txn->description, 50) }}</td>
                    <td>@php
                        $mod = $txn->module ?? 'manual';
                        $modStyle = match($mod) {
                            'loan'    => 'background:#2563eb;color:#fff',
                            'savings' => 'background:#059669;color:#fff',
                            'fd'      => 'background:#0891b2;color:#fff',
                            'client'  => 'background:#d97706;color:#fff',
                            'payroll' => 'background:#7c3aed;color:#fff',
                            default   => 'background:#6b7280;color:#fff',
                        };
                    @endphp
                    <span class="badge" style="{{ $modStyle }}">{{ ucfirst($mod) }}</span></td>
                    <td class="text-end">{{ number_format($txn->lines_sum_debit ?? 0, $dp) }}</td>
                    <td class="pe-3 text-muted small">{{ $txn->createdBy->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No transactions found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $transactions->withQueryString()->links() }}</div>
</div>
@endsection
