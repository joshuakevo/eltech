@extends('layouts.app')
@section('title', 'Send Email')
@section('breadcrumb')
    <li class="breadcrumb-item active">Send Email</li>
@endsection
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0">Send Email</h4>
        <p class="text-muted small mb-0">Email clients — loan repayments due/overdue, dormant savings accounts, or anyone else. Sent from <strong>{{ $fromAddress }}</strong>.</p>
    </div>
    <ul class="nav nav-pills">
        <li class="nav-item"><a class="nav-link active" href="{{ route('send-email.index') }}">Send Email</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('send-email.reports') }}">Email Reports</a></li>
    </ul>
</div>

{{-- Recipient group + filters --}}
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end" id="filterForm">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Recipient Group</label>
                <select name="category" class="form-select" onchange="document.getElementById('filterForm').submit()">
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
                    @foreach($segments as $s)<option value="{{ $s->id }}" @selected(request('segment_id') == $s->id)>{{ $s->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-outline-primary">Filter</button></div>
        </form>
    </div>
</div>

@php $withEmail = collect($rows)->filter(fn ($r) => !empty($r['email']))->count(); @endphp
<form method="POST" action="{{ route('send-email.send') }}" id="sendForm" enctype="multipart/form-data" data-no-block="1">
    @csrf
    <input type="hidden" name="category" value="{{ $category }}">
    <input type="hidden" name="test" id="testFlag" value="0">

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">Message</div>
                <div class="card-body">
                    <label class="form-label small fw-semibold">Subject</label>
                    <input type="text" name="subject" class="form-control mb-2" maxlength="200" value="{{ old('subject', $subject) }}" required>
                    <label class="form-label small fw-semibold">Message</label>
                    <textarea name="body" class="form-control" rows="11" maxlength="10000" required>{{ old('body', $body) }}</textarea>
                    <div class="form-text">
                        Placeholders: <code>{name}</code>, <code>{client_number}</code>, <code>{org}</code>
                        @if(in_array($category, ['loan_due','loan_overdue'])), <code>{amount}</code>, <code>{due_date}</code>, <code>{loan_number}</code>
                        @elseif($category === 'dormant_savings'), <code>{account_number}</code>@endif
                        — filled in for each client. Sent with your logo and organisation name.
                    </div>
                    <label class="form-label small fw-semibold mt-3">Attachment <span class="text-muted fw-normal">(optional, up to 5 MB)</span></label>
                    <input type="file" name="attachment" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.csv">
                    <button type="button" class="btn btn-outline-secondary btn-sm mt-3" onclick="sendTest()">
                        <i class="bi bi-envelope-check me-1"></i>Send me a test ({{ auth()->user()->email }})
                    </button>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <span>{{ count($rows) }} recipient(s) · {{ $withEmail }} with an email address</span>
                    <button type="submit" class="btn btn-success btn-sm" id="sendBtn" disabled onclick="document.getElementById('testFlag').value=0; return confirmSend()">
                        <i class="bi bi-envelope-paper-fill me-1"></i> Send Email to Selected (<span id="selectedCount">0</span>)
                    </button>
                </div>
                <div class="table-responsive" style="max-height:520px;overflow-y:auto">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light" style="position:sticky;top:0">
                            <tr>
                                <th class="ps-3"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                                <th>Client</th><th>Email</th><th>Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td class="ps-3"><input type="checkbox" name="ids[]" value="{{ $row['id'] }}" class="form-check-input recipient-checkbox" {{ !empty($row['email']) ? '' : 'disabled' }}></td>
                                <td><div class="fw-semibold">{{ $row['name'] }}</div><div class="text-muted small font-monospace">{{ $row['client_number'] }}</div></td>
                                <td class="small">@if(!empty($row['email'])){{ $row['email'] }}@else<span class="text-muted">— no email —</span>@endif</td>
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
const boxes = () => document.querySelectorAll('.recipient-checkbox:not(:disabled)');
const sendBtn = document.getElementById('sendBtn');
function updateCount() {
    const n = document.querySelectorAll('.recipient-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = n;
    sendBtn.disabled = n === 0;
}
selectAll.addEventListener('change', () => { boxes().forEach(cb => cb.checked = selectAll.checked); updateCount(); });
boxes().forEach(cb => cb.addEventListener('change', updateCount));
function confirmSend() {
    const n = document.querySelectorAll('.recipient-checkbox:checked').length;
    if (!confirm(`Send this email to ${n} recipient(s)? This cannot be undone.`)) return false;
    sendBtn.disabled = true; sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sending ' + n + '…';
    return true;
}
function sendTest() {
    const f = document.getElementById('sendForm');
    document.getElementById('testFlag').value = 1;
    if (!f.reportValidity()) return;
    f.submit();
}
</script>
@endpush
@endsection
