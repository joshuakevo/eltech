@extends('layouts.app')
@section('title', 'Edit Group Transaction')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('transactions.index') }}">Journal Entries</a></li>
    <li class="breadcrumb-item"><a href="{{ route('transactions.show', $groupTransaction->journal_transaction_id) }}">{{ $groupTransaction->journalTransaction->reference }}</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection
@section('content')
<div class="mb-4">
    <h4 class="fw-bold mb-0">Edit Group {{ ucfirst($groupTransaction->type) }}</h4>
    <span class="text-muted">{{ $groupTransaction->group->name }} — {{ $groupTransaction->member->name }}</span>
</div>

<div class="card" style="max-width:640px">
    <div class="card-body">
        <div class="alert alert-info small">
            Editing this reverses the member's old balance impact and re-applies it with the new
            amount/account — the journal entry's two GL lines are replaced accordingly. Only this
            one member's transaction is affected.
        </div>

        <form method="POST" action="{{ route('groups.transactions.update', $groupTransaction) }}">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label class="form-label fw-semibold">Amount (UGX) <span class="text-danger">*</span></label>
                <input type="number" name="amount" class="form-control @error('amount') is-invalid @enderror"
                       min="0.01" step="0.01" value="{{ old('amount', $groupTransaction->amount) }}" required>
                @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Transaction Date <span class="text-danger">*</span></label>
                    <input type="date" name="transaction_date" class="form-control"
                           value="{{ old('transaction_date', $groupTransaction->transaction_date->toDateString()) }}"
                           max="{{ today()->toDateString() }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Payment Source <span class="text-danger">*</span></label>
                    <select name="payment_source_account_id" class="form-select ts-select" required>
                        <option value="">— Select GL account —</option>
                        @foreach($paymentSourceAccounts as $acc)
                        <option value="{{ $acc->id }}" {{ old('payment_source_account_id', $currentPaymentLine?->account_id) == $acc->id ? 'selected' : '' }}>
                            {{ $acc->account_code }} — {{ $acc->account_name }}
                        </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Notes</label>
                <input type="text" name="notes" class="form-control" value="{{ old('notes', $groupTransaction->notes) }}" placeholder="Optional note…">
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('transactions.show', $groupTransaction->journal_transaction_id) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
