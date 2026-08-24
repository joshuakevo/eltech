@extends('layouts.app')
@section('title', 'Send SMS')
@section('breadcrumb')
    <li class="breadcrumb-item active">Send SMS</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-end mb-3">
    <div>
        <h4 class="fw-bold mb-0">Send SMS</h4>
        <p class="text-muted small mb-0">Send SMS reminders to clients — loan repayments due/overdue, dormant savings accounts, or anyone else.</p>
    </div>
    <ul class="nav nav-pills">
        <li class="nav-item"><a class="nav-link active" href="{{ route('send-sms.index') }}">Send SMS</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('send-sms.reports') }}">Delivery Reports</a></li>
    </ul>
</div>

{{-- Subscription status --}}
@if($activeSubscription)
<div class="alert alert-success small mb-3">
    <i class="bi bi-check-circle-fill me-1"></i>
    SMS subscription active until <strong>{{ $activeSubscription->period_end->format('d M Y') }}</strong>.
</div>
@elseif($canSend)
<div class="alert alert-info small mb-3 d-flex justify-content-between align-items-center">
    <span><i class="bi bi-info-circle-fill me-1"></i> Free trial: <strong>{{ $freeTrialRemaining }}</strong> SMS remaining before a subscription is required.</span>
    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#subscribeModal">Subscribe now</button>
</div>
@else
<div class="alert alert-danger small mb-3">
    <i class="bi bi-exclamation-triangle-fill me-1"></i>
    Your free trial has ended and there is no active SMS subscription. Sending is disabled until you subscribe.
    <button type="button" class="btn btn-sm btn-danger ms-2" data-bs-toggle="modal" data-bs-target="#subscribeModal">Subscribe now</button>
</div>
@endif

@if($recentSubscriptionAttempts->isNotEmpty())
<div class="card mb-3">
    <div class="card-header py-2 small fw-semibold">Recent Subscription Payment Attempts</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr>
                <th class="ps-3">Date</th><th>Phone</th><th>Amount</th><th>Status</th><th class="pe-3">Failure Reason</th>
            </tr></thead>
            <tbody>
            @foreach($recentSubscriptionAttempts as $attempt)
                <tr>
                    <td class="ps-3 small text-muted text-nowrap">{{ $attempt->created_at->format('d M Y H:i') }}</td>
                    <td class="small font-monospace">{{ $attempt->phone_number }}</td>
                    <td class="small">{{ number_format($attempt->amount, 0) }}</td>
                    <td>
                        <span class="badge bg-{{ $attempt->status === 'successful' ? 'success' : ($attempt->status === 'failed' ? 'danger' : 'secondary') }}-subtle text-{{ $attempt->status === 'successful' ? 'success' : ($attempt->status === 'failed' ? 'danger' : 'secondary') }}">
                            {{ ucfirst($attempt->status) }}
                        </span>
                    </td>
                    <td class="pe-3 small text-muted">{{ $attempt->failure_reason ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Subscribe Modal --}}
<div class="modal fade" id="subscribeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('send-sms.subscribe') }}">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title"><i class="bi bi-chat-dots-fill me-2 text-primary"></i>Subscribe to SMS</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">
                        {{ number_format($subscriptionPrice, 0) }} UGX/month, charged via mobile money.
                        Enter the phone number to charge — you'll get a USSD/PIN prompt on that phone to approve the payment.
                    </p>
                    <label class="form-label fw-semibold">Phone Number</label>
                    <input type="text" name="phone_number" class="form-control" placeholder="07XXXXXXXX" required>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Request Payment</button>
                </div>
            </form>
        </div>
    </div>
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
                    <button type="submit" class="btn btn-success btn-sm" id="sendBtn" disabled
                            @if(!$canSend) title="Subscribe to SMS to continue sending" @endif>
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

const canSend = @json($canSend);

function updateCount() {
    const checked = document.querySelectorAll('.recipient-checkbox:checked').length;
    countEl.textContent = checked;
    sendBtn.disabled = checked === 0 || !canSend;
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
