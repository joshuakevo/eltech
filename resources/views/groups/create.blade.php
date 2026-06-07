@extends('layouts.app')
@section('title', 'New Group')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('groups.index') }}">Groups</a></li>
    <li class="breadcrumb-item active">New Group</li>
@endsection
@section('content')
<div class="mb-4"><h4 class="fw-bold mb-0">Register New Group</h4></div>

<div class="card" style="max-width:660px">
    <div class="card-body">
        <form method="POST" action="{{ route('groups.store') }}">
            @csrf

            {{-- Type selector --}}
            <div class="mb-4">
                <label class="form-label fw-semibold">Group Type <span class="text-danger">*</span></label>
                <div class="row g-2">
                    <div class="col-6">
                        <input type="radio" class="btn-check" name="group_type" id="type_savings" value="savings"
                               {{ old('group_type','savings') === 'savings' ? 'checked' : '' }}>
                        <label class="btn btn-outline-primary w-100 text-start p-3" for="type_savings">
                            <i class="bi bi-piggy-bank-fill d-block fs-4 mb-1"></i>
                            <strong>Savings Group</strong>
                            <div class="small text-muted mt-1">Each member has their own balance. Deposits and withdrawals are per member.</div>
                        </label>
                    </div>
                    <div class="col-6">
                        <input type="radio" class="btn-check" name="group_type" id="type_pooled" value="pooled"
                               {{ old('group_type') === 'pooled' ? 'checked' : '' }}>
                        <label class="btn btn-outline-success w-100 text-start p-3" for="type_pooled">
                            <i class="bi bi-safe2-fill d-block fs-4 mb-1"></i>
                            <strong>Pooled Group</strong>
                            <div class="small text-muted mt-1">Members contribute to a shared pot. Withdrawals are from the group balance.</div>
                        </label>
                    </div>
                </div>
                @error('group_type')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Group Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Registration Date <span class="text-danger">*</span></label>
                    <input type="date" name="registration_date" class="form-control @error('registration_date') is-invalid @enderror" value="{{ old('registration_date', today()->toDateString()) }}" required>
                    @error('registration_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Membership Fee</label>
                    <input type="number" name="membership_fee" class="form-control @error('membership_fee') is-invalid @enderror" value="{{ old('membership_fee', 0) }}" min="0" step="0.01">
                    @error('membership_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- Savings group fields --}}
            <div id="savingsFields">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Monthly Interest Rate (%)</label>
                        <input type="number" name="monthly_interest_rate" class="form-control @error('monthly_interest_rate') is-invalid @enderror"
                               value="{{ old('monthly_interest_rate', 0) }}" min="0" max="100" step="0.0001">
                        @error('monthly_interest_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">GL Savings Account</label>
                        <select name="gl_account_id" class="form-select">
                            <option value="">— Default (Group Member Savings 2005) —</option>
                            @foreach($glAccounts as $acc)
                            <option value="{{ $acc->id }}" {{ old('gl_account_id') == $acc->id ? 'selected' : '' }}>
                                {{ $acc->account_code }} — {{ $acc->account_name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- Pooled group fields --}}
            <div id="pooledFields" style="display:none">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Expected Contribution <span class="text-danger">*</span></label>
                        <input type="number" name="expected_contribution" class="form-control @error('expected_contribution') is-invalid @enderror"
                               value="{{ old('expected_contribution') }}" min="0" step="0.01" placeholder="Amount per member per cycle">
                        @error('expected_contribution')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Contribution Cycle <span class="text-danger">*</span></label>
                        <select name="contribution_cycle" class="form-select @error('contribution_cycle') is-invalid @enderror">
                            <option value="">— Select cycle —</option>
                            <option value="weekly"    {{ old('contribution_cycle') === 'weekly'    ? 'selected' : '' }}>Weekly</option>
                            <option value="biweekly"  {{ old('contribution_cycle') === 'biweekly'  ? 'selected' : '' }}>Bi-weekly (twice a month)</option>
                            <option value="monthly"   {{ old('contribution_cycle') === 'monthly'   ? 'selected' : '' }}>Monthly</option>
                            <option value="quarterly" {{ old('contribution_cycle') === 'quarterly' ? 'selected' : '' }}>Quarterly</option>
                        </select>
                        @error('contribution_cycle')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">GL Account</label>
                        <select name="gl_account_id_pooled" class="form-select">
                            <option value="">— Default (Group Member Savings 2005) —</option>
                            @foreach($glAccounts as $acc)
                            <option value="{{ $acc->id }}" {{ old('gl_account_id') == $acc->id ? 'selected' : '' }}>
                                {{ $acc->account_code }} — {{ $acc->account_name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Notes</label>
                <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Create Group</button>
                <a href="{{ route('groups.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
const radios = document.querySelectorAll('input[name="group_type"]');
const savingsFields = document.getElementById('savingsFields');
const pooledFields  = document.getElementById('pooledFields');
const pooledGl      = document.querySelector('select[name="gl_account_id_pooled"]');
const savingsGl     = document.querySelector('select[name="gl_account_id"]');

function toggleType() {
    const isPooled = document.getElementById('type_pooled').checked;
    savingsFields.style.display = isPooled ? 'none' : '';
    pooledFields.style.display  = isPooled ? '' : 'none';
    // sync the GL account hidden field
    if (isPooled) savingsGl.name = '_gl_account_id_disabled';
    else {
        savingsGl.name  = 'gl_account_id';
        pooledGl.name   = 'gl_account_id_pooled';
    }
    if (isPooled) pooledGl.name = 'gl_account_id';
}

radios.forEach(r => r.addEventListener('change', toggleType));
toggleType();
</script>
@endpush
@endsection
