@extends('layouts.client-portal')
@section('title', 'Transactions')
@section('content')

<div class="mb-4">
    <h5 class="fw-bold mb-0">Transaction History</h5>
    <div class="text-muted small">All savings activity across your accounts, newest first.</div>
</div>

<div class="card">
    <div class="card-header">Transaction History</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Date</th>
                    <th>Account</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Reference</th>
                    <th class="text-end text-danger">Debit (&minus;)</th>
                    <th class="text-end text-success">Credit (+)</th>
                    <th class="text-end pe-3 fw-semibold">Balance</th>
                </tr>
            </thead>
            <tbody class="small">
            @forelse($transactions as $txn)
                @php
                    $isCredit = in_array($txn->transaction_type, ['deposit', 'interest']) ||
                                ($txn->transaction_type === 'transfer' && $txn->balance_after > $txn->balance_before);
                @endphp
                <tr>
                    <td class="ps-3 text-nowrap">{{ $txn->transaction_date->format('d M Y') }}</td>
                    <td class="text-nowrap">
                        <a href="{{ route('client-portal.savings', $txn->savingsAccount) }}" class="text-decoration-none font-monospace">{{ $txn->savingsAccount->account_number }}</a>
                    </td>
                    <td>
                        <span class="badge bg-{{ $isCredit ? 'success' : 'danger' }} bg-opacity-10 text-{{ $isCredit ? 'success' : 'danger' }}">
                            {{ ucfirst(str_replace('_',' ',$txn->transaction_type)) }}
                        </span>
                    </td>
                    <td class="text-muted">{{ $txn->description ?? '—' }}</td>
                    <td class="text-muted font-monospace small">{{ $txn->reference ?? '—' }}</td>
                    <td class="text-end text-danger">@if(!$isCredit) {{ number_format($txn->amount, $dp) }} @else — @endif</td>
                    <td class="text-end text-success">@if($isCredit) {{ number_format($txn->amount, $dp) }} @else — @endif</td>
                    <td class="text-end pe-3 fw-semibold {{ $txn->balance_after < 0 ? 'text-danger' : '' }}">{{ number_format($txn->balance_after, $dp) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No transactions.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($transactions->hasPages())
    <div class="card-footer bg-white">{{ $transactions->withQueryString()->links() }}</div>
    @endif
</div>

@endsection
