@extends('layouts.app')
@section('title', 'Deposit')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('savings.index') }}">Savings</a></li>
    <li class="breadcrumb-item"><a href="{{ route('savings.show', $saving) }}">{{ $saving->account_number }}</a></li>
    <li class="breadcrumb-item active">Deposit</li>
@endsection
@section('content')
<div class="alert alert-info small mb-3">
    <strong>Current Balance:</strong> {{ number_format($saving->balance, $dp) }} &nbsp;|&nbsp;
    <strong>Account:</strong> {{ $saving->account_number }} — {{ $saving->client->name }}
</div>
<div class="card">
    <div class="card-header">Deposit — {{ $saving->account_number }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('savings.deposit', $saving) }}">
            @csrf
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                    <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                    <input type="date" name="date" class="form-control" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Payment Source</label>
                    <select name="payment_source_account_id" class="form-select" required>
                        <option value="">— Select GL account —</option>
                        @foreach($paymentSourceAccounts as $acc)
                        <option value="{{ $acc->id }}" {{ old('payment_source_account_id') == $acc->id ? 'selected' : '' }}>
                            {{ $acc->account_code }} — {{ $acc->account_name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Description</label>
                    <input type="text" name="description" class="form-control" value="{{ old('description', 'Cash Deposit') }}">
                </div>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Receipt / Reference <span class="text-danger">*</span></label>
                    <input type="text" name="reference" class="form-control" value="{{ old('reference') }}" required>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-success">Post Deposit</button>
                <a href="{{ route('savings.show', $saving) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
