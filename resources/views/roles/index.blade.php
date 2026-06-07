@extends('layouts.app')
@section('title', 'Roles & Permissions')
@section('breadcrumb')
    <li class="breadcrumb-item active">Roles &amp; Permissions</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Roles &amp; Permissions</h4>
        <p class="text-muted small mb-0">Control what each role can access. Changes take effect immediately.</p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newRoleModal">
        <i class="bi bi-plus-lg me-1"></i>New Role
    </button>
</div>

@php $systemRoles = ['super_admin','admin','cashier','staff','client','group_leader','group_member']; @endphp

<div class="row g-3">
@foreach($roles as $role)
@php
    $isPortal  = in_array($role->name, ['client', 'group_leader', 'group_member']);
    $isSystem  = in_array($role->name, $systemRoles);
    $isCustom  = !$isSystem;
    $badgeClass = match($role->name) {
        'super_admin'   => 'bg-danger',
        'admin'         => 'bg-primary',
        'cashier'       => 'bg-success',
        'staff'         => 'bg-secondary',
        'group_leader',
        'group_member',
        'client'        => 'bg-light text-dark border',
        default         => 'bg-warning text-dark',
    };
@endphp
<div class="col-md-4">
    <div class="card h-100 {{ $isCustom ? 'border-warning' : '' }}">
        <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge {{ $badgeClass }}">{{ ucfirst(str_replace('_',' ',$role->name)) }}</span>
                @if($isCustom)
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle ms-1" style="font-size:10px">Custom</span>
                @endif
                <span class="text-muted small ms-auto">{{ $role->permissions_count }} perm{{ $role->permissions_count != 1 ? 's' : '' }}</span>
            </div>

            @if($isPortal)
                <p class="text-muted small mb-3 flex-grow-1">Portal role — no admin panel access.</p>
                <span class="text-muted small fst-italic">No changes needed</span>
            @else
                <p class="text-muted small mb-3 flex-grow-1">
                    @if($role->name === 'super_admin') Full system access — all permissions.
                    @elseif($role->name === 'admin') Admin access excluding backup management.
                    @elseif($role->name === 'cashier') Day-to-day operational access.
                    @elseif($role->name === 'staff') Read-only access to most modules.
                    @else Custom role — configure permissions below.
                    @endif
                </p>
                <div class="d-flex gap-2">
                    <a href="{{ route('roles.edit', $role) }}" class="btn btn-sm btn-outline-primary flex-grow-1">
                        <i class="bi bi-shield-check me-1"></i>Edit Permissions
                    </a>
                    @if($isCustom)
                    <form method="POST" action="{{ route('roles.destroy', $role) }}"
                          onsubmit="return confirm('Delete role \'{{ $role->name }}\'? This cannot be undone.')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete role">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@endforeach
</div>

<div class="alert alert-info mt-4">
    <i class="bi bi-info-circle me-2"></i>
    <strong>Tip:</strong> To give a single user extra access beyond their role, go to
    <a href="{{ route('users.index') }}">Users</a>, open the user, and use the <strong>Direct Permissions</strong> section.
</div>

{{-- New Role Modal --}}
<div class="modal fade" id="newRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('roles.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-shield-plus me-2"></i>Create New Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Role Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="roleNameInput" class="form-control @error('name') is-invalid @enderror"
                               placeholder="e.g. hr_manager" value="{{ old('name') }}"
                               pattern="[a-z0-9_]+" required>
                        <div class="form-text">Lowercase letters, numbers and underscores only. E.g. <code>hr_manager</code>, <code>branch_officer</code></div>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="alert alert-light border small mb-0">
                        <i class="bi bi-lightbulb me-1 text-warning"></i>
                        After creating the role, click <strong>Edit Permissions</strong> on it to assign what it can access.
                        Then assign the role to users from the <a href="{{ route('users.index') }}">Users</a> page.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Create Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Auto-format role name: spaces → underscores, lowercase
document.getElementById('roleNameInput')?.addEventListener('input', function () {
    this.value = this.value.toLowerCase().replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, '');
});
// Re-open modal if validation failed
@if($errors->has('name'))
    document.addEventListener('DOMContentLoaded', () => {
        new bootstrap.Modal(document.getElementById('newRoleModal')).show();
    });
@endif
</script>
@endpush
@endsection
