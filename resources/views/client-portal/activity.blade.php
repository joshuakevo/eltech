@extends('layouts.client-portal')
@section('title', 'Transactions')
@section('content')

<div class="mb-4">
    <h5 class="fw-bold mb-0">Transactions</h5>
    <div class="text-muted small">All savings activity across your linked accounts.</div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th class="ps-3">Date</th>
                    <th>Account</th>
                    <th>Type</th>
                    <th>Notes</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end pe-3">Balance After</th>
                </tr>
            </thead>
            <tbody>
            @forelse($transactions as $tx)
            <tr>
                <td class="ps-3 small">{{ $tx->transaction_date->format('d M Y') }}</td>
                <td class="small text-muted">{{ $tx->savingsAccount->account_number }}</td>
                <td>
                    @php $cls = match($tx->transaction_type) { 'deposit'=>'bg-success','withdrawal'=>'bg-danger','interest'=>'bg-primary',default=>'bg-secondary' }; @endphp
                    <span class="badge {{ $cls }}">{{ ucfirst($tx->transaction_type) }}</span>
                </td>
                <td class="text-muted small">{{ $tx->description ?? '—' }}</td>
                <td class="text-end fw-semibold font-monospace {{ $tx->transaction_type === 'withdrawal' ? 'text-danger' : 'text-success' }}">
                    {{ $tx->transaction_type === 'withdrawal' ? '-' : '+' }}{{ number_format($tx->amount, 0) }}
                </td>
                <td class="text-end pe-3 font-monospace small">{{ number_format($tx->balance_after, 0) }}</td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-center text-muted py-5">No transactions yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($transactions->hasPages())
    <div class="card-footer">{{ $transactions->withQueryString()->links() }}</div>
    @endif
</div>

@endsection
