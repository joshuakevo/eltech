@extends('layouts.app')
@section('title', 'Edit Employee')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('employees.index') }}">Employees</a></li>
    <li class="breadcrumb-item active">Edit — {{ $employee->name }}</li>
@endsection
@section('content')
<div class="card shadow-sm">
    <div class="card-header py-2 px-3 fw-semibold">Edit Employee — {{ $employee->employee_number }}</div>
    <div class="card-body p-3">
    <form method="POST" action="{{ route('employees.update', $employee) }}">
        @csrf @method('PUT')

        <div class="row g-2 g-lg-3">
            <div class="col-lg-5">
                <label class="form-label small mb-0 fw-semibold">Client</label>
                <input type="text" class="form-control form-control-sm fw-semibold bg-light" readonly value="{{ $employee->name }}">
            </div>
            <div class="col-lg-7">
                <label class="form-label small mb-0 fw-semibold">Salary savings account <span class="text-danger">*</span></label>
                <select name="savings_account_id" class="form-select form-select-sm @error('savings_account_id') is-invalid @enderror" required>
                    @foreach($savingsAccounts as $sa)
                    <option value="{{ $sa->id }}" {{ old('savings_account_id', $employee->savings_account_id) == $sa->id ? 'selected' : '' }}>
                        {{ $sa->account_number }} — {{ $sa->product->name ?? '' }}
                    </option>
                    @endforeach
                </select>
                @error('savings_account_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label class="form-label small mb-0 fw-semibold">Position</label>
                <input type="text" name="position" class="form-control form-control-sm" value="{{ old('position', $employee->position) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-0 fw-semibold">Department</label>
                <input type="text" name="department" class="form-control form-control-sm" value="{{ old('department', $employee->department) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-0 fw-semibold">Basic salary (UGX) <span class="text-danger">*</span></label>
                <input type="number" name="basic_salary" class="form-control form-control-sm @error('basic_salary') is-invalid @enderror"
                       value="{{ old('basic_salary', $employee->basic_salary) }}" min="0" step="1000" required>
                @error('basic_salary')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label small mb-0 fw-semibold">Status <span class="text-danger">*</span></label>
                <select name="status" class="form-select form-select-sm" required>
                    <option value="active"   {{ old('status', $employee->status) === 'active'   ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ old('status', $employee->status) === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <div class="col-md-9">
                <details class="border rounded px-2 py-1 bg-light">
                    <summary class="small fw-semibold user-select-none" style="cursor:pointer">Notes <span class="text-muted fw-normal">(optional)</span></summary>
                    <textarea name="notes" class="form-control form-control-sm mt-1" rows="1" placeholder="Optional">{{ old('notes', $employee->notes) }}</textarea>
                </details>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 pt-3 mt-2 border-top">
            <button type="submit" class="btn btn-primary btn-sm">Update employee</button>
            <a href="{{ route('employees.show', $employee) }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
        </div>
    </form>
    </div>
</div>
@endsection
