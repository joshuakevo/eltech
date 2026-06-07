@extends('layouts.app')
@section('title', 'Savings Products')
@section('breadcrumb')
    <li class="breadcrumb-item active">Savings Products</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Savings Products</h4>
    @can('create savings-products')
    <a href="{{ route('savings-products.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> New Product</a>
    @endcan
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th class="ps-3">Name</th><th>Min Balance</th><th>Withdrawal Fee</th><th>Interest Rate</th><th>Frequency</th><th>Status</th><th class="pe-3">Actions</th>
            </tr></thead>
            <tbody>
            @forelse($products as $p)
                <tr>
                    <td class="ps-3 fw-semibold">{{ $p->name }}</td>
                    <td>{{ number_format($p->minimum_balance, $dp) }}</td>
                    <td>{{ number_format($p->withdrawal_fee, $dp) }}</td>
                    <td>{{ $p->interest_rate }}% p.a.</td>
                    <td class="small">{{ ucfirst($p->interest_frequency) }}</td>
                    <td><span class="badge {{ $p->is_active ? 'bg-success' : 'bg-secondary' }} bg-opacity-10 {{ $p->is_active ? 'text-success' : 'text-secondary' }}">{{ $p->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="pe-3">
                        <a href="{{ route('savings-products.show', $p) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                        @can('edit savings-products')
                        <a href="{{ route('savings-products.edit', $p) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No savings products.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $products->links() }}</div>
</div>
@endsection
