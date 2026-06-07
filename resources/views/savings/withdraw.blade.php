@extends('layouts.app')
@section('title', 'Withdrawal')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('savings.index') }}">Savings</a></li>
    <li class="breadcrumb-item"><a href="{{ route('savings.show', $saving) }}">{{ $saving->account_number }}</a></li>
    <li class="breadcrumb-item active">Withdraw</li>
@endsection
@section('content')
@php $defaultFee = $saving->product->withdrawal_fee ?? 0; @endphp
<div class="alert alert-warning small mb-3">
    <strong>Balance:</strong> {{ number_format($saving->balance, $dp) }} &nbsp;|&nbsp;
    <strong>Min Balance:</strong> {{ number_format($saving->product->minimum_balance, $dp) }} &nbsp;|&nbsp;
    <strong>Withdraw Fee:</strong> {{ number_format($defaultFee, $dp) }}
</div>
<div class="card">
    <div class="card-header">Withdrawal — {{ $saving->account_number }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('savings.withdraw', $saving) }}">
            @csrf
            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                    <input type="number" name="amount" id="withdrawAmount" class="form-control"
                        step="0.01" min="0.01" value="{{ old('amount') }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                    <input type="date" name="date" class="form-control"
                        value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Withdrawal Fee</label>
                    <input type="number" name="withdrawal_fee" id="withdrawFee" class="form-control"
                        step="0.01" min="0" value="{{ old('withdrawal_fee', $defaultFee) }}">
                    <div class="form-text">Default: {{ number_format($defaultFee, $dp) }} — set to 0 to waive</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Total Deduction</label>
                    <div class="form-control bg-light fw-semibold" id="totalDeduction">—</div>
                    <div class="form-text text-muted">Amount + Fee</div>
                </div>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
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
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Description</label>
                    <input type="text" name="description" class="form-control"
                        value="{{ old('description', 'Cash Withdrawal') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Receipt / Reference <span class="text-danger">*</span></label>
                    <input type="text" name="reference" class="form-control" value="{{ old('reference') }}" required>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-warning">Process Withdrawal</button>
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
    var amt = parseFloat(document.getElementById('withdrawAmount').value) || 0;
    var fee = parseFloat(document.getElementById('withdrawFee').value) || 0;
    document.getElementById('totalDeduction').textContent = fmt(amt + fee);
}
document.getElementById('withdrawAmount').addEventListener('input', updateTotal);
document.getElementById('withdrawFee').addEventListener('input', updateTotal);
updateTotal();
</script>
@endpush
