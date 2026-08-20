@extends('layouts.app')
@section('title', 'Send Statements')
@section('breadcrumb')
    <li class="breadcrumb-item active">Send Statements</li>
@endsection
@section('content')
<div class="mb-4">
    <h4 class="fw-bold mb-0">Send Statements</h4>
    <p class="text-muted small mb-0">Email savings statements to selected clients, or send a WhatsApp link to an individual client, for the chosen period.</p>
</div>

{{-- Filter bar --}}
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Name or client #..." value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Segment</label>
                <select name="segment_id" class="form-select">
                    <option value="">All Segments</option>
                    @foreach($segments as $s)
                    <option value="{{ $s->id }}" @selected(request('segment_id') == $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Status</label>
                <select name="status" class="form-select">
                    <option value="active" @selected(request('status','active')=='active')>Active</option>
                    <option value="inactive" @selected(request('status')=='inactive')>Inactive</option>
                    <option value="blacklisted" @selected(request('status')=='blacklisted')>Blacklisted</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">From Date</label>
                <input type="date" name="from_date" class="form-control" value="{{ $fromDate }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">To Date</label>
                <input type="date" name="to_date" class="form-control" value="{{ $toDate }}">
            </div>
            <div class="col-auto">
                <button class="btn btn-outline-primary">Filter</button>
            </div>
        </form>
    </div>
</div>

<form method="POST" action="{{ route('send-statements.send') }}" id="sendForm"
      onsubmit="return confirmSend()">
    @csrf
    <input type="hidden" name="from_date" value="{{ $fromDate }}">
    <input type="hidden" name="to_date" value="{{ $toDate }}">

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>{{ $clients->count() }} client(s) with an active savings account</span>
            <button type="submit" class="btn btn-success btn-sm" id="sendBtn" disabled>
                <i class="bi bi-envelope-fill me-1"></i> Send Statements to Selected (<span id="selectedCount">0</span>)
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                        <th>Client</th>
                        <th>Client #</th>
                        <th>Email</th>
                        <th>Segment</th>
                        <th class="text-center">Active Accounts</th>
                        <th class="text-center">WhatsApp</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($clients as $client)
                    <tr>
                        <td class="ps-3">
                            <input type="checkbox" name="client_ids[]" value="{{ $client->id }}"
                                   class="form-check-input client-checkbox" {{ $client->email ? '' : 'disabled' }}>
                        </td>
                        <td>
                            <a href="{{ route('clients.show', $client) }}" class="text-decoration-none text-dark fw-semibold">{{ $client->name }}</a>
                        </td>
                        <td class="font-monospace text-muted">{{ $client->client_number }}</td>
                        <td>
                            @if($client->email)
                                {{ $client->email }}
                            @else
                                <span class="text-muted small">— no email on file —</span>
                            @endif
                        </td>
                        <td>{{ $client->segment->name ?? '—' }}</td>
                        <td class="text-center">{{ $client->savingsAccounts->count() }}</td>
                        <td class="text-center">
                            @if($client->whatsapp_link)
                                <a href="{{ $client->whatsapp_link }}" target="_blank" rel="noopener"
                                   class="btn btn-sm btn-outline-success" title="Send statement link via WhatsApp">
                                    <i class="bi bi-whatsapp"></i>
                                </a>
                            @else
                                <span class="text-muted small" title="No phone number on file">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No clients match these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</form>

@push('scripts')
<script>
const selectAll = document.getElementById('selectAll');
const checkboxes = () => document.querySelectorAll('.client-checkbox:not(:disabled)');
const sendBtn = document.getElementById('sendBtn');
const countEl = document.getElementById('selectedCount');

function updateCount() {
    const checked = document.querySelectorAll('.client-checkbox:checked').length;
    countEl.textContent = checked;
    sendBtn.disabled = checked === 0;
}

selectAll.addEventListener('change', function () {
    checkboxes().forEach(cb => cb.checked = selectAll.checked);
    updateCount();
});

checkboxes().forEach(cb => cb.addEventListener('change', updateCount));

function confirmSend() {
    const checked = document.querySelectorAll('.client-checkbox:checked').length;
    return confirm(`Send savings statement emails to ${checked} client(s) for the period {{ $fromDate }} to {{ $toDate }}? This may take a while for large batches -- please don't close this tab.`);
}
</script>
@endpush
@endsection
