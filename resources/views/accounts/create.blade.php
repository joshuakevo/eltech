@extends('layouts.app')
@section('title', 'New Account')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('accounts.index') }}">Chart of Accounts</a></li>
    <li class="breadcrumb-item active">New Account</li>
@endsection
@section('content')
<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">New Account</div>
            <div class="card-body">
                <form method="POST" action="{{ route('accounts.store') }}">
                    @csrf
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Account Code <span class="text-danger">*</span></label>
                            <input type="text" name="account_code" class="form-control font-monospace @error('account_code') is-invalid @enderror" value="{{ old('account_code') }}" required>
                            @error('account_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Account Name <span class="text-danger">*</span></label>
                            <input type="text" name="account_name" class="form-control @error('account_name') is-invalid @enderror" value="{{ old('account_name') }}" required>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Account Type <span class="text-danger">*</span></label>
                            <select name="account_type" class="form-select" required>
                                @foreach(['asset','liability','equity','revenue','expense'] as $t)
                                    <option value="{{ $t }}" @selected(old('account_type')==$t)>{{ ucfirst($t) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Parent Account</label>
                            <select name="parent_id" class="form-select ts-select">
                                <option value="">— None —</option>
                                @foreach($parents as $p)
                                    <option value="{{ $p->id }}" @selected(old('parent_id')==$p->id)>{{ $p->account_code }} — {{ $p->account_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" @checked(old('is_active', 1))>
                        <label for="is_active" class="form-check-label">Active</label>
                    </div>
                    <div class="mb-4 form-check">
                        <input type="checkbox" name="is_payment_source" value="1" class="form-check-input" id="is_payment_source" @checked(old('is_payment_source'))>
                        <label for="is_payment_source" class="form-check-label">Use as Payment Source
                            <span class="text-muted small d-block fw-normal">Appears in payment dropdowns for deposits, withdrawals &amp; repayments</span>
                        </label>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary">Save Account</button>
                        <a href="{{ route('accounts.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
