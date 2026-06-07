@extends('layouts.app')
@section('title', 'Transfer')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('savings.index') }}">Savings</a></li>
    <li class="breadcrumb-item"><a href="{{ route('savings.show', $saving) }}">{{ $saving->account_number }}</a></li>
    <li class="breadcrumb-item active">Transfer</li>
@endsection
@section('content')
@php $defaultFee = $saving->product->withdrawal_fee ?? 0; @endphp
<div class="alert alert-info small mb-3">
    <strong>From Balance:</strong> {{ number_format($saving->balance, $dp) }} &nbsp;|&nbsp;
    <strong>Withdraw Fee:</strong> {{ number_format($defaultFee, $dp) }}
</div>
<div class="card">
    <div class="card-header">Transfer — From {{ $saving->account_number }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('savings.transfer', $saving) }}">
            @csrf
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">From Account</label>
                    <input type="text" class="form-control"
                        value="{{ $saving->account_number }} — {{ $saving->client->name }} ({{ number_format($saving->balance, $dp) }})" disabled>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">To Account <span class="text-danger">*</span></label>
                    <select name="to_account_id" class="form-select" required>
                        <option value="">Select destination...</option>
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}">{{ $acc->account_number }} — {{ $acc->client->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                    <input type="number" name="amount" id="transferAmount" class="form-control"
                        step="0.01" min="0.01" value="{{ old('amount') }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                    <input type="date" name="date" class="form-control"
                        value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Withdrawal Fee</label>
                    <input type="number" name="withdrawal_fee" id="transferFee" class="form-control"
                        step="0.01" min="0" value="{{ old('withdrawal_fee', 0) }}">
                    <div class="form-text">Default: {{ number_format($defaultFee, $dp) }} — 0 to waive</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Total Deduction</label>
                    <div class="form-control bg-light fw-semibold" id="transferTotal">—</div>
                    <div class="form-text text-muted">Amount + Fee</div>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-info text-white">Transfer</button>
                <a href="{{ route('savings.show', $saving) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
@push('scripts')
<script>
var dp = {{ $dp }};
function fmt(v) { return parseFloat(v || 0).toFixed(dp); }
function updateTotal() {
    var amt = parseFloat(document.getElementById('transferAmount').value) || 0;
    var fee = parseFloat(document.getElementById('transferFee').value) || 0;
    document.getElementById('transferTotal').textContent = fmt(amt + fee);
}
document.getElementById('transferAmount').addEventListener('input', updateTotal);
document.getElementById('transferFee').addEventListener('input', updateTotal);
updateTotal();
</script>
@endpush
