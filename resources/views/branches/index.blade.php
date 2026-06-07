@extends('layouts.app')
@section('title','Branches')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0 fw-semibold">Branches</h4>
        <p class="text-muted small mb-0">Manage your organisation's branch network</p>
    </div>
    <a href="{{ route('branches.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> New Branch
    </a>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Branch</th>
                    <th>Code</th>
                    <th>Manager</th>
                    <th>Phone</th>
                    <th class="text-center">Clients</th>
                    <th class="text-center">Loans</th>
                    <th class="text-center">Savings</th>
                    <th class="text-center">Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($branches as $branch)
                <tr>
                    <td>
                        <a href="{{ route('branches.show', $branch) }}" class="fw-semibold text-decoration-none text-dark">
                            {{ $branch->name }}
                        </a>
                        @if($branch->address)
                            <div class="text-muted small">{{ Str::limit($branch->address, 40) }}</div>
                        @endif
                    </td>
                    <td><span class="badge bg-secondary">{{ $branch->code }}</span></td>
                    <td>{{ $branch->manager_name ?? '—' }}</td>
                    <td>{{ $branch->phone ?? '—' }}</td>
                    <td class="text-center">{{ $branch->clients_count }}</td>
                    <td class="text-center">{{ $branch->loans_count }}</td>
                    <td class="text-center">{{ $branch->savings_accounts_count }}</td>
                    <td class="text-center">
                        @if($branch->is_active)
                            <span class="badge bg-success-subtle text-success">Active</span>
                        @else
                            <span class="badge bg-danger-subtle text-danger">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('branches.edit', $branch) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-pencil"></i>
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center py-4 text-muted">No branches found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($branches->hasPages())
    <div class="card-footer">{{ $branches->links() }}</div>
    @endif
</div>
@endsection
