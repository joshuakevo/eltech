@extends('layouts.app')
@section('title', 'Edit Role — ' . ucfirst(str_replace('_',' ',$role->name)))
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('roles.index') }}">Roles &amp; Permissions</a></li>
    <li class="breadcrumb-item active">{{ ucfirst(str_replace('_',' ',$role->name)) }}</li>
@endsection
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">
            Edit Permissions &mdash; <span class="text-primary">{{ ucfirst(str_replace('_',' ',$role->name)) }}</span>
        </h4>
        <p class="text-muted small mb-0">Check the permissions this role should have. Changes take effect on next login.</p>
    </div>
    <a href="{{ route('roles.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

@if($role->name === 'super_admin')
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>Super Admin</strong> always has all permissions and cannot be edited.
</div>
@else

<form method="POST" action="{{ route('roles.update', $role) }}">
@csrf @method('PUT')

{{-- Toolbar --}}
<div class="d-flex align-items-center gap-3 mb-3">
    <div class="input-group" style="max-width:260px">
        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
        <input type="text" id="permSearch" class="form-control border-start-0" placeholder="Search permissions...">
    </div>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAll(true)">
        <i class="bi bi-check-all me-1"></i>Select All
    </button>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAll(false)">
        <i class="bi bi-x-lg me-1"></i>Deselect All
    </button>
    <span class="text-muted small ms-auto" id="globalCount"></span>
</div>

{{-- Permission categories --}}
<div class="row g-3 mb-4" id="categoryGrid">
@foreach($categories as $label => $cat)
<div class="col-md-4 category-col" data-label="{{ strtolower($label) }}">
    <div class="card h-100 border">
        <div class="card-header py-2 d-flex align-items-center justify-content-between"
             style="background:#f8f9fa">
            <span class="fw-semibold small">
                <i class="bi {{ $cat['icon'] }} me-2 text-primary"></i>{{ $label }}
            </span>
            <button type="button" class="btn btn-link btn-sm p-0 text-muted text-decoration-none"
                    onclick="toggleSection('{{ Str::slug($label) }}', this)"
                    data-state="mixed">
                <span class="toggle-label small">Select all</span>
            </button>
        </div>
        <div class="card-body py-2 px-3" id="section-{{ Str::slug($label) }}">
            @foreach($cat['permissions'] as $perm)
            @php
                $parts   = explode(' ', $perm);
                $action  = $parts[0];          // view / create / manage …
                $subject = implode(' ', array_slice($parts, 1));
                $checked = in_array($perm, $rolePermissions);

                $actionBadge = match($action) {
                    'view'    => 'bg-success-subtle text-success',
                    'create'  => 'bg-primary-subtle text-primary',
                    'edit'    => 'bg-warning-subtle text-warning',
                    'delete'  => 'bg-danger-subtle text-danger',
                    'manage'  => 'bg-info-subtle text-info',
                    'use'     => 'bg-secondary-subtle text-secondary',
                    'process' => 'bg-info-subtle text-info',
                    default   => 'bg-secondary-subtle text-secondary',
                };
            @endphp
            <div class="form-check perm-item d-flex align-items-center gap-2 py-1 border-bottom" data-perm="{{ $perm }}">
                <input class="form-check-input perm-check flex-shrink-0 mt-0"
                       type="checkbox"
                       name="permissions[]"
                       value="{{ $perm }}"
                       id="perm_{{ Str::slug($perm) }}"
                       {{ $checked ? 'checked' : '' }}>
                <label class="form-check-label d-flex align-items-center gap-2 w-100 mb-0" for="perm_{{ Str::slug($perm) }}" style="cursor:pointer">
                    <span class="badge {{ $actionBadge }}" style="min-width:58px;font-size:10px;font-weight:600">{{ ucfirst($action) }}</span>
                    <span class="small">{{ ucfirst(str_replace('-',' ',$subject)) }}</span>
                </label>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endforeach
</div>

<div class="d-flex gap-2 align-items-center">
    <button type="submit" class="btn btn-primary px-4">
        <i class="bi bi-check-lg me-1"></i>Save Permissions
    </button>
    <a href="{{ route('roles.index') }}" class="btn btn-outline-secondary">Cancel</a>
    <span class="text-muted small ms-3" id="savedCount"></span>
</div>

</form>
@endif

@push('scripts')
<script>
function toggleAll(state) {
    document.querySelectorAll('.perm-check').forEach(cb => cb.checked = state);
    updateCount();
}

function toggleSection(sectionId, btn) {
    const section  = document.getElementById('section-' + sectionId);
    const boxes    = section.querySelectorAll('.perm-check');
    const anyUnchecked = [...boxes].some(cb => !cb.checked);
    boxes.forEach(cb => cb.checked = anyUnchecked);
    btn.querySelector('.toggle-label').textContent = anyUnchecked ? 'Deselect all' : 'Select all';
    updateCount();
}

function updateCount() {
    const total   = document.querySelectorAll('.perm-check').length;
    const checked = document.querySelectorAll('.perm-check:checked').length;
    const el = document.getElementById('globalCount');
    if (el) el.textContent = checked + ' of ' + total + ' permissions selected';
}

document.querySelectorAll('.perm-check').forEach(cb => cb.addEventListener('change', updateCount));
updateCount();

// Live search — hides non-matching rows, hides entire card if nothing matches
document.getElementById('permSearch')?.addEventListener('input', function () {
    const q = this.value.toLowerCase().trim();

    document.querySelectorAll('.category-col').forEach(col => {
        let visibleCount = 0;
        col.querySelectorAll('.perm-item').forEach(item => {
            const match = !q || item.dataset.perm.includes(q);
            item.style.display = match ? '' : 'none';
            if (match) visibleCount++;
        });
        col.style.display = visibleCount === 0 && q ? 'none' : '';
    });
});
</script>
@endpush
@endsection
