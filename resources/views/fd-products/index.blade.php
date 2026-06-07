@extends('layouts.app')
@section('title', 'FD Products')
@section('breadcrumb')
    <li class="breadcrumb-item active">Fixed Deposit Products</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Fixed Deposit Products</h4>
    @can('create fd-products')
    <a href="{{ route('fd-products.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> New Product</a>
    @endcan
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th class="ps-3">Name</th><th>Rate</th><th>Term</th><th>Min Amount</th><th>Status</th><th class="pe-3">Actions</th>
            </tr></thead>
            <tbody>
            @forelse($products as $p)
                <tr>
                    <td class="ps-3 fw-semibold">{{ $p->name }}</td>
                    <td>{{ $p->interest_rate }}% p.a.</td>
                    <td>{{ $p->term_months }} months</td>
                    <td>{{ number_format($p->min_amount, $dp) }}</td>
                    <td><span class="badge {{ $p->is_active ? 'bg-success' : 'bg-secondary' }} bg-opacity-10 {{ $p->is_active ? 'text-success' : 'text-secondary' }}">{{ $p->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="pe-3">
                        <a href="{{ route('fd-products.show', $p) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                        @can('edit fd-products')
                        <a href="{{ route('fd-products.edit', $p) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No FD products.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $products->links() }}</div>
</div>
@endsection
