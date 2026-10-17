@extends('layouts.app')
@section('title', 'Interest run — ' . $run->product->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('savings-interest.index', ['tab' => 'p'.$run->savings_product_id]) }}">Running Interest</a></li>
    <li class="breadcrumb-item active">{{ $run->product->name }} to {{ $run->period_end->format('d M Y') }}</li>
@endsection
@section('content')
<div class="page-header">
    <div>
        <h4 class="fw-bold mb-0">{{ $run->product->name }} interest to {{ $run->period_end->format('d M Y') }} <span class="badge bg-success-subtle text-success fs-6 align-middle">Posted</span></h4>
        <div class="text-muted small">Run #{{ $run->id }} by {{ $run->createdBy->name ?? '—' }} on {{ $run->created_at->format('d M Y H:i') }}
            @if($run->transaction_id) · <a href="{{ route('transactions.show', $run->transaction_id) }}">Journal</a>@endif
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('savings-interest.show', [$run, 'format' => 'excel']) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
        <a href="{{ route('savings-interest.show', [$run, 'format' => 'pdf']) }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
        @if($isLatest)
        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#undoRunModal"><i class="bi bi-arrow-counterclockwise me-1"></i>Undo run</button>
        @endif
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-4"><div class="stat-card"><div class="text-muted small">Total interest</div><div class="fs-4 fw-bold text-primary">{{ number_format($run->total_interest, $dp) }}</div></div></div>
    <div class="col-sm-4"><div class="stat-card"><div class="text-muted small">Accounts credited</div><div class="fs-4 fw-bold">{{ $run->accounts_count }}</div></div></div>
    <div class="col-sm-4"><div class="stat-card"><div class="text-muted small">Average per account</div><div class="fs-4 fw-bold">{{ number_format($run->accounts_count ? $run->total_interest / $run->accounts_count : 0, $dp) }}</div></div></div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr><th class="ps-3">#</th><th>Member</th><th>Account</th><th>Period</th><th class="text-end">Days</th><th class="text-end">Avg balance</th><th class="text-end">Closing balance</th><th>Rate</th><th class="text-end pe-3">Interest</th></tr>
            </thead>
            <tbody class="small">
            @foreach($lines as $i => $l)
                <tr>
                    <td class="ps-3">{{ $i + 1 }}</td>
                    <td>{{ $l->client->name ?? '' }} <span class="text-muted">{{ $l->client->client_number ?? '' }}</span></td>
                    <td class="font-monospace">@if($l->account)<a href="{{ route('savings.show', $l->account) }}" class="text-decoration-none">{{ $l->account->account_number }}</a>@endif</td>
                    <td class="text-nowrap">{{ $l->period_start->format('d M') }} – {{ $l->period_end->format('d M Y') }}</td>
                    <td class="text-end">{{ $l->days }}</td>
                    <td class="text-end">{{ number_format($l->average_balance, $dp) }}</td>
                    <td class="text-end">{{ number_format($l->closing_balance, $dp) }}</td>
                    <td>{{ $l->rate_label }}</td>
                    <td class="text-end pe-3 fw-semibold">{{ number_format($l->interest, $dp) }}</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot class="table-light fw-bold small"><tr><td colspan="8" class="ps-3">Total</td><td class="text-end pe-3">{{ number_format($lines->sum('interest'), $dp) }}</td></tr></tfoot>
        </table>
    </div>
</div>

@if($isLatest)
<div class="modal fade" id="undoRunModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('savings-interest.undo', $run) }}" class="modal-content">
            @csrf @method('DELETE')
            <div class="modal-header">
                <h6 class="modal-title fw-bold text-danger"><i class="bi bi-arrow-counterclockwise me-1"></i>Undo this interest run</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body small">
                <p>This <strong>deletes</strong> the journal and the interest row on all {{ $run->accounts_count }} members' statements ({{ number_format($run->total_interest, $dp) }} in total) — nothing is reversed, it is as if the run never happened. Balances go back and you can run it again.</p>
                <label class="form-label fw-semibold">Type UNDO to confirm</label>
                <input type="text" name="confirm" class="form-control" autocomplete="off" required pattern="UNDO">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger">Undo run</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
