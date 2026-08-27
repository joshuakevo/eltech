@extends('layouts.app')
@section('title', 'Run Loans')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('loans.index') }}">Loans</a></li>
    <li class="breadcrumb-item active">Run Loans</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Run Loans</h4>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small text-uppercase">Principal Balance</div>
            <div class="fs-4 fw-bold text-warning">{{ number_format($totalPrincipalBalance, $dp) }}</div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small text-uppercase">Interest Balance</div>
            <div class="fs-4 fw-bold text-warning">{{ number_format($totalInterestBalance, $dp) }}</div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small text-uppercase">Loans Due — {{ $date->format('jS') }}</div>
            <div class="fs-4 fw-bold">{{ number_format($totalCount) }}</div>
        </div></div>
    </div>
</div>

<div class="card">
    <div class="card-body pb-0">
        <form class="row g-2 mb-3" method="GET">
            <div class="col-auto">
                <label class="col-form-label small text-muted">Anniversary date</label>
            </div>
            <div class="col-auto"><input type="date" name="date" class="form-control" value="{{ $date->toDateString() }}"></div>
            <div class="col-md-3"><input type="text" name="search" class="form-control" placeholder="Loan # or client name..." value="{{ request('search') }}"></div>
            <div class="col-auto"><button class="btn btn-outline-primary">View</button></div>
            <div class="col-auto"><a href="{{ route('loans.run') }}" class="btn btn-outline-secondary">Today</a></div>
        </form>
        <p class="small text-muted">
            Active loans whose disbursement day-of-month is the <strong>{{ $date->format('jS') }}</strong> —
            every installment for these loans falls due on that day each month.
        </p>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th class="ps-3">Loan #</th><th>Client</th>
                <th class="text-end">Outstanding Principal</th><th class="text-end">Outstanding Interest</th>
                <th class="text-end">Installment</th><th class="text-end">Savings Balance</th>
                <th>Last Date Recovered</th><th>Next Due Date</th><th class="pe-3">Actions</th>
            </tr></thead>
            <tbody>
            @forelse($loans as $loan)
                <tr>
                    <td class="ps-3 font-monospace"><a href="{{ route('loans.show', $loan) }}" class="text-decoration-none">{{ $loan->loan_number }}</a></td>
                    <td>
                        @if($loan->client)
                            <a href="{{ route('clients.show', $loan->client) }}" class="text-decoration-none">{{ $loan->client->name }}</a>
                        @else
                            <span class="text-muted fst-italic">Deleted client</span>
                        @endif
                    </td>
                    <td class="text-end fw-semibold">{{ number_format($loan->outstanding_principal, $dp) }}</td>
                    <td class="text-end fw-semibold">{{ number_format($loan->outstanding_interest, $dp) }}</td>
                    <td class="text-end">
                        @if($loan->next_schedule)
                            {{ number_format($loan->next_schedule->total_due, $dp) }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-end">{{ number_format($loan->savings_balance, $dp) }}</td>
                    <td class="small">
                        @if($loan->last_recovered)
                            {{ \Illuminate\Support\Carbon::parse($loan->last_recovered)->format('d M Y') }}
                        @else
                            <span class="text-muted fst-italic">Never</span>
                        @endif
                    </td>
                    <td class="small">
                        @if($loan->next_schedule)
                            {{ \Illuminate\Support\Carbon::parse($loan->next_schedule->due_date)->format('d M Y') }}
                        @else
                            <span class="text-muted fst-italic">Fully scheduled/paid</span>
                        @endif
                    </td>
                    <td class="pe-3 text-nowrap">
                        <a href="{{ route('loans.show', $loan) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                        @can('repay loans')
                        <a href="{{ route('loans.repay-form', $loan) }}" class="btn btn-sm btn-success"><i class="bi bi-cash"></i></a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No active loans due on the {{ $date->format('jS') }}.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
