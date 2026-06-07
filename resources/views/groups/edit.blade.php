@extends('layouts.app')
@section('title', 'Edit Group')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('groups.index') }}">Groups</a></li>
    <li class="breadcrumb-item"><a href="{{ route('groups.show', $group) }}">{{ $group->name }}</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection
@section('content')
<div class="mb-4"><h4 class="fw-bold mb-0">Edit Group: {{ $group->name }}</h4></div>

<div class="card" style="max-width:640px">
    <div class="card-body">
        <form method="POST" action="{{ route('groups.update', $group) }}">
            @csrf @method('PUT')
            <div class="mb-3">
                <label class="form-label fw-semibold">Group Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $group->name) }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Registration Date <span class="text-danger">*</span></label>
                    <input type="date" name="registration_date" class="form-control" value="{{ old('registration_date', $group->registration_date->toDateString()) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Membership Fee (UGX)</label>
                    <input type="number" name="membership_fee" class="form-control" value="{{ old('membership_fee', $group->membership_fee) }}" min="0" step="0.01">
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Monthly Interest Rate (%)</label>
                    <input type="number" name="monthly_interest_rate" class="form-control" value="{{ old('monthly_interest_rate', (int) $group->monthly_interest_rate) }}" min="0" max="100" step="1">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="active"   {{ old('status', $group->status) === 'active'    ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ old('status', $group->status) === 'inactive'  ? 'selected' : '' }}>Inactive</option>
                        <option value="dissolved"{{ old('status', $group->status) === 'dissolved' ? 'selected' : '' }}>Dissolved</option>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">GL Savings Account</label>
                <select name="gl_account_id" class="form-select">
                    <option value="">— Default (Group Member Savings 2005) —</option>
                    @foreach($glAccounts as $acc)
                    <option value="{{ $acc->id }}" {{ old('gl_account_id', $group->gl_account_id) == $acc->id ? 'selected' : '' }}>
                        {{ $acc->account_code }} — {{ $acc->account_name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Notes</label>
                <textarea name="notes" class="form-control" rows="2">{{ old('notes', $group->notes) }}</textarea>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('groups.show', $group) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
