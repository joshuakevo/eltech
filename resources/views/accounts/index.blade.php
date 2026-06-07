@extends('layouts.app')
@section('title', 'Chart of Accounts')
@section('breadcrumb')
    <li class="breadcrumb-item active">Chart of Accounts</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Chart of Accounts</h4>
    @can('create accounts')
    <a href="{{ route('accounts.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> New Account</a>
    @endcan
</div>
<div class="card">
    <div class="card-body pb-0">
        <form class="row g-2 mb-3" method="GET">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Search code or name..." value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select name="type" class="form-select">
                    <option value="">All Types</option>
                    @foreach(['asset','liability','equity','revenue','expense'] as $t)
                        <option value="{{ $t }}" @selected(request('type')==$t)>{{ ucfirst($t) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-outline-primary">Filter</button></div>
            <div class="col-auto"><a href="{{ route('accounts.index') }}" class="btn btn-outline-secondary">Clear</a></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th class="ps-3">Code</th><th>Account Name</th><th>Type</th><th>Parent</th><th>Status</th><th class="pe-3">Actions</th>
            </tr></thead>
            <tbody>
            @forelse($accounts as $account)
                <tr>
                    <td class="ps-3 font-monospace fw-semibold">{{ $account->account_code }}</td>
                    <td>{{ $account->account_name }}</td>
                    <td>
                        @php
                            $tc = match($account->account_type) {
                                'asset'     => 'primary',
                                'liability' => 'danger',
                                'equity'    => 'dark',
                                'revenue'   => 'success',
                                'expense'   => 'warning',
                                default     => 'secondary',
                            };
                        @endphp
                        <span class="badge bg-{{ $tc }}">{{ ucfirst($account->account_type) }}</span>
                    </td>
                    <td class="text-muted small">{{ $account->parent->account_name ?? '—' }}</td>
                    <td>
                        @if($account->is_active)
                            <span class="badge bg-success bg-opacity-10 text-success">Active</span>
                        @else
                            <span class="badge bg-secondary bg-opacity-10 text-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="pe-3">
                        <a href="{{ route('accounts.show', $account) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                        @can('edit accounts')
                        <a href="{{ route('accounts.edit', $account) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No accounts found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $accounts->withQueryString()->links() }}</div>
</div>
@endsection
