@extends('layouts.client-portal')
@section('title', 'Mobile Money Withdrawal')
@section('content')

<div class="mb-4">
    <a href="{{ route('client-portal.mobile-money.index') }}" class="text-muted text-decoration-none small">
        <i class="bi bi-arrow-left me-1"></i>Mobile Money
    </a>
    <h5 class="fw-bold mb-0 mt-1">Withdraw via Mobile Money</h5>
</div>

<div class="row justify-content-center">
<div class="col-md-8">
<div class="card">
    <div class="card-body">
        <div class="alert alert-info small mb-3">
            <i class="bi bi-info-circle me-1"></i>
            Withdrawal requests are reviewed by our staff before payout. Your account is only debited once approved.
        </div>
        @if($accounts->isEmpty())
            <p class="text-muted small mb-0">You don't have any active savings accounts to withdraw from.</p>
        @else
        <form method="POST" action="{{ route('client-portal.mobile-money.withdraw') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-semibold">Savings Account</label>
                <select name="savings_account_id" class="form-select" required>
                    @foreach($accounts as $acc)
                    <option value="{{ $acc->id }}" @selected(old('savings_account_id')==$acc->id)>
                        {{ $acc->account_number }} — {{ $acc->product->name }} (Balance: {{ number_format($acc->balance, 2) }})
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Amount (UGX)</label>
                <input type="number" name="amount" class="form-control" step="1" min="{{ $minAmount }}"
                       value="{{ old('amount') }}" placeholder="Minimum {{ number_format($minAmount, 0) }}" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Mobile Money Phone Number</label>
                <input type="text" name="phone_number" class="form-control"
                       value="{{ old('phone_number', $client->phone) }}" placeholder="+2567xxxxxxxx" required>
                <div class="form-text">Where the payout will be sent once approved.</div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-danger"><i class="bi bi-check-lg me-1"></i>Request Withdrawal</button>
                <a href="{{ route('client-portal.mobile-money.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
        @endif
    </div>
</div>
</div>
</div>
@endsection
