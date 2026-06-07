@extends('layouts.app')
@section('title', 'Groups')
@section('breadcrumb')
    <li class="breadcrumb-item active">Groups</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Groups</h4>
    @can('manage groups')
    <a href="{{ route('groups.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i> New Group</a>
    @endcan
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
            <input type="search" name="search" class="form-control form-control-sm" style="max-width:220px" placeholder="Search name or #…" value="{{ request('search') }}">
            <select name="status" class="form-select form-select-sm" style="max-width:150px">
                <option value="">All Status</option>
                <option value="active"   {{ request('status')==='active'   ?'selected':'' }}>Active</option>
                <option value="inactive" {{ request('status')==='inactive' ?'selected':'' }}>Inactive</option>
                <option value="dissolved"{{ request('status')==='dissolved'?'selected':'' }}>Dissolved</option>
            </select>
            <button class="btn btn-sm btn-outline-secondary">Filter</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th class="ps-3">Group #</th>
                    <th>Name</th>
                    <th class="text-center">Members</th>
                    <th class="text-end">Total Balance</th>
                    <th class="text-end">Rate</th>
                    <th>Status</th>
                    <th class="pe-3"></th>
                </tr>
            </thead>
            <tbody>
            @forelse($groups as $g)
            <tr>
                <td class="ps-3 font-monospace text-muted">{{ $g->group_number }}</td>
                <td class="fw-semibold">{{ $g->name }}</td>
                <td class="text-center">{{ $g->active_members_count }}</td>
                <td class="text-end fw-semibold text-primary">{{ number_format($g->members_sum_balance ?? 0, 0) }}</td>
                <td class="text-end text-muted small">{{ $g->monthly_interest_rate }}%</td>
                <td>
                    <span class="badge {{ $g->status === 'active' ? 'bg-success' : ($g->status === 'dissolved' ? 'bg-danger' : 'bg-secondary') }}">
                        {{ ucfirst($g->status) }}
                    </span>
                </td>
                <td class="text-end pe-3">
                    <a href="{{ route('groups.show', $g) }}" class="btn btn-sm btn-outline-primary">View</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-center text-muted py-4">No groups found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($groups->hasPages())
    <div class="card-footer">{{ $groups->withQueryString()->links() }}</div>
    @endif
</div>
@endsection
