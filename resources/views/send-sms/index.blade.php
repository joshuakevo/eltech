@extends('layouts.app')
@section('title', 'Send SMS')
@section('breadcrumb')
    <li class="breadcrumb-item active">Send SMS</li>
@endsection
@section('content')
<div class="mb-4">
    <h4 class="fw-bold mb-0">Send SMS</h4>
    <p class="text-muted small mb-0">Send SMS reminders to clients — loan repayments due/overdue, dormant savings accounts, or anyone else.</p>
</div>

{{-- Category + filter bar --}}
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end" id="filterForm">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Recipient Group</label>
                <select name="category" id="categorySelect" class="form-select" onchange="document.getElementById('filterForm').submit()">
                    <option value="all" @selected($category=='all')>All Clients</option>
                    <option value="loan_due" @selected($category=='loan_due')>Loan Repayments Due Soon</option>
                    <option value="loan_overdue" @selected($category=='loan_overdue')>Loan Repayments Overdue</option>
                    <option value="dormant_savings" @selected($category=='dormant_savings')>Dormant Savings Accounts</option>
                </select>
            </div>
            @if($category === 'loan_due')
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Due Within (days)</label>
                <input type="number" name="due_days" class="form-control" min="1" max="60" value="{{ $dueDays }}">
            </div>
            @endif
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
            <div class="col-auto">
                <button class="btn btn-outline-primary">Filter</button>
            </div>
        </form>
    </div>
</div>

<form method="POST" action="{{ route('send-sms.send') }}" id="sendForm" onsubmit="return confirmSend()">
    @csrf
    <input type="hidden" name="category" value="{{ $category }}">

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">Message</div>
                <div class="card-body">
                    <textarea name="message" id="messageBox" class="form-control" rows="6" maxlength="918" required>{{ $message }}</textarea>
                    <div class="form-text">
                        <span id="charCount">0</span> characters (~<span id="segmentCount">1</span> SMS segment(s)).
                        Placeholders: <code>{name}</code>, <code>{client_number}</code>, <code>{org}</code>
                        @if(in_array($category, ['loan_due','loan_overdue']))
                            , <code>{amount}</code>, <code>{due_date}</code>, <code>{loan_number}</code>
                        @elseif($category === 'dormant_savings')
                            , <code>{account_number}</code>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>{{ count($rows) }} recipient(s) found</span>
                    <button type="submit" class="btn btn-success btn-sm" id="sendBtn" disabled>
                        <i class="bi bi-chat-dots-fill me-1"></i> Send SMS to Selected (<span id="selectedCount">0</span>)
                    </button>
                </div>
                <div class="table-responsive" style="max-height:480px;overflow-y:auto">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                                <th>Client</th>
                                <th>Phone</th>
                                <th>Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td class="ps-3">
                                    <input type="checkbox" name="ids[]" value="{{ $row['id'] }}"
                                           class="form-check-input recipient-checkbox" {{ $row['phone'] ? '' : 'disabled' }}>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $row['name'] }}</div>
                                    <div class="text-muted small font-monospace">{{ $row['client_number'] }}</div>
                                </td>
                                <td>
                                    @if($row['phone'])
                                        {{ $row['phone'] }}
                                    @else
                                        <span class="text-muted small">— no phone —</span>
                                    @endif
                                </td>
                                <td class="small text-muted">{{ $row['detail'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">No recipients match these filters.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
const selectAll = document.getElementById('selectAll');
const checkboxes = () => document.querySelectorAll('.recipient-checkbox:not(:disabled)');
const sendBtn = document.getElementById('sendBtn');
const countEl = document.getElementById('selectedCount');
const messageBox = document.getElementById('messageBox');
const charCount = document.getElementById('charCount');
const segmentCount = document.getElementById('segmentCount');

function updateCount() {
    const checked = document.querySelectorAll('.recipient-checkbox:checked').length;
    countEl.textContent = checked;
    sendBtn.disabled = checked === 0;
}

function updateCharCount() {
    const len = messageBox.value.length;
    charCount.textContent = len;
    segmentCount.textContent = Math.max(1, Math.ceil(len / 153));
}

selectAll.addEventListener('change', function () {
    checkboxes().forEach(cb => cb.checked = selectAll.checked);
    updateCount();
});
checkboxes().forEach(cb => cb.addEventListener('change', updateCount));
messageBox.addEventListener('input', updateCharCount);
updateCharCount();

function confirmSend() {
    const checked = document.querySelectorAll('.recipient-checkbox:checked').length;
    return confirm(`Send this SMS to ${checked} recipient(s)? This will use real SMS credit and cannot be undone.`);
}
</script>
@endpush
@endsection
