@extends('layouts.app')
@section('title', 'Edit Savings Product')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('savings-products.index') }}">Savings Products</a></li>
    <li class="breadcrumb-item active">{{ $savingsProduct->name }}</li>
@endsection
@section('content')
<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">Edit Savings Product — {{ $savingsProduct->name }}</div>
            <div class="card-body">
                <form method="POST" action="{{ route('savings-products.update', $savingsProduct) }}">
                    @csrf @method('PUT')
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Product Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $savingsProduct->name) }}" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Minimum Balance</label>
                            <input type="number" name="minimum_balance" class="form-control" step="0.01" value="{{ old('minimum_balance', $savingsProduct->minimum_balance) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Withdrawal Fee</label>
                            <input type="number" name="withdrawal_fee" class="form-control" step="0.01" value="{{ old('withdrawal_fee', $savingsProduct->withdrawal_fee) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Interest Method</label>
                            <select name="interest_method" id="interestMethod" class="form-select">
                                <option value="flat"   @selected(old('interest_method',$savingsProduct->interest_method)=='flat')>Flat Rate</option>
                                <option value="tiered" @selected(old('interest_method',$savingsProduct->interest_method)=='tiered')>Graduated Tiers</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4" id="flatRateField">
                            <label class="form-label fw-semibold">Interest Rate (% p.a.)</label>
                            <input type="number" name="interest_rate" class="form-control" step="0.01" value="{{ old('interest_rate', $savingsProduct->interest_rate) }}">
                            <div class="form-text">Used when Interest Method is Flat Rate.</div>
                        </div>
                        <div class="col-md-8" id="tieredNote" style="display:none">
                            <div class="alert alert-info py-2 mb-0 small">Interest is calculated from the org-wide <a href="{{ route('savings-interest-tiers.edit') }}">Savings Interest Tiers</a> &mdash; each portion of the balance earns its own bracket's rate.</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Interest Frequency</label>
                        <select name="interest_frequency" class="form-select">
                            @foreach(['daily','monthly','quarterly','annually'] as $f)
                                <option value="{{ $f }}" @selected(old('interest_frequency',$savingsProduct->interest_frequency)==$f)>{{ ucfirst($f) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Savings Liability Account</label>
                            <select name="savings_liability_account_id" class="form-select ts-select">
                                <option value="">— None —</option>
                                @foreach($accounts as $a)
                                    <option value="{{ $a->id }}" @selected(old('savings_liability_account_id',$savingsProduct->savings_liability_account_id)==$a->id)>{{ $a->account_code }} — {{ $a->account_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Interest Expense Account</label>
                            <select name="interest_expense_account_id" class="form-select ts-select">
                                <option value="">— None —</option>
                                @foreach($accounts as $a)
                                    <option value="{{ $a->id }}" @selected(old('interest_expense_account_id',$savingsProduct->interest_expense_account_id)==$a->id)>{{ $a->account_code }} — {{ $a->account_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mb-4 form-check">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" @checked(old('is_active',$savingsProduct->is_active))>
                        <label for="is_active" class="form-check-label">Active</label>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary">Update Product</button>
                        <a href="{{ route('savings-products.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
function toggleInterestMethod() {
    const tiered = document.getElementById('interestMethod').value === 'tiered';
    document.getElementById('flatRateField').style.display = tiered ? 'none' : '';
    document.getElementById('tieredNote').style.display    = tiered ? '' : 'none';
}
document.getElementById('interestMethod').addEventListener('change', toggleInterestMethod);
document.addEventListener('DOMContentLoaded', toggleInterestMethod);
</script>
@endsection
