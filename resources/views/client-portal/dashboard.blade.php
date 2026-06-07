@extends('layouts.client-portal')
@section('title', 'My Statements')
@section('content')

<div class="mb-4">
    <h5 class="fw-bold mb-0">Welcome, {{ auth()->user()->name }}</h5>
    @if($clients->count() > 1)
    <span class="text-muted small">You have access to {{ $clients->count() }} accounts below.</span>
    @endif
</div>

@foreach($clients as $client)
<div class="mb-5">

    {{-- Client heading --}}
    <div class="d-flex align-items-center gap-2 mb-3">
        <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width:36px;height:36px;flex-shrink:0">
            <i class="bi bi-person-fill text-primary"></i>
        </div>
        <div>
            <div class="fw-bold">{{ $client->name }}</div>
            <div class="text-muted small">{{ $client->client_number }} &bull; Member since {{ $client->joining_date?->format('d M Y') }}</div>
        </div>
    </div>

    {{-- Savings Accounts --}}
    <div class="card mb-3">
        <div class="card-header bg-white py-2 d-flex align-items-center gap-2">
            <i class="bi bi-piggy-bank text-success"></i>
            <span class="fw-semibold">Savings Accounts</span>
        </div>
        <div class="card-body p-0">
            @if($client->savingsAccounts->isEmpty())
                <p class="text-muted p-3 mb-0 small">No savings accounts.</p>
            @else
            <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Account No.</th>
                        <th>Product</th>
                        <th class="text-end">Balance</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($client->savingsAccounts as $sa)
                    <tr>
                        <td class="fw-semibold">{{ $sa->account_number }}</td>
                        <td>{{ $sa->product->name }}</td>
                        <td class="text-end fw-semibold">{{ number_format($sa->balance, 2) }}</td>
                        <td><span class="badge badge-status-{{ $sa->status }}">{{ ucfirst($sa->status) }}</span></td>
                        <td class="text-end">
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
        <div class="card-header bg-white py-2 d-flex align-items-center gap-2">
            <i class="bi bi-cash-stack text-primary"></i>
            <span class="fw-semibold">Loans</span>
        </div>
        <div class="card-body p-0">
            @if($client->loans->isEmpty())
                <p class="text-muted p-3 mb-0 small">No loans.</p>
            @else
            <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Loan No.</th>
                        <th>Product</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Balance</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($client->loans as $loan)
                    <tr>
                        <td class="fw-semibold">{{ $loan->loan_number }}</td>
                        <td>{{ $loan->product->name }}</td>
                        <td class="text-end">{{ number_format($loan->principal, 2) }}</td>
                        <td class="text-end fw-semibold">{{ number_format($loan->total_outstanding, 2) }}</td>
                        <td><span class="badge badge-status-{{ $loan->status }}">{{ ucfirst($loan->status) }}</span></td>
                        <td class="text-end">
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
        <div class="card-header bg-white py-2 d-flex align-items-center gap-2">
            <i class="bi bi-safe text-warning"></i>
            <span class="fw-semibold">Fixed Deposits</span>
        </div>
        <div class="card-body p-0">
            @if($client->fixedDeposits->isEmpty())
                <p class="text-muted p-3 mb-0 small">No fixed deposits.</p>
            @else
            <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>FD No.</th>
                        <th>Product</th>
                        <th class="text-end">Principal</th>
                        <th class="text-end">At Maturity</th>
                        <th>Matures</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($client->fixedDeposits as $fd)
                    <tr>
                        <td class="fw-semibold">{{ $fd->deposit_number }}</td>
                        <td>{{ $fd->product->name }}</td>
                        <td class="text-end">{{ number_format($fd->principal, 2) }}</td>
                        <td class="text-end fw-semibold">{{ number_format($fd->maturity_amount, 2) }}</td>
                        <td>{{ $fd->maturity_date->format('d M Y') }}</td>
                        <td><span class="badge badge-status-{{ $fd->status }}">{{ ucfirst($fd->status) }}</span></td>
                        <td class="text-end">
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
@if(!$loop->last)<hr class="my-4">@endif
@endforeach

@endsection
