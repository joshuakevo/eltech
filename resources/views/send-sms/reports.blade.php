@extends('layouts.app')
@section('title', 'SMS Delivery Reports')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('send-sms.index') }}">Send SMS</a></li>
    <li class="breadcrumb-item active">Delivery Reports</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-end mb-3">
    <div>
        <h4 class="fw-bold mb-0">SMS Delivery Reports</h4>
        <p class="text-muted small mb-0">History of every SMS send attempt and whether the gateway accepted it.</p>
    </div>
    <ul class="nav nav-pills">
        <li class="nav-item"><a class="nav-link" href="{{ route('send-sms.index') }}">Send SMS</a></li>
        <li class="nav-item"><a class="nav-link active" href="{{ route('send-sms.reports') }}">Delivery Reports</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('send-sms.subscription') }}">Subscription</a></li>
    </ul>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Sent</div>
                <div class="fs-4 fw-bold text-success">{{ number_format($sentCount) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Failed</div>
                <div class="fs-4 fw-bold text-danger">{{ number_format($failedCount) }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body pb-0">
        <form class="row g-2 mb-3" method="GET">
            <div class="col-md-3"><input type="text" name="search" class="form-control" placeholder="Client name or phone..." value="{{ request('search') }}"></div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="sent" @selected(request('status')=='sent')>Sent</option>
                    <option value="failed" @selected(request('status')=='failed')>Failed</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="category" class="form-select">
                    <option value="">All Categories</option>
                    <option value="all" @selected(request('category')=='all')>All Clients</option>
                    <option value="loan_due" @selected(request('category')=='loan_due')>Loan Due</option>
                    <option value="loan_overdue" @selected(request('category')=='loan_overdue')>Loan Overdue</option>
                    <option value="dormant_savings" @selected(request('category')=='dormant_savings')>Dormant Savings</option>
                </select>
            </div>
            <div class="col-md-2"><input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}"></div>
            <div class="col-md-2"><input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}"></div>
            <div class="col-auto"><button class="btn btn-outline-primary">Filter</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th class="ps-3">Date</th><th>Client</th><th>Phone</th><th>Message</th>
                <th>Category</th><th>Gateway</th><th>Status</th><th class="pe-3">Sent By</th>
            </tr></thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td class="ps-3 small text-muted text-nowrap">{{ $log->created_at->format('d M Y H:i') }}</td>
                    <td>
                        @if($log->client)
                            <a href="{{ route('clients.show', $log->client) }}" class="text-decoration-none">{{ $log->client->name }}</a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="small font-monospace">{{ $log->phone }}</td>
                    <td class="small text-muted" style="max-width:280px">{{ \Illuminate\Support\Str::limit($log->message, 80) }}</td>
                    <td class="small">{{ ucfirst(str_replace('_', ' ', $log->category)) }}</td>
                    <td class="small text-muted">{{ $log->gateway ?? '—' }}</td>
                    <td>
                        @if($log->status === 'sent')
                            <span class="badge bg-success-subtle text-success">Sent</span>
                        @else
                            <span class="badge bg-danger-subtle text-danger" title="{{ $log->error }}">Failed</span>
                        @endif
                    </td>
                    <td class="pe-3 small text-muted">{{ $log->sentBy->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No SMS sent yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $logs->links() }}</div>
</div>
@endsection
