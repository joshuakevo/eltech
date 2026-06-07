@extends('layouts.app')
@section('title', 'Loan Products')
@section('breadcrumb')
    <li class="breadcrumb-item active">Loan Products</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Loan Products</h4>
    @can('create loan-products')
    <a href="{{ route('loan-products.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> New Product</a>
    @endcan
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th class="ps-3">Name</th><th>Rate</th><th>Method</th><th>Term</th><th>Penalty</th><th>Status</th><th class="pe-3">Actions</th>
            </tr></thead>
            <tbody>
            @forelse($products as $p)
                <tr>
                    <td class="ps-3 fw-semibold">{{ $p->name }}</td>
                    <td>{{ $p->interest_rate }}% p.a.</td>
                    <td><span class="badge bg-light text-dark">{{ ucfirst($p->interest_method) }}</span></td>
                    <td>{{ $p->term_months }} months</td>
                    <td>{{ $p->penalty_rate }}%/day</td>
                    <td><span class="badge {{ $p->is_active ? 'bg-success' : 'bg-secondary' }} bg-opacity-10 {{ $p->is_active ? 'text-success' : 'text-secondary' }}">{{ $p->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="pe-3">
                        <a href="{{ route('loan-products.show', $p) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                        @can('edit loan-products')
                        <a href="{{ route('loan-products.edit', $p) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No loan products.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $products->links() }}</div>
</div>
@endsection
