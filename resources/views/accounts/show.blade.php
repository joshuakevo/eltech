@extends('layouts.app')
@section('title', $account->account_name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('accounts.index') }}">Chart of Accounts</a></li>
    <li class="breadcrumb-item active">{{ $account->account_code }}</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">{{ $account->account_name }}</h4>
        <span class="font-monospace text-muted">{{ $account->account_code }} &bull; {{ ucfirst($account->account_type) }}</span>
    </div>
    @can('edit accounts')
    <a href="{{ route('accounts.edit', $account) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
    @endcan
</div>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2" method="GET">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">From Date</label>
                <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">To Date</label>
                <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">
            </div>
            <div class="col-auto align-self-end">
                <button class="btn btn-outline-primary">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="text-muted small">Total Debits</div>
            <div class="fw-bold fs-5 text-primary">{{ number_format($totalDebit, $dp) }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="text-muted small">Total Credits</div>
            <div class="fw-bold fs-5 text-success">{{ number_format($totalCredit, $dp) }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="text-muted small">Net Balance</div>
            <div class="fw-bold fs-5">{{ number_format(abs($totalDebit - $totalCredit), $dp) }}</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">Ledger Entries</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th class="ps-3">Date</th><th>Reference</th><th>Description</th>
                <th class="text-end">Debit</th><th class="text-end pe-3">Credit</th>
            </tr></thead>
            <tbody>
            @forelse($lines as $line)
                <tr>
                    <td class="ps-3">{{ $line->transaction->date->format('d M Y') }}</td>
                    <td><a href="{{ route('transactions.show', $line->transaction) }}" class="font-monospace text-decoration-none">{{ $line->transaction->reference }}</a></td>
                    <td>{{ $line->description ?? $line->transaction->description }}</td>
                    <td class="text-end">{{ $line->debit > 0 ? number_format($line->debit, $dp) : '' }}</td>
                    <td class="text-end pe-3">{{ $line->credit > 0 ? number_format($line->credit, $dp) : '' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No ledger entries.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $lines->withQueryString()->links() }}</div>
</div>
@endsection
