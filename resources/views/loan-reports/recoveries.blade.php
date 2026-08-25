@extends('layouts.app')
@section('title', 'Loan Recoveries')
@section('breadcrumb')
    <li class="breadcrumb-item active">Loan Recoveries</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Loan Recoveries</h4>
    <div class="dropdown">
        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-download me-1"></i>Export</button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['format'=>'pdf']) }}" target="_blank"><i class="bi bi-file-earmark-pdf me-2 text-danger"></i>Export PDF</a></li>
            <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['format'=>'excel']) }}"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Export Excel (CSV)</a></li>
        </ul>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small text-uppercase">Principal Balance</div>
            <div class="fs-4 fw-bold text-warning">{{ number_format($totalPrincipalBalance, $dp) }}</div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small text-uppercase">Interest Balance</div>
            <div class="fs-4 fw-bold text-warning">{{ number_format($totalInterestBalance, $dp) }}</div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small text-uppercase">Active Loans</div>
            <div class="fs-4 fw-bold">{{ number_format($totalCount) }}</div>
        </div></div>
    </div>
</div>

<div class="card">
    <div class="card-body pb-0">
        <form class="row g-2 mb-3" method="GET">
            <div class="col-md-4"><input type="text" name="search" class="form-control" placeholder="Loan # or client name..." value="{{ request('search') }}"></div>
            <div class="col-md-3">
                <select name="rm_id" class="form-select">
                    <option value="">All Relationship Managers</option>
                    @foreach($relationshipManagers as $rm)
                        <option value="{{ $rm->id }}" @selected(request('rm_id') == $rm->id)>{{ $rm->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-outline-primary">Filter</button></div>
            <div class="col-auto"><a href="{{ route('loan-reports.recoveries') }}" class="btn btn-outline-secondary">Clear</a></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th class="ps-3">Loan #</th><th>Client</th>
                <th class="text-end">Principal Balance</th><th class="text-end">Interest Balance</th>
                <th>RM</th><th>Last Comment</th><th class="pe-3">Actions</th>
            </tr></thead>
            <tbody>
            @forelse($loans as $loan)
                @php $lastComment = $loan->comments->first(); @endphp
                <tr>
                    <td class="ps-3 font-monospace"><a href="{{ route('loans.show', $loan) }}" class="text-decoration-none">{{ $loan->loan_number }}</a></td>
                    <td>
                        @if($loan->client)
                            <a href="{{ route('clients.show', $loan->client) }}" class="text-decoration-none">{{ $loan->client->name }}</a>
                        @else
                            <span class="text-muted fst-italic">Deleted client</span>
                        @endif
                    </td>
                    <td class="text-end fw-semibold">{{ number_format($loan->outstanding_principal, $dp) }}</td>
                    <td class="text-end fw-semibold">{{ number_format($loan->outstanding_interest, $dp) }}</td>
                    <td class="small">{{ $loan->client->relationshipManager->name ?? '—' }}</td>
                    <td class="small" style="max-width:260px">
                        @if($lastComment)
                            <div>{{ \Illuminate\Support\Str::limit($lastComment->comment, 80) }}</div>
                            <div class="text-muted" style="font-size:.75rem">{{ $lastComment->created_at->format('d M Y H:i') }} — {{ $lastComment->createdBy->name ?? 'Unknown' }}</div>
                        @else
                            <span class="text-muted fst-italic">No comments yet</span>
                        @endif
                    </td>
                    <td class="pe-3">
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#commentModal{{ $loan->id }}">
                            <i class="bi bi-chat-square-text"></i> Add Comment
                        </button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No active loans found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $loans->links() }}</div>
</div>

{{-- Add Comment Modals --}}
@foreach($loans as $loan)
<div class="modal fade" id="commentModal{{ $loan->id }}" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('loan-reports.add-comment', $loan) }}">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title"><i class="bi bi-chat-square-text me-2 text-primary"></i>Add Comment — {{ $loan->loan_number }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if($loan->comments->isNotEmpty())
                    <div class="mb-3 small" style="max-height:180px;overflow-y:auto">
                        <div class="fw-semibold text-muted mb-1">Previous comments</div>
                        @foreach($loan->comments as $c)
                        <div class="border-bottom pb-1 mb-1">
                            <div>{{ $c->comment }}</div>
                            <div class="text-muted" style="font-size:.75rem">{{ $c->created_at->format('d M Y H:i') }} — {{ $c->createdBy->name ?? 'Unknown' }}</div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                    <label class="form-label fw-semibold">New Comment</label>
                    <textarea name="comment" class="form-control" rows="3" maxlength="1000" required placeholder="e.g. Called client, promised to pay by Friday."></textarea>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Comment</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endsection
