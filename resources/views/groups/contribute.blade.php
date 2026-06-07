@extends('layouts.app')
@section('title', 'Record Contribution')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('groups.index') }}">Groups</a></li>
    <li class="breadcrumb-item"><a href="{{ route('groups.show', $group) }}">{{ $group->name }}</a></li>
    <li class="breadcrumb-item active">Record Contribution</li>
@endsection
@section('content')
<div class="row g-3">
<div class="col-md-7">

<div class="alert alert-info d-flex gap-3 align-items-start mb-3">
    <i class="bi bi-safe2-fill fs-4"></i>
    <div>
        <div class="fw-semibold">{{ $group->name }}</div>
        <div class="small">
            Pool Balance: <strong>{{ number_format($group->pool_balance, 0) }}</strong>
            &nbsp;&bull;&nbsp;
            Expected {{ ucfirst($group->contribution_cycle) }}: <strong>{{ number_format($group->expected_contribution, 0) }}</strong>
            &nbsp;&bull;&nbsp;
            {{ $members->count() }} active members
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header fw-semibold"><i class="bi bi-plus-circle text-success me-2"></i>Record Member Contribution</div>
    <div class="card-body">
        <form method="POST" action="{{ route('groups.contribute.store', $group) }}">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-semibold">Member <span class="text-danger">*</span></label>
                <select name="member_id" class="form-select ts-select @error('member_id') is-invalid @enderror" required>
                    <option value="">— Select member —</option>
                    @foreach($members as $m)
                    <option value="{{ $m->id }}" {{ old('member_id') == $m->id ? 'selected' : '' }}>
                        {{ $m->name }}{{ $m->is_leader ? ' (Leader)' : '' }}
                        — Total contributed: {{ number_format($m->balance, 0) }}
                    </option>
                    @endforeach
                </select>
                @error('member_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">{{ \App\Models\SystemSetting::get('currency','KES') }}</span>
                        <input type="number" name="amount" class="form-control @error('amount') is-invalid @enderror"
                               step="0.01" min="0.01"
                               value="{{ old('amount', $group->expected_contribution) }}" required>
                    </div>
                    @error('amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @if($group->expected_contribution)
                    <div class="form-text">Expected: {{ number_format($group->expected_contribution, 0) }} / {{ $group->contribution_cycle }}</div>
                    @endif
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                    <input type="date" name="transaction_date" class="form-control @error('transaction_date') is-invalid @enderror"
                           value="{{ old('transaction_date', today()->toDateString()) }}"
                           max="{{ today()->toDateString() }}" required>
                    @error('transaction_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Receipt / Reference <span class="text-danger">*</span></label>
                    <input type="text" name="reference" class="form-control" value="{{ old('reference') }}" placeholder="e.g. RCT-001" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Notes</label>
                    <input type="text" name="notes" class="form-control" value="{{ old('notes') }}" placeholder="Optional note">
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success px-4"><i class="bi bi-check-circle me-1"></i>Post Contribution</button>
                <a href="{{ route('groups.show', $group) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

</div>
</div>
@endsection
