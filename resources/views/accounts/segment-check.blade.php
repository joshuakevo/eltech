@extends('layouts.app')
@section('title', 'Segment Check')
@section('breadcrumb')
    <li class="breadcrumb-item">Accounting</li>
    <li class="breadcrumb-item active">Segment Check</li>
@endsection
@section('content')
<div class="page-header">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-tags me-1 text-primary"></i>Segment Check</h4>
        <div class="text-muted small">Income and expense lines that don't count under any segment. A line belongs to a segment through its <strong>member</strong>, else the <strong>segment picked on the line</strong>, else its <strong>account's segment</strong> (Chart of Accounts).</div>
    </div>
</div>

<form method="GET" class="row g-2 align-items-end mb-3">
    <div class="col-6 col-md-2"><label class="form-label small mb-1">From</label><input type="date" name="from_date" class="form-control" value="{{ $from }}"></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1">To</label><input type="date" name="to_date" class="form-control" value="{{ $to }}"></div>
    <div class="col-auto"><button class="btn btn-outline-primary">Check</button></div>
</form>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="stat-card"><div class="text-muted small">Income & expense lines</div><div class="fs-4 fw-bold">{{ number_format($lines->count()) }}</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="text-muted small">In no segment</div><div class="fs-4 fw-bold {{ $unassigned->count() ? 'text-danger' : 'text-success' }}">{{ number_format($unassigned->count()) }}</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="text-muted small">Amount in no segment</div><div class="fs-4 fw-bold">{{ number_format($unassigned->sum(fn ($l) => (float) $l->debit + (float) $l->credit), $dp) }}</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="text-muted small">Member has no segment</div><div class="fs-4 fw-bold {{ $memberNoSegment ? 'text-warning' : '' }}">{{ $memberNoSegment }}</div></div></div>
</div>

@if($unassigned->isEmpty())
    <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i>Every income and expense line in this period counts under a segment.</div>
@else
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">By account</div>
            <div class="card-body small text-muted pb-0">Accounts that always belong to one segment: give the account a segment once and all its lines follow.</div>
            <ul class="list-group list-group-flush small">
                @foreach($byAccount as $a)
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><span class="font-monospace">{{ $a['account']->account_code }}</span> {{ $a['account']->account_name }}<br><span class="text-muted">{{ $a['lines'] }} line(s) · {{ number_format($a['amount'], $dp) }}</span></span>
                    @can('edit accounts')<a href="{{ route('accounts.edit', $a['account']->id) }}" class="btn btn-sm btn-outline-secondary py-0">Set segment</a>@endcan
                </li>
                @endforeach
            </ul>
        </div>
    </div>
    <div class="col-lg-8">
        <form method="POST" action="{{ route('segments.assign') }}">
            @csrf
            <div class="card">
                <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <span>Lines in no segment</span>
                    <div class="d-flex gap-2">
                        <select name="segment_id" class="form-select form-select-sm" required style="width:200px">
                            <option value="">Tag ticked lines to…</option>
                            @foreach($allSegments as $sg)<option value="{{ $sg->id }}">{{ $sg->name }}</option>@endforeach
                        </select>
                        <button class="btn btn-sm btn-primary">Tag</button>
                    </div>
                </div>
                <div class="table-responsive" style="max-height:65vh">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light" style="position:sticky;top:0"><tr>
                            <th class="ps-3"><input type="checkbox" class="form-check-input" onclick="document.querySelectorAll('.sg-line').forEach(c => c.checked = this.checked)"></th>
                            <th>Date</th><th>Journal</th><th>Account</th><th>Description</th><th class="text-end pe-3">Amount</th></tr></thead>
                        <tbody class="small">
                        @foreach($unassigned->take(500) as $l)
                            <tr>
                                <td class="ps-3">@if(!$l->client_id)<input type="checkbox" name="line_ids[]" value="{{ $l->id }}" class="form-check-input sg-line">@endif</td>
                                <td class="text-nowrap">{{ \Carbon\Carbon::parse($l->date)->format('d M Y') }}</td>
                                <td class="font-monospace"><a href="{{ route('transactions.show', $l->transaction_id) }}">{{ $l->reference }}</a></td>
                                <td><span class="font-monospace">{{ $l->account->account_code }}</span> {{ $l->account->account_name }}</td>
                                <td class="text-muted">{{ $l->description ?: $l->tx_description }}@if($l->client_id)<div class="text-warning">member has no segment — set it on the member</div>@endif</td>
                                <td class="text-end pe-3">{{ number_format((float) $l->debit + (float) $l->credit, $dp) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @if($unassigned->count() > 500)<div class="card-footer small text-muted">Showing the first 500 — narrow the dates to see the rest.</div>@endif
            </div>
        </form>
    </div>
</div>
@endif
@endsection
