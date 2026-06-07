@extends('layouts.app')
@section('title', 'Add Employee')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('employees.index') }}">Employees</a></li>
    <li class="breadcrumb-item active">Add</li>
@endsection
@section('content')
<div class="card shadow-sm">
    <div class="card-header py-2 px-3 fw-semibold">Add Employee</div>
    <div class="card-body p-3">
    <form method="POST" action="{{ route('employees.store') }}">
        @csrf

        <div class="row g-2 g-lg-3">
            <div class="col-lg-5">
                <label class="form-label small mb-0 fw-semibold">Client <span class="text-danger">*</span></label>
                <select name="client_id" id="clientSelect" class="form-select form-select-sm @error('client_id') is-invalid @enderror" required>
                    <option value="">— Select client —</option>
                    @foreach($clients as $client)
                    <option value="{{ $client->id }}" {{ old('client_id') == $client->id ? 'selected' : '' }}>
                        {{ $client->name }}
                    </option>
                    @endforeach
                </select>
                @error('client_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="form-text lh-sm" style="font-size:.7rem">Only clients with an active savings account.</div>
            </div>
            <div class="col-lg-7">
                <label class="form-label small mb-0 fw-semibold">Salary savings account <span class="text-danger">*</span></label>
                <select name="savings_account_id" id="savingsSelect" class="form-select form-select-sm @error('savings_account_id') is-invalid @enderror" required>
                    <option value="">— Select client first —</option>
                </select>
                @error('savings_account_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label class="form-label small mb-0 fw-semibold">Position</label>
                <input type="text" name="position" class="form-control form-control-sm" value="{{ old('position') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-0 fw-semibold">Department</label>
                <input type="text" name="department" class="form-control form-control-sm" value="{{ old('department') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-0 fw-semibold">Basic salary (UGX) <span class="text-danger">*</span></label>
                <input type="number" name="basic_salary" class="form-control form-control-sm @error('basic_salary') is-invalid @enderror"
                       value="{{ old('basic_salary', 0) }}" min="0" step="1000" required>
                @error('basic_salary')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label small mb-0 fw-semibold">Status <span class="text-danger">*</span></label>
                <select name="status" class="form-select form-select-sm" required>
                    <option value="active" {{ old('status','active') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <div class="col-md-9">
                <details class="border rounded px-2 py-1 bg-light">
                    <summary class="small fw-semibold user-select-none" style="cursor:pointer">Notes <span class="text-muted fw-normal">(optional)</span></summary>
                    <textarea name="notes" class="form-control form-control-sm mt-1" rows="1" placeholder="Optional">{{ old('notes') }}</textarea>
                </details>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 pt-3 mt-2 border-top">
            <button type="submit" class="btn btn-primary btn-sm">Save employee</button>
            <a href="{{ route('employees.index') }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
        </div>
    </form>
    </div>
</div>

@push('scripts')
<script>
const allAccounts = @json($savingsAccounts);
const oldClientId  = '{{ old('client_id') }}';
const oldAccountId = '{{ old('savings_account_id') }}';

const clientSel  = document.getElementById('clientSelect');
const savingsSel = document.getElementById('savingsSelect');

function populateAccounts(clientId, selectedId) {
    savingsSel.innerHTML = '<option value="">— Select account —</option>';
    const accounts = allAccounts[clientId] || [];
    accounts.forEach(sa => {
        const opt = document.createElement('option');
        opt.value = sa.id;
        opt.textContent = sa.account_number + ' — ' + (sa.product ? sa.product.name : '');
        if (String(sa.id) === String(selectedId)) opt.selected = true;
        savingsSel.appendChild(opt);
    });
    if (accounts.length === 1) savingsSel.selectedIndex = 1;
}

clientSel.addEventListener('change', () => populateAccounts(clientSel.value, ''));

if (oldClientId) populateAccounts(oldClientId, oldAccountId);
</script>
@endpush
@endsection
