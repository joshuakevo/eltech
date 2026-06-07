@extends('layouts.app')
@section('title', 'Edit FD Product')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('fd-products.index') }}">FD Products</a></li>
    <li class="breadcrumb-item active">{{ $fdProduct->name }}</li>
@endsection
@section('content')
<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">Edit FD Product — {{ $fdProduct->name }}</div>
            <div class="card-body">
                <form method="POST" action="{{ route('fd-products.update', $fdProduct) }}">
                    @csrf @method('PUT')
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Product Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $fdProduct->name) }}" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Interest Rate (% p.a.)</label>
                            <input type="number" name="interest_rate" class="form-control" step="0.01" value="{{ old('interest_rate', $fdProduct->interest_rate) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Term (months)</label>
                            <input type="number" name="term_months" class="form-control" min="1" value="{{ old('term_months', $fdProduct->term_months) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Min Amount</label>
                            <input type="number" name="min_amount" class="form-control" step="0.01" value="{{ old('min_amount', $fdProduct->min_amount) }}">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">FD Liability Account</label>
                            <select name="deposit_liability_account_id" class="form-select ts-select">
                                <option value="">— None —</option>
                                @foreach($accounts as $a)
                                    <option value="{{ $a->id }}" @selected(old('deposit_liability_account_id',$fdProduct->deposit_liability_account_id)==$a->id)>{{ $a->account_code }} — {{ $a->account_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Interest Expense Account</label>
                            <select name="interest_expense_account_id" class="form-select ts-select">
                                <option value="">— None —</option>
                                @foreach($accounts as $a)
                                    <option value="{{ $a->id }}" @selected(old('interest_expense_account_id',$fdProduct->interest_expense_account_id)==$a->id)>{{ $a->account_code }} — {{ $a->account_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mb-4 form-check">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" @checked(old('is_active',$fdProduct->is_active))>
                        <label for="is_active" class="form-check-label">Active</label>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary">Update Product</button>
                        <a href="{{ route('fd-products.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
