@extends('layouts.app')
@section('title', 'Clients Eligible for Closing')
@section('breadcrumb')
    <li class="breadcrumb-item active">Clients Eligible for Closing</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Clients Eligible for Closing</h4>
</div>

<div class="alert alert-info small">
    <i class="bi bi-info-circle me-1"></i>
    These clients have <strong>no savings account, loan, fixed deposit, share, or transaction attached</strong>
    anywhere in the system — never used since registration. Review and mark them inactive if they should be
    closed. Marking inactive is reversible; it does not delete the client record.
</div>

<form class="row g-2 mb-3" method="GET">
    <div class="col-md-4"><input type="text" name="search" class="form-control" placeholder="Name or client #..." value="{{ request('search') }}"></div>
    <div class="col-auto"><button class="btn btn-outline-primary">Filter</button></div>
    <div class="col-auto"><a href="{{ route('client-closure.index') }}" class="btn btn-outline-secondary">Clear</a></div>
</form>

<form method="POST" action="{{ route('client-closure.mark-inactive') }}" id="closureForm"
      onsubmit="return confirm('Mark the selected client(s) inactive? This can be reversed later from their profile.');">
    @csrf
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr>
                    <th class="ps-3"><input type="checkbox" class="form-check-input" id="selectAll"></th>
                    <th>Client #</th><th>Name</th><th>Status</th><th>Segment</th><th>RM</th><th class="pe-3">Joined</th>
                </tr></thead>
                <tbody>
                @forelse($clients as $client)
                    <tr>
                        <td class="ps-3">
                            <input type="checkbox" name="client_ids[]" value="{{ $client->id }}" class="form-check-input client-checkbox">
                        </td>
                        <td class="font-monospace">{{ $client->client_number }}</td>
                        <td><a href="{{ route('clients.show', $client) }}" class="text-decoration-none">{{ $client->name }}</a></td>
                        <td><span class="badge badge-status-{{ $client->status }}">{{ ucfirst($client->status) }}</span></td>
                        <td class="small text-muted">{{ $client->segment->name ?? '—' }}</td>
                        <td class="small text-muted">{{ $client->relationshipManager->name ?? '—' }}</td>
                        <td class="pe-3 small text-muted">{{ $client->joining_date?->format('d M Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No clients currently eligible for closing.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            {{ $clients->links() }}
            @if($clients->count())
            <button type="submit" class="btn btn-warning">
                <i class="bi bi-person-dash me-1"></i> Mark Selected Inactive
            </button>
            @endif
        </div>
    </div>
</form>

@push('scripts')
<script>
document.getElementById('selectAll')?.addEventListener('change', function () {
    document.querySelectorAll('.client-checkbox').forEach(cb => cb.checked = this.checked);
});
</script>
@endpush
@endsection
