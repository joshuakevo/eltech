@extends('layouts.app')
@section('title','Client Segments')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0 fw-semibold">Client Segments</h4>
        <p class="text-muted small mb-0">Group clients into segments (e.g. Konnect Business, Konnect Sacco, Venture Capital)</p>
    </div>
    <a href="{{ route('client-segments.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> New Segment
    </a>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Segment</th>
                    <th>Description</th>
                    <th class="text-center">Clients</th>
                    <th class="text-center">Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($segments as $segment)
                <tr>
                    <td class="fw-semibold">{{ $segment->name }}</td>
                    <td class="text-muted">{{ $segment->description ? Str::limit($segment->description, 60) : '—' }}</td>
                    <td class="text-center">{{ $segment->clients_count }}</td>
                    <td class="text-center">
                        @if($segment->is_active)
                            <span class="badge bg-success-subtle text-success">Active</span>
                        @else
                            <span class="badge bg-danger-subtle text-danger">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('client-segments.edit', $segment) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-pencil"></i>
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center py-4 text-muted">No segments found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($segments->hasPages())
    <div class="card-footer">{{ $segments->links() }}</div>
    @endif
</div>
@endsection
