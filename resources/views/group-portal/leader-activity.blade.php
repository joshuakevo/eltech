@extends('layouts.group-portal')
@section('title', 'Recent Activity')
@section('content')

<div class="mb-4">
    <h5 class="fw-bold mb-0">Recent Activity</h5>
    <div class="text-muted small">All transactions for {{ $group->name }}</div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th class="ps-3">Date</th>
                    <th>Member</th>
                    <th>Type</th>
                    <th>Notes</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end pe-3">Balance After</th>
                </tr>
            </thead>
            <tbody>
            @forelse($transactions as $tx)
            <tr>
                <td class="ps-3">
                    {{ $tx->transaction_date->format('d M Y') }}
                </td>
                <td class="fw-semibold">{{ $tx->member?->name ?? '—' }}</td>
                <td>
                    @php
                        $cls = match($tx->type) {
                            'deposit'    => 'bg-success',
                            'withdrawal' => 'bg-danger',
                            'interest'   => 'bg-primary',
                            default      => 'bg-secondary',
                        };
                    @endphp
                    <span class="badge {{ $cls }}">{{ $tx->type === 'transfer_out' ? 'Transfer Out' : ucfirst($tx->type) }}</span>
                </td>
                <td class="text-muted small">{{ $tx->notes ?? '—' }}</td>
                @php $isOut = in_array($tx->type, ['withdrawal','transfer_out']); @endphp
                <td class="text-end font-monospace fw-semibold {{ $isOut ? 'text-danger' : 'text-success' }}">
                    {{ $isOut ? '-' : '+' }}{{ number_format($tx->amount, 0) }}
                </td>
                <td class="text-end font-monospace pe-3">{{ number_format($tx->balance_after, 0) }}</td>
            </tr>
            @empty
            <tr><td colspan="6" class="text-center text-muted py-4">No transactions yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($transactions->hasPages())
    <div class="card-footer">{{ $transactions->withQueryString()->links() }}</div>
    @endif
</div>
@endsection
