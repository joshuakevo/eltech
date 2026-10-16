{{-- Savings transaction history ($saving with transactions oldest first) — staff page and member portal --}}
<div class="card">
    <div class="card-header">Transaction History</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">#</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Reference</th>
                    <th class="text-end text-danger">Debit (&minus;)</th>
                    <th class="text-end text-success">Credit (+)</th>
                    <th class="text-end pe-3 fw-semibold">Balance</th>
                </tr>
            </thead>
            @php $debitTotal = 0; $creditTotal = 0; @endphp
            <tbody class="small">
            @forelse($saving->transactions as $i => $txn)
                @php
                    $isCredit = in_array($txn->transaction_type, ['deposit', 'interest']) ||
                                ($txn->transaction_type === 'transfer' && $txn->balance_after > $txn->balance_before);
                    if ($isCredit) { $creditTotal += $txn->amount; } else { $debitTotal += $txn->amount; }
                @endphp
                <tr>
                    <td class="ps-3">{{ $i + 1 }}</td>
                    <td>{{ $txn->transaction_date->format('d M Y') }}</td>
                    <td>
                        <span class="badge bg-{{ $isCredit ? 'success' : 'danger' }} bg-opacity-10 text-{{ $isCredit ? 'success' : 'danger' }}">
                            {{ ucfirst(str_replace('_',' ',$txn->transaction_type)) }}
                        </span>
                    </td>
                    <td class="text-muted">{{ $txn->description ?? '—' }}</td>
                    <td class="text-muted font-monospace small">{{ $txn->reference ?? '—' }}</td>
                    <td class="text-end text-danger">
                        @if(!$isCredit) {{ number_format($txn->amount, $dp) }} @else — @endif
                    </td>
                    <td class="text-end text-success">
                        @if($isCredit) {{ number_format($txn->amount, $dp) }} @else — @endif
                    </td>
                    <td class="text-end pe-3 fw-semibold {{ $txn->balance_after < 0 ? 'text-overdrawn' : '' }}">{{ number_format($txn->balance_after, $dp) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No transactions.</td></tr>
            @endforelse
            </tbody>
            @if($saving->transactions->isNotEmpty())
            <tfoot class="table-light fw-bold small">
                <tr>
                    <td colspan="5" class="ps-3">Totals</td>
                    <td class="text-end text-danger">{{ number_format($debitTotal, $dp) }}</td>
                    <td class="text-end text-success">{{ number_format($creditTotal, $dp) }}</td>
                    <td class="text-end pe-3 {{ $saving->is_overdrawn ? 'text-overdrawn' : '' }}">{{ number_format($saving->balance, $dp) }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
