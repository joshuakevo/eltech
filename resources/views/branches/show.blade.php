@extends('layouts.app')
@section('title', $branch->name)
@section('content')
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <a href="{{ route('branches.index') }}" class="text-muted text-decoration-none small">
            <i class="bi bi-arrow-left me-1"></i>Branches
        </a>
        <h4 class="mb-0 fw-semibold mt-1">{{ $branch->name }}</h4>
        <span class="badge bg-secondary">{{ $branch->code }}</span>
        @if($branch->is_active)
            <span class="badge bg-success ms-1">Active</span>
        @else
            <span class="badge bg-danger ms-1">Inactive</span>
        @endif
    </div>
    <a href="{{ route('branches.edit', $branch) }}" class="btn btn-outline-primary">
        <i class="bi bi-pencil me-1"></i>Edit
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card text-center shadow-sm">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-primary">{{ $branch->clients_count }}</div>
                <div class="text-muted small">Clients</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card text-center shadow-sm">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-warning">{{ $branch->loans_count }}</div>
                <div class="text-muted small">Loans</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card text-center shadow-sm">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-success">{{ $branch->savings_accounts_count }}</div>
                <div class="text-muted small">Savings Accounts</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card text-center shadow-sm">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-info">{{ $branch->fixed_deposits_count }}</div>
                <div class="text-muted small">Fixed Deposits</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Branch Details</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5 text-muted">Address</dt>
                    <dd class="col-7">{{ $branch->address ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Phone</dt>
                    <dd class="col-7">{{ $branch->phone ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Email</dt>
                    <dd class="col-7">{{ $branch->email ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Manager</dt>
                    <dd class="col-7">{{ $branch->manager_name ?? '—' }}</dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Staff in this Branch</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td class="text-muted small">{{ $user->email }}</td>
                            <td><span class="badge bg-info-subtle text-info">{{ $user->role_name }}</span></td>
                            <td>
                                @if($user->is_active)
                                    <span class="badge bg-success-subtle text-success">Active</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger">Inactive</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">No staff assigned.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
