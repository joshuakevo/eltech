@extends('layouts.app')
@section('title','New Branch')
@section('content')
<div class="mb-4">
    <a href="{{ route('branches.index') }}" class="text-muted text-decoration-none small">
        <i class="bi bi-arrow-left me-1"></i>Branches
    </a>
    <h4 class="mb-0 fw-semibold mt-1">New Branch</h4>
</div>

<div class="row justify-content-center">
<div class="col-lg-7">
<div class="card shadow-sm">
<div class="card-body p-4">
<form method="POST" action="{{ route('branches.store') }}">
@csrf
<div class="row g-3">
    <div class="col-sm-8">
        <label class="form-label fw-semibold">Branch Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name') }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-sm-4">
        <label class="form-label fw-semibold">Branch Code <span class="text-danger">*</span></label>
        <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
               value="{{ old('code') }}" placeholder="e.g. HQ, BR01" required>
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label class="form-label fw-semibold">Address</label>
        <input type="text" name="address" class="form-control" value="{{ old('address') }}">
    </div>
    <div class="col-sm-6">
        <label class="form-label fw-semibold">Phone</label>
        <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
    </div>
    <div class="col-sm-6">
        <label class="form-label fw-semibold">Email</label>
        <input type="email" name="email" class="form-control" value="{{ old('email') }}">
    </div>
    <div class="col-12">
        <label class="form-label fw-semibold">Branch Manager</label>
        <input type="text" name="manager_name" class="form-control" value="{{ old('manager_name') }}">
    </div>
    <div class="col-12">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                   value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>
</div>
<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">Create Branch</button>
    <a href="{{ route('branches.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
</form>
</div>
</div>
</div>
</div>
@endsection
