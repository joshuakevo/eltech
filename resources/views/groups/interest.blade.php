@extends('layouts.app')
@section('title', 'Post Monthly Interest')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('groups.index') }}">Groups</a></li>
    <li class="breadcrumb-item"><a href="{{ route('groups.show', $group) }}">{{ $group->name }}</a></li>
    <li class="breadcrumb-item active">Post Interest</li>
@endsection
@section('content')
<div class="mb-4"><h4 class="fw-bold mb-0">Post Monthly Interest — {{ $group->name }}</h4></div>

<div class="alert alert-info mb-3" style="max-width:640px">
    <i class="bi bi-info-circle me-1"></i>
    Applies the group monthly rate (<strong>{{ number_format($group->monthly_interest_rate, 4) }}%</strong>)
    to each active member's current balance. Posts a single journal entry (DR Interest Expense / CR Group Savings)
    and credits each member's sub-balance individually.
</div>

<div class="card" style="max-width:520px">
    <div class="card-body">
        <form method="POST" action="{{ route('groups.interest.store', $group) }}">
            @csrf
            @error('interest')
            <div class="alert alert-danger small mb-3">{{ $message }}</div>
            @enderror
            <div class="mb-3">
                <label class="form-label fw-semibold">Posting Date <span class="text-danger">*</span></label>
                <input type="date" name="transaction_date" class="form-control" value="{{ old('transaction_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Notes <small class="text-muted">(optional)</small></label>
                <input type="text" name="notes" class="form-control" value="{{ old('notes') }}" placeholder="e.g. March 2026 interest">
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Post Interest</button>
                <a href="{{ route('groups.show', $group) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
