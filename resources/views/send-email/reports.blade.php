@extends('layouts.app')
@section('title', 'Email Reports')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('send-email.index') }}">Send Email</a></li>
    <li class="breadcrumb-item active">Email Reports</li>
@endsection
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0">Email Reports</h4>
        <p class="text-muted small mb-0">Every email sent from CRM → Send Email.</p>
    </div>
    <ul class="nav nav-pills">
        <li class="nav-item"><a class="nav-link" href="{{ route('send-email.index') }}">Send Email</a></li>
        <li class="nav-item"><a class="nav-link active" href="{{ route('send-email.reports') }}">Email Reports</a></li>
    </ul>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="stat-card"><div class="text-muted small">Sent</div><div class="fs-4 fw-bold text-success">{{ number_format($sentCount) }}</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="text-muted small">Failed</div><div class="fs-4 fw-bold text-danger">{{ number_format($failedCount) }}</div></div></div>
</div>

<div class="card">
    <div class="card-body pb-0">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-3"><input type="text" name="search" class="form-control" placeholder="Client, email or subject…" value="{{ request('search') }}"></div>
            <div class="col-md-2"><select name="status" class="form-select"><option value="">All statuses</option><option value="sent" @selected(request('status')=='sent')>Sent</option><option value="failed" @selected(request('status')=='failed')>Failed</option></select></div>
            <div class="col-md-2"><select name="category" class="form-select"><option value="">All groups</option>@foreach(['all' => 'All clients', 'loan_due' => 'Loan due', 'loan_overdue' => 'Loan overdue', 'dormant_savings' => 'Dormant savings'] as $k => $v)<option value="{{ $k }}" @selected(request('category')==$k)>{{ $v }}</option>@endforeach</select></div>
            <div class="col-md-2"><input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}"></div>
            <div class="col-md-2"><input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}"></div>
            <div class="col-auto"><button class="btn btn-outline-primary">Filter</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th class="ps-3">Date</th><th>Client</th><th>Email</th><th>Subject</th><th>Status</th><th class="pe-3">Sent by</th></tr></thead>
            <tbody class="small">
            @forelse($logs as $log)
                <tr>
                    <td class="ps-3 text-nowrap">{{ $log->created_at->format('d M Y H:i') }}</td>
                    <td>@if($log->client)<a href="{{ route('clients.show', $log->client) }}">{{ $log->client->name }}</a>@else—@endif</td>
                    <td>{{ $log->email }}</td>
                    <td><span title="{{ $log->body }}">{{ $log->subject }}</span>@if($log->attachment_name)<div class="text-muted"><i class="bi bi-paperclip"></i> {{ $log->attachment_name }}</div>@endif</td>
                    <td>@if($log->status === 'sent')<span class="badge bg-success-subtle text-success">Sent</span>@else<span class="badge bg-danger-subtle text-danger" title="{{ $log->error }}">Failed</span><div class="text-danger text-truncate" style="max-width:220px" title="{{ $log->error }}">{{ $log->error }}</div>@endif</td>
                    <td class="pe-3">{{ $log->sentBy->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No emails sent yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())<div class="card-footer bg-white">{{ $logs->links() }}</div>@endif
</div>
@endsection
