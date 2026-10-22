@extends('layouts.app')
@section('title', 'Opening balance check')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('savings.index') }}">Savings</a></li>
    <li class="breadcrumb-item active">Opening balance check</li>
@endsection
@section('content')
<div class="page-header">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-link-45deg me-1 text-primary"></i>Savings opening balance check</h4>
        <div class="text-muted small">Opening balances from the transfer were brought in as a statement row and a separate journal that were not linked, so deleting the journal left the row on the statement.</div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <span><strong>{{ $linkable->count() }}</strong> opening row(s) can be linked to their journal</span>
        @if($linkable->isNotEmpty())
        <form method="POST" action="{{ route('savings.opening-check.link') }}">@csrf<button class="btn btn-sm btn-primary"><i class="bi bi-link me-1"></i>Link all</button></form>
        @endif
    </div>
    <div class="card-body small text-muted">Same account number and amount. After linking, deleting or reversing an opening journal also removes its statement row — so this can't happen again. Nothing else changes.</div>
</div>

<div class="card">
    <div class="card-header"><strong>{{ $orphans->count() }}</strong> leftover opening row(s) — the journal behind them no longer exists</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light"><tr><th class="ps-3">Account</th><th>Member</th><th>Date</th><th>Description</th><th class="text-end">Amount</th><th class="text-end">Account balance</th><th class="pe-3"></th></tr></thead>
            <tbody class="small">
            @forelse($orphans as $r)
                <tr>
                    <td class="ps-3 font-monospace"><a href="{{ route('savings.show', $r->savings_account_id) }}">{{ $r->savingsAccount->account_number }}</a></td>
                    <td>{{ $r->savingsAccount->client->name ?? '—' }}</td>
                    <td>{{ $r->transaction_date->format('d M Y') }}</td>
                    <td>{{ $r->description }}</td>
                    <td class="text-end {{ $r->transaction_type === 'withdrawal' ? 'text-danger' : 'text-success' }}">{{ $r->transaction_type === 'withdrawal' ? '−' : '+' }}{{ number_format(abs($r->amount), 0) }}</td>
                    <td class="text-end">{{ number_format($r->savingsAccount->balance, 0) }}</td>
                    <td class="pe-3 text-end">
                        <form method="POST" action="{{ route('savings.opening-check.remove', $r) }}" onsubmit="return confirm('Remove this opening balance row from {{ $r->savingsAccount->account_number }}? Its journal is already gone; the running balance is rebuilt.')">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger py-0"><i class="bi bi-trash"></i> Remove</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-3">None — every opening row has its journal.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
