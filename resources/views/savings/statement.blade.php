@extends('layouts.app')
@section('title', 'Savings Statement — ' . $account->account_number)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('savings.index') }}">Savings</a></li>
    <li class="breadcrumb-item"><a href="{{ route('savings.show', $account) }}">{{ $account->account_number }}</a></li>
    <li class="breadcrumb-item active">Statement</li>
@endsection
@section('content')

{{-- Header bar with date filter and PDF export --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('savings.statement', $account) }}" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-1">From Date</label>
                <input type="date" name="from_date" class="form-control form-control-sm" value="{{ $fromDate }}">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">To Date</label>
                <input type="date" name="to_date" class="form-control form-control-sm" value="{{ $toDate ?? today()->toDateString() }}">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
            </div>
            <div class="col-auto ms-auto">
                <a href="{{ route('savings.statement-pdf', $account) }}?from_date={{ $fromDate }}&to_date={{ $toDate }}"
                   class="btn btn-danger btn-sm" target="_blank">
                    <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
                </a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header small fw-semibold py-2">Client Information</div>
            <div class="card-body py-2">
                <table class="table table-sm table-borderless mb-0 small">
                    <tr><td class="text-muted w-40">Full Name</td><td class="fw-semibold">{{ $account->client->name }}</td></tr>
                    <tr><td class="text-muted">Client No.</td><td class="fw-semibold">{{ $account->client->client_number }}</td></tr>
                    @if($account->client->phone)
                    <tr><td class="text-muted">Phone</td><td>{{ $account->client->phone }}</td></tr>
                    @endif
                    @if($account->client->email)
                    <tr><td class="text-muted">Email</td><td>{{ $account->client->email }}</td></tr>
                    @endif
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header small fw-semibold py-2">Account Information</div>
            <div class="card-body py-2">
                <table class="table table-sm table-borderless mb-0 small">
                    <tr><td class="text-muted w-40">Account No.</td><td class="fw-semibold font-monospace">{{ $account->account_number }}</td></tr>
                    <tr><td class="text-muted">Product</td><td>{{ $account->product->name }}</td></tr>
                    <tr><td class="text-muted">Date Opened</td><td>{{ $account->opened_date->format('d M Y') }}</td></tr>
                    <tr><td class="text-muted">Status</td><td><span class="badge bg-{{ $account->status === 'active' ? 'success' : 'secondary' }}">{{ strtoupper($account->status) }}</span></td></tr>
                    <tr><td class="text-muted">Interest Rate</td><td>{{ $account->product->interest_rate ?? 0 }}% p.a.</td></tr>
                    <tr><td class="text-muted">Current Balance</td><td class="fw-bold text-success fs-6">{{ number_format($account->balance, $dp) }}</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Period Summary --}}
@php
    $totalDeposits    = $transactions->whereIn('transaction_type', ['deposit', 'interest'])->sum('amount');
    $totalWithdrawals = $transactions->whereIn('transaction_type', ['withdrawal', 'fee', 'loan_repayment'])->sum('amount');
@endphp
<div class="row g-3 mb-3">
    <div class="col">
        <div class="card text-center border-success">
            <div class="card-body py-2">
                <div class="small text-muted">Total Deposits</div>
                <div class="fw-bold fs-6 text-success">{{ number_format($totalDeposits, $dp) }}</div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card text-center border-danger">
            <div class="card-body py-2">
                <div class="small text-muted">Total Withdrawals</div>
                <div class="fw-bold fs-6 text-danger">{{ number_format($totalWithdrawals, $dp) }}</div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="small text-muted">Transactions</div>
                <div class="fw-bold fs-6">{{ $transactions->count() }}</div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="small text-muted">Closing Balance</div>
                <div class="fw-bold fs-6">{{ number_format($account->balance, $dp) }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Transaction History --}}
<div class="card">
    <div class="card-header small fw-semibold py-2 d-flex justify-content-between">
        <span>Transaction History</span>
        @if($fromDate || $toDate)
        <span class="text-muted">
            {{ $fromDate ? \Carbon\Carbon::parse($fromDate)->format('d M Y') : 'Beginning' }}
            — {{ $toDate ? \Carbon\Carbon::parse($toDate)->format('d M Y') : 'Today' }}
        </span>
        @endif
    </div>
    <div class="card-body p-0">
        @if($transactions->isEmpty())
            <p class="text-muted small p-3 mb-0">No transactions in this period.</p>
        @else
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Reference</th>
                    <th class="text-end text-danger">Debit (−)</th>
                    <th class="text-end text-success">Credit (+)</th>
                    <th class="text-end fw-semibold">Balance</th>
                </tr>
            </thead>
            @php $debitTotal = 0; $creditTotal = 0; @endphp
            <tbody class="small">
            @foreach($transactions as $i => $t)
            @php
                $isCredit = in_array($t->transaction_type, ['deposit', 'interest']) ||
                            ($t->transaction_type === 'transfer' && $t->balance_after > $t->balance_before);
                if ($isCredit) { $creditTotal += $t->amount; } else { $debitTotal += $t->amount; }
            @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $t->transaction_date->format('d M Y') }}</td>
                    <td>
                        <span class="badge bg-{{ $isCredit ? 'success' : 'danger' }} bg-opacity-10 text-{{ $isCredit ? 'success' : 'danger' }}">
                            {{ ucfirst(str_replace('_', ' ', $t->transaction_type)) }}
                        </span>
                    </td>
                    <td class="text-muted">{{ $t->description ?? '—' }}</td>
                    <td class="text-muted font-monospace small">{{ $t->reference ?? '—' }}</td>
                    <td class="text-end text-danger">
                        @if(!$isCredit) {{ number_format($t->amount, $dp) }} @else — @endif
                    </td>
                    <td class="text-end text-success">
                        @if($isCredit) {{ number_format($t->amount, $dp) }} @else — @endif
                    </td>
                    <td class="text-end fw-semibold">{{ number_format($t->balance_after, $dp) }}</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot class="table-light fw-bold small">
                <tr>
                    <td colspan="5">Totals</td>
                    <td class="text-end text-danger">{{ number_format($debitTotal, $dp) }}</td>
                    <td class="text-end text-success">{{ number_format($creditTotal, $dp) }}</td>
                    <td class="text-end text-primary">{{ number_format($account->balance, $dp) }}</td>
                </tr>
            </tfoot>
        </table>
        @endif
    </div>
</div>
<p class="text-muted small mt-2 mb-0">Statement generated: {{ now()->format('d M Y H:i') }}</p>
@endsection
