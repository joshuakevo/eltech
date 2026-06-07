@extends('layouts.app')
@section('title', 'Audit Log')
@section('breadcrumb')
    <li class="breadcrumb-item active">Audit Log</li>
@endsection
@section('content')
<div class="page-header">
    <div>
        <h4>Audit Log</h4>
        <div class="sub">Track all user activity and login events</div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body pb-0">
        <form class="row g-2 mb-3" method="GET">
            <div class="col-md-2">
                <select name="user_id" class="form-select">
                    <option value="">All Users</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="event" class="form-select">
                    <option value="">All Events</option>
                    @foreach($events as $e)
                        <option value="{{ $e }}" @selected(request('event') == $e)>{{ ucfirst(str_replace('_',' ',$e)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="module" class="form-select">
                    <option value="">All Modules</option>
                    @foreach($modules as $m)
                        <option value="{{ $m }}" @selected(request('module') == $m)>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="from" class="form-control" value="{{ request('from') }}" placeholder="From">
            </div>
            <div class="col-md-2">
                <input type="date" name="to" class="form-control" value="{{ request('to') }}" placeholder="To">
            </div>
            <div class="col-md-2">
                <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Search description / IP...">
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-outline-primary">Filter</button>
                <a href="{{ route('audit.index') }}" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th class="ps-3">Date / Time</th>
                <th>User</th>
                <th>Event</th>
                <th>Module</th>
                <th>Description</th>
                <th>IP Address</th>
            </tr></thead>
            <tbody>
            @forelse($logs as $log)
                @php
                    $eventStyle = match($log->event) {
                        'login'        => 'background:#059669;color:#fff',
                        'logout'       => 'background:#6b7280;color:#fff',
                        'login_failed' => 'background:#dc2626;color:#fff',
                        'create'       => 'background:#2563eb;color:#fff',
                        'update'       => 'background:#d97706;color:#fff',
                        'delete'       => 'background:#991b1b;color:#fff',
                        default        => 'background:#7c3aed;color:#fff',
                    };
                    $moduleStyle = match($log->module) {
                        'Clients'        => 'background:#f59e0b;color:#fff',
                        'Loans'          => 'background:#2563eb;color:#fff',
                        'Savings'        => 'background:#059669;color:#fff',
                        'Fixed Deposits' => 'background:#0891b2;color:#fff',
                        'Journal Entries'=> 'background:#7c3aed;color:#fff',
                        'HR'             => 'background:#db2777;color:#fff',
                        'Payroll'        => 'background:#d97706;color:#fff',
                        'Users'          => 'background:#374151;color:#fff',
                        default          => 'background:#6b7280;color:#fff',
                    };
                @endphp
                <tr>
                    <td class="ps-3 text-muted small">{{ $log->created_at->format('d M Y H:i:s') }}</td>
                    <td>
                        @if($log->user)
                            <div class="fw-semibold" style="font-size:.82rem">{{ $log->user->name }}</div>
                            <div class="text-muted" style="font-size:.72rem">{{ $log->user->role_name ?? '' }}</div>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td><span class="badge" style="{{ $eventStyle }}">{{ ucfirst(str_replace('_',' ',$log->event)) }}</span></td>
                    <td>
                        @if($log->module)
                            <span class="badge" style="{{ $moduleStyle }}">{{ $log->module }}</span>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td style="font-size:.82rem">{{ $log->description }}</td>
                    <td class="font-monospace text-muted small">{{ $log->ip_address }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No audit records found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $logs->withQueryString()->links() }}</div>
</div>
@endsection
