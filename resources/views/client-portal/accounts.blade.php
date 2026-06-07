@extends('layouts.client-portal')
@section('title', 'My Statements')
@section('content')

<div class="mb-4 d-flex align-items-center gap-3">
    <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center fw-bold text-primary"
         style="width:42px;height:42px;font-size:.9rem;flex-shrink:0">
        {{ strtoupper(substr($client->name, 0, 1)) }}
    </div>
    <div>
        <h5 class="fw-bold mb-0">{{ $client->name }}</h5>
        <div class="text-muted small">{{ $client->client_number }} &bull; Member since {{ $client->joining_date?->format('d M Y') ?? '—' }}</div>
    </div>
</div>

@php $loop = (object)['last' => true]; /* single client, no loop needed */ @endphp
<div class="mb-5">
    <div>

    {{-- Savings --}}
    <div class="card mb-3">
        <div class="card-header py-2 d-flex align-items-center gap-2">
            <i class="bi bi-piggy-bank text-success"></i> Savings Accounts
        </div>
        <div class="card-body p-0">
            @if($client->savingsAccounts->isEmpty())
                <p class="text-muted p-3 mb-0 small">No savings accounts.</p>
            @else
            <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th class="ps-3">Account No.</th><th>Product</th><th class="text-end">Balance</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                @foreach($client->savingsAccounts as $sa)
                <tr>
                    <td class="ps-3 fw-semibold">{{ $sa->account_number }}</td>
                    <td>{{ $sa->product->name }}</td>
                    <td class="text-end fw-bold text-primary font-monospace">{{ number_format($sa->balance, 0) }}</td>
                    <td><span class="badge badge-status-{{ $sa->status }}">{{ ucfirst($sa->status) }}</span></td>
                    <td class="text-end pe-3">
                        <a href="{{ route('client-portal.savings', $sa) }}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye me-1"></i>Statement
                        </a>
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
            </div>
            @endif
        </div>
    </div>

    {{-- Loans --}}
    <div class="card mb-3">
        <div class="card-header py-2 d-flex align-items-center gap-2">
            <i class="bi bi-cash-stack text-primary"></i> Loans
        </div>
        <div class="card-body p-0">
            @if($client->loans->isEmpty())
                <p class="text-muted p-3 mb-0 small">No loans.</p>
            @else
            <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th class="ps-3">Loan No.</th><th>Product</th><th class="text-end">Amount</th><th class="text-end">Balance</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                @foreach($client->loans as $loan)
                <tr>
                    <td class="ps-3 fw-semibold">{{ $loan->loan_number }}</td>
                    <td>{{ $loan->product->name }}</td>
                    <td class="text-end font-monospace">{{ number_format($loan->principal, 0) }}</td>
                    <td class="text-end fw-bold font-monospace {{ $loan->total_outstanding > 0 ? 'text-danger' : 'text-muted' }}">{{ number_format($loan->total_outstanding, 0) }}</td>
                    <td><span class="badge badge-status-{{ $loan->status }}">{{ ucfirst($loan->status) }}</span></td>
                    <td class="text-end pe-3">
                        <a href="{{ route('client-portal.loan', $loan) }}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye me-1"></i>Statement
                        </a>
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
            </div>
            @endif
        </div>
    </div>

    {{-- Fixed Deposits --}}
    <div class="card mb-3">
        <div class="card-header py-2 d-flex align-items-center gap-2">
            <i class="bi bi-safe text-warning"></i> Fixed Deposits
        </div>
        <div class="card-body p-0">
            @if($client->fixedDeposits->isEmpty())
                <p class="text-muted p-3 mb-0 small">No fixed deposits.</p>
            @else
            <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th class="ps-3">FD No.</th><th>Product</th><th class="text-end">Principal</th><th class="text-end">At Maturity</th><th>Matures</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                @foreach($client->fixedDeposits as $fd)
                <tr>
                    <td class="ps-3 fw-semibold">{{ $fd->deposit_number }}</td>
                    <td>{{ $fd->product->name }}</td>
                    <td class="text-end font-monospace">{{ number_format($fd->principal, 0) }}</td>
                    <td class="text-end fw-bold font-monospace text-warning">{{ number_format($fd->maturity_amount, 0) }}</td>
                    <td class="small">{{ $fd->maturity_date->format('d M Y') }}</td>
                    <td><span class="badge badge-status-{{ $fd->status }}">{{ ucfirst($fd->status) }}</span></td>
                    <td class="text-end pe-3">
                        <a href="{{ route('client-portal.fd', $fd) }}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye me-1"></i>Details
                        </a>
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
            </div>
            @endif
        </div>
    </div>
</div>
</div>

@endsection
