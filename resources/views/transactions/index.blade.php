@extends('layouts.app')
@section('title', 'Journal Entries')
@section('breadcrumb')
    <li class="breadcrumb-item active">Journal Entries</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Journal Entries</h4>
    <div class="d-flex gap-2">
        <a href="{{ request()->fullUrlWithQuery(['format' => 'pdf']) }}" target="_blank" class="btn btn-outline-danger">
            <i class="bi bi-file-earmark-pdf me-1"></i> Export PDF
        </a>
        <a href="{{ route('transactions.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> New Entry</a>
    </div>
</div>
<div class="card">
    <div class="card-body pb-0">
        <form class="row g-2 mb-3" method="GET">
            <div class="col-md-3">
                <select name="account_id" class="form-select ts-select">
                    <option value="">— All accounts —</option>
                    @foreach($accounts as $acc)
                    <option value="{{ $acc->id }}" {{ (string) request('account_id') === (string) $acc->id ? 'selected' : '' }}>
                        {{ $acc->account_code }} — {{ $acc->account_name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <input type="text" name="reference" class="form-control" placeholder="Reference..." value="{{ request('reference') }}">
            </div>
            <div class="col-md-2">
                <input type="date" name="from_date" class="form-control" value="{{ $fromDate }}">
            </div>
            <div class="col-md-2">
                <input type="date" name="to_date" class="form-control" value="{{ $toDate }}">
            </div>
            <div class="col-auto"><button class="btn btn-outline-primary">Filter</button></div>
            @if(request('account_id') || request('reference') || request('from_date') || request('to_date'))
            <div class="col-auto"><a href="{{ route('transactions.index') }}" class="btn btn-outline-secondary">Clear</a></div>
            @endif
        </form>
    </div>

    @if($account)
        {{-- Ledger view: single account, opening balance carried forward + running balance --}}
        <div class="px-3 pb-2 small text-muted">
            {{ $account->account_code }} — {{ $account->account_name }} &bull; {{ \Carbon\Carbon::parse($fromDate)->format('d M Y') }} to {{ \Carbon\Carbon::parse($toDate)->format('d M Y') }}
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr>
                    <th class="ps-3">Date</th><th>Reference</th><th>Description</th>
                    <th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end pe-3">Balance</th>
                </tr></thead>
                <tbody>
                    <tr class="table-light">
                        <td class="ps-3 fw-semibold" colspan="5">Opening Balance b/f</td>
                        <td class="text-end pe-3 fw-semibold">{{ number_format($openingBalance, $dp) }}</td>
                    </tr>
                    @forelse($ledgerRows as $row)
                    @php $line = $row['line']; @endphp
                    <tr>
                        <td class="ps-3">{{ $line->transaction->date->format('d M Y') }}</td>
                        <td><a href="{{ route('transactions.show', $line->transaction) }}" class="font-monospace text-decoration-none">{{ $line->transaction->reference }}</a></td>
                        <td>{{ Str::limit($line->description ?: $line->transaction->description, 50) }}</td>
                        <td class="text-end">{{ $line->debit > 0 ? number_format($line->debit, $dp) : '' }}</td>
                        <td class="text-end">{{ $line->credit > 0 ? number_format($line->credit, $dp) : '' }}</td>
                        <td class="text-end pe-3">{{ number_format($row['balance'], $dp) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No transactions in this range.</td></tr>
                    @endforelse
                    <tr class="table-light">
                        <td class="ps-3 fw-semibold" colspan="5">Closing Balance</td>
                        <td class="text-end pe-3 fw-semibold">{{ number_format($ledgerRows->last()['balance'] ?? $openingBalance, $dp) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @else
        {{-- Plain journal browse: all accounts --}}
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr>
                    <th class="ps-3">Reference</th><th>Date</th><th>Description</th><th>Module</th><th class="text-end">Amount</th><th class="pe-3">Created By</th>
                </tr></thead>
                <tbody>
                @forelse($transactions as $txn)
                    <tr>
                        <td class="ps-3"><a href="{{ route('transactions.show', $txn) }}" class="font-monospace text-decoration-none">{{ $txn->reference }}</a></td>
                        <td>{{ $txn->date->format('d M Y') }}</td>
                        <td>{{ Str::limit($txn->description, 50) }}</td>
                        <td>@php
                            $mod = $txn->module ?? 'manual';
                            $modStyle = match($mod) {
                                'loan'    => 'background:#2563eb;color:#fff',
                                'savings' => 'background:#059669;color:#fff',
                                'fd'      => 'background:#0891b2;color:#fff',
                                'client'  => 'background:#d97706;color:#fff',
                                'payroll' => 'background:#7c3aed;color:#fff',
                                default   => 'background:#6b7280;color:#fff',
                            };
                        @endphp
                        <span class="badge" style="{{ $modStyle }}">{{ ucfirst($mod) }}</span></td>
                        <td class="text-end">{{ number_format($txn->lines_sum_debit ?? 0, $dp) }}</td>
                        <td class="pe-3 text-muted small">{{ $txn->createdBy->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No transactions found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $transactions->withQueryString()->links() }}</div>
    @endif
</div>
@endsection
