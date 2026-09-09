@extends('layouts.app')
@section('title', 'CRM — Clients')
@section('breadcrumb')
    <li class="breadcrumb-item active">CRM</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">CRM — Clients</h4>
        <div class="text-muted small">{{ number_format($totalCount) }} client{{ $totalCount === 1 ? '' : 's' }} matching current filters</div>
    </div>
</div>

<div class="card">
    <div class="card-body pb-0">
        <form class="row g-2 mb-3" method="GET">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Name, client # or phone..." value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="active" @selected(request('status')=='active')>Active</option>
                    <option value="inactive" @selected(request('status')=='inactive')>Inactive</option>
                    <option value="blacklisted" @selected(request('status')=='blacklisted')>Blacklisted</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="client_type" class="form-select">
                    <option value="">All Types</option>
                    <option value="individual" @selected(request('client_type')=='individual')>Individual</option>
                    <option value="group" @selected(request('client_type')=='group')>Group</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="product" class="form-select">
                    <option value="">Any Product</option>
                    <option value="savings" @selected(request('product')=='savings')>Has Savings</option>
                    <option value="loan" @selected(request('product')=='loan')>Has Active Loan</option>
                    <option value="fixed_deposit" @selected(request('product')=='fixed_deposit')>Has Fixed Deposit</option>
                    <option value="shares" @selected(request('product')=='shares')>Has Shares</option>
                    <option value="none" @selected(request('product')=='none')>No Products</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="relationship_manager_id" class="form-select">
                    <option value="">All Account Managers</option>
                    @foreach($relationshipManagers as $rm)
                    <option value="{{ $rm->id }}" @selected(request('relationship_manager_id')==$rm->id)>{{ $rm->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="health" class="form-select">
                    <option value="">Any Health</option>
                    <option value="Healthy" @selected(request('health')=='Healthy')>🟢 Healthy</option>
                    <option value="Needs Attention" @selected(request('health')=='Needs Attention')>🟡 Needs Attention</option>
                    <option value="At Risk" @selected(request('health')=='At Risk')>🔴 At Risk</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Joined from</label>
                <input type="date" name="joined_from" class="form-control" value="{{ request('joined_from') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Joined to</label>
                <input type="date" name="joined_to" class="form-control" value="{{ request('joined_to') }}">
            </div>
            <div class="col-auto align-self-end"><button class="btn btn-outline-primary">Filter</button></div>
            <div class="col-auto align-self-end"><a href="{{ route('crm.clients.index') }}" class="btn btn-outline-secondary">Clear</a></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th class="ps-3">Client</th><th>Type</th><th>Account Manager</th>
                <th class="text-center">Products</th>
                <th>Health</th>
                <th class="text-end">Total Value</th>
                <th class="text-end">Outstanding</th>
                <th>Last Activity</th><th>Status</th><th class="pe-3">Actions</th>
            </tr></thead>
            <tbody>
            @forelse($clients as $client)
                <tr>
                    <td class="ps-3">
                        <a href="{{ route('crm.clients.show', $client) }}" class="text-decoration-none fw-semibold">{{ $client->name }}</a>
                        <div class="small text-muted font-monospace">{{ $client->client_number }}</div>
                    </td>
                    <td class="small text-muted">{{ ucfirst($client->client_type) }}</td>
                    <td class="small">{{ $client->relationshipManager->name ?? '—' }}</td>
                    <td class="text-center">
                        <span class="badge bg-primary-subtle text-primary">{{ $client->product_count }}/{{ $totalProductTypes }}</span>
                    </td>
                    <td>
                        <span class="badge bg-{{ $client->health['color'] }}-subtle text-{{ $client->health['color'] }}-emphasis" title="Health score: {{ $client->health['score'] }}/100">
                            {{ $client->health['emoji'] }} {{ $client->health['label'] }}
                        </span>
                    </td>
                    <td class="text-end fw-semibold text-success">{{ number_format($client->financial_summary['total_assets'], $dp) }}</td>
                    <td class="text-end fw-semibold {{ $client->financial_summary['total_liability'] > 0 ? 'text-warning' : 'text-muted' }}">
                        {{ number_format($client->financial_summary['total_liability'], $dp) }}
                    </td>
                    <td class="small text-muted">
                        {{ $client->last_activity_at ? \Illuminate\Support\Carbon::parse($client->last_activity_at)->format('d M Y') : 'Never' }}
                    </td>
                    <td><span class="badge badge-status-{{ $client->status }}">{{ ucfirst($client->status) }}</span></td>
                    <td class="pe-3">
                        <a href="{{ route('crm.clients.show', $client) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-center text-muted py-4">No clients found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $clients->links() }}</div>
</div>
@endsection
