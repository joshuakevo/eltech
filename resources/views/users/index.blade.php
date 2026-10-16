@extends('layouts.app')
@section('title','Users')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0 fw-semibold">Users</h4>
        <p class="text-muted small mb-0">Manage system users and their roles</p>
    </div>
    <a href="{{ route('users.create') }}" class="btn btn-primary">
        <i class="bi bi-person-plus me-1"></i> New User
    </a>
</div>

@php
    $roleColors = ['super_admin'=>'danger','admin'=>'warning','cashier'=>'info','staff'=>'secondary','client'=>'success','group_member'=>'info','group_leader'=>'primary'];
@endphp
<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link {{ !$portal ? 'active fw-semibold' : '' }}" href="{{ route('users.index') }}"><i class="bi bi-person-badge me-1"></i>Staff users <span class="badge bg-secondary-subtle text-secondary ms-1">{{ $counts['staff'] }}</span></a></li>
    <li class="nav-item"><a class="nav-link {{ $portal ? 'active fw-semibold' : '' }}" href="{{ route('users.index', ['type' => 'portal']) }}"><i class="bi bi-phone me-1"></i>Portal users <span class="badge bg-secondary-subtle text-secondary ms-1">{{ $counts['portal'] }}</span></a></li>
</ul>
@if($portal)
<div class="alert alert-light border small py-2">Client and group portal accounts, with the members and groups each one can see. The name is the account's own name — the one shown in the Audit Log.</div>
@endif

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Branch</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">RM</th>
                    <th>Last login</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($users as $user)
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white fw-semibold"
                                 style="width:36px;height:36px;font-size:14px;flex-shrink:0">
                                {{ strtoupper(substr($user->name,0,1)) }}
                            </div>
                            <div>
                                <span class="fw-semibold">{{ $user->name }}</span>
                                @if($portal)
                                    @php $linked = $user->portalClients; $gm = $groupMembers[$user->id] ?? collect(); @endphp
                                    <div class="small text-muted">
                                        @forelse($linked as $lc)
                                            <span class="me-2"><i class="bi bi-person me-1"></i>{{ $lc->name }} ({{ $lc->client_number }})</span>
                                        @empty
                                            @if($user->hasRole('client'))<span class="text-danger me-2">No member linked</span>@endif
                                        @endforelse
                                        @foreach($gm as $m)<span class="me-2"><i class="bi bi-people me-1"></i>{{ $m->group->name ?? 'Group' }}</span>@endforeach
                                        @if($user->hasAnyRole(['group_member','group_leader']) && $gm->isEmpty())<span class="text-warning">group role, no group</span>@endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="text-muted">{{ $user->email }}</td>
                    <td>{{ $user->phone ?? '—' }}</td>
                    <td>
                        @if($portal)
                            @foreach($user->roles as $r)<span class="badge bg-{{ $roleColors[$r->name] ?? 'secondary' }}-subtle text-{{ $roleColors[$r->name] ?? 'secondary' }} me-1">{{ ucfirst(str_replace('_',' ',$r->name)) }}</span>@endforeach
                        @else
                        <span class="badge bg-{{ $roleColors[$user->role_name] ?? 'secondary' }}-subtle text-{{ $roleColors[$user->role_name] ?? 'secondary' }}">
                            {{ ucfirst(str_replace('_',' ',$user->role_name)) }}
                        </span>
                        @endif
                    </td>
                    <td>{{ $user->branch?->name ?? '—' }}</td>
                    <td class="text-center">
                        @if($user->is_active)
                            <span class="badge bg-success-subtle text-success">Active</span>
                        @else
                            <span class="badge bg-danger-subtle text-danger">Inactive</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($user->is_relationship_manager)
                            <span class="badge bg-primary-subtle text-primary">Yes</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="small text-nowrap">
                        @if($user->last_login_at)
                            @php $ago = $user->last_login_at->diffInDays(now()); @endphp
                            <span class="{{ $ago <= 7 ? 'text-success' : ($ago <= 30 ? 'text-warning' : 'text-danger') }}" title="{{ $user->last_login_at->format('d M Y H:i') }}">{{ $user->last_login_at->diffForHumans() }}</span>
                            <div class="text-muted" style="font-size:.7rem">{{ $user->last_login_at->format('d M Y H:i') }}</div>
                        @else
                            <span class="text-muted">Never</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" action="{{ route('users.send-invite', $user) }}"
                                  onsubmit="return confirm('Email {{ $user->name }} a link to set their password and log in?');">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-primary" title="Send login invite email">
                                    <i class="bi bi-envelope"></i>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('users.toggle-rm', $user) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-{{ $user->is_relationship_manager ? 'secondary' : 'primary' }}"
                                        title="{{ $user->is_relationship_manager ? 'Remove from Relationship Manager list' : 'Add to Relationship Manager list' }}">
                                    <i class="bi bi-person-badge"></i>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('users.toggle-status', $user) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-{{ $user->is_active ? 'warning' : 'success' }}"
                                        title="{{ $user->is_active ? 'Deactivate' : 'Activate' }}">
                                    <i class="bi bi-{{ $user->is_active ? 'pause' : 'play' }}"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center py-4 text-muted">No system users found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
