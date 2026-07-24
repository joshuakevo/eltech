@extends('layouts.app')
@section('title','New Client Segment')
@section('content')
<div class="mb-4">
    <a href="{{ route('client-segments.index') }}" class="text-muted text-decoration-none small">
        <i class="bi bi-arrow-left me-1"></i>Client Segments
    </a>
    <h4 class="mb-0 fw-semibold mt-1">New Segment</h4>
</div>

<div class="row justify-content-center">
<div class="col-lg-7">
<div class="card shadow-sm">
<div class="card-body p-4">
<form method="POST" action="{{ route('client-segments.store') }}">
@csrf
<div class="row g-3">
    <div class="col-12">
        <label class="form-label fw-semibold">Segment Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name') }}" placeholder="e.g. Konnect Business" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label class="form-label fw-semibold">Description</label>
        <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
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
    <button type="submit" class="btn btn-primary">Create Segment</button>
    <a href="{{ route('client-segments.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
</form>
</div>
</div>
</div>
</div>
@endsection
