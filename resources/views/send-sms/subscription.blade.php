@extends('layouts.app')
@section('title', 'SMS Subscription')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('send-sms.index') }}">Send SMS</a></li>
    <li class="breadcrumb-item active">Subscription</li>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-end mb-3">
    <div>
        <h4 class="fw-bold mb-0">SMS Subscription</h4>
        <p class="text-muted small mb-0">Free trial status, subscription payments, and full payment history.</p>
    </div>
    <ul class="nav nav-pills">
        <li class="nav-item"><a class="nav-link" href="{{ route('send-sms.index') }}">Send SMS</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('send-sms.reports') }}">Delivery Reports</a></li>
        <li class="nav-item"><a class="nav-link active" href="{{ route('send-sms.subscription') }}">Subscription</a></li>
    </ul>
</div>

{{-- Subscription status --}}
@if($activeSubscription)
<div class="alert alert-success small mb-3">
    <i class="bi bi-check-circle-fill me-1"></i>
    SMS subscription active until <strong>{{ $activeSubscription->period_end->format('d M Y') }}</strong>.
    <button type="button" class="btn btn-sm btn-outline-success ms-2" data-bs-toggle="modal" data-bs-target="#subscribeModal">Renew / Add Another</button>
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

<div class="card">
    <div class="card-header py-2 small fw-semibold">Subscription Payment History</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr>
                <th class="ps-3">Date</th><th>Phone</th><th>Amount</th><th>Status</th><th>Period</th>
                <th>Initiated By</th><th class="pe-3">Failure Reason</th>
            </tr></thead>
            <tbody>
            @forelse($payments as $payment)
                <tr>
                    <td class="ps-3 small text-muted text-nowrap">{{ $payment->created_at->format('d M Y H:i') }}</td>
                    <td class="small font-monospace">{{ $payment->phone_number }}</td>
                    <td class="small">{{ number_format($payment->amount, 0) }}</td>
                    <td>
                        <span class="badge bg-{{ $payment->status === 'successful' ? 'success' : ($payment->status === 'failed' || $payment->status === 'cancelled' ? 'danger' : 'secondary') }}-subtle text-{{ $payment->status === 'successful' ? 'success' : ($payment->status === 'failed' || $payment->status === 'cancelled' ? 'danger' : 'secondary') }}">
                            {{ ucfirst($payment->status) }}
                        </span>
                    </td>
                    <td class="small text-muted">
                        @if($payment->period_start)
                            {{ $payment->period_start->format('d M') }} – {{ $payment->period_end->format('d M Y') }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="small text-muted">{{ $payment->initiatedBy->name ?? '—' }}</td>
                    <td class="pe-3 small text-muted">{{ $payment->failure_reason ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No subscription payments yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $payments->links() }}</div>
</div>
@endsection
